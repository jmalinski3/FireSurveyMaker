#!/usr/bin/env python3
"""UKG roster grabber.

Logs into UKG with a headless browser, scrapes your roster/schedule, and writes
it to a CSV. Built to run from cron on an always-on box (e.g. a Raspberry Pi).

Security model
--------------
- Secrets (username, password, TOTP seed) are read from the OS keyring via
  python-keyring. Run ``python set_secrets.py`` once to store them. A plaintext
  .env fallback exists for testing but is discouraged.
- The browser uses a PERSISTENT profile directory, so UKG's "remember this
  device for 7 days" cookie survives between runs. The TOTP is therefore only
  needed about once a week; most runs reuse the session with no login at all.
- The password and the generated TOTP code are NEVER written to the logs.

Setup
-----
1. pip install -r requirements.txt && playwright install chromium
2. python set_secrets.py            # store creds in the OS keyring
3. cp config.example.yaml config.yaml  # then fill in URLs + selectors
4. python ukg_roster.py --headful   # first run: watch it, verify selectors
5. Install crontab.example once it works headless.
"""
from __future__ import annotations

import argparse
import csv
import datetime as dt
import fcntl
import io
import logging
import os
import re
import sys
from pathlib import Path

import yaml
import pyotp
import keyring
from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

KEYRING_SERVICE = "ukg-roster"
HERE = Path(__file__).resolve().parent


def build_logger(log_path: Path) -> logging.Logger:
    log = logging.getLogger("ukg-roster")
    log.setLevel(logging.INFO)
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(message)s")
    stream = logging.StreamHandler(sys.stdout)
    stream.setFormatter(fmt)
    log.addHandler(stream)
    fileh = logging.FileHandler(log_path)
    fileh.setFormatter(fmt)
    log.addHandler(fileh)
    return log


def load_config(path: Path) -> dict:
    if not path.exists():
        raise SystemExit(f"Config not found: {path}. Copy config.example.yaml to config.yaml.")
    with open(path) as f:
        return yaml.safe_load(f)


def get_secret(name: str, env_key: str) -> str:
    """Prefer the OS keyring; fall back to an env var for testing."""
    value = keyring.get_password(KEYRING_SERVICE, name)
    if value:
        return value
    value = os.environ.get(env_key)
    if value:
        return value
    raise SystemExit(
        f"Missing secret '{name}'. Run set_secrets.py or set ${env_key}."
    )


def acquire_lock(lock_path: Path):
    """Single-instance guard so overlapping cron runs don't double-login."""
    handle = open(lock_path, "w")
    try:
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except OSError:
        return None
    return handle


def is_visible(page, selector: str, timeout: int = 5000) -> bool:
    try:
        page.locator(selector).first.wait_for(state="visible", timeout=timeout)
        return True
    except Exception:
        return False


def is_present(page, selector: str, timeout: int = 5000) -> bool:
    """Present in the DOM (attached), regardless of CSS visibility. The
    Telestaff roster <li> rows have no layout box of their own, so they never
    report as 'visible' even when fully loaded; we read them via the DOM."""
    try:
        page.locator(selector).first.wait_for(state="attached", timeout=timeout)
        return True
    except Exception:
        return False


def goto_roster(page, cfg: dict) -> None:
    page.goto(
        cfg["roster_url"],
        wait_until="domcontentloaded",
        timeout=cfg["timeouts"]["navigation_ms"],
    )


def reach_roster(page, cfg: dict, log: logging.Logger) -> None:
    """Navigate to the roster, dismissing any post-login interstitials first.

    UKG Telestaff sometimes redirects a fresh login through a "contact log"
    page (/telestaff/checkContactLog) that must be closed before the roster
    renders. dismiss_selectors lists the close/acknowledge button(s); each is
    clicked if present, then we re-navigate and re-check for the roster.
    """
    sel = cfg["selectors"]
    el_to = cfg["timeouts"]["element_ms"]
    dismiss = cfg.get("dismiss_selectors") or []
    for attempt in range(3):
        goto_roster(page, cfg)
        if is_present(page, sel["roster_ready_marker"], timeout=el_to):
            return
        dismissed = False
        for d in dismiss:
            if is_visible(page, d, timeout=2000):
                try:
                    page.click(d)
                    page.wait_for_load_state(
                        "domcontentloaded", timeout=cfg["timeouts"]["navigation_ms"]
                    )
                    log.info("Dismissed a post-login interstitial popup.")
                    dismissed = True
                except Exception as exc:  # noqa: BLE001
                    log.warning("Could not dismiss interstitial %s: %s", d, exc)
        if not dismissed:
            log.warning(
                "Roster not ready and no interstitial to dismiss (attempt %d/3).",
                attempt + 1,
            )
    # Final readiness is enforced by scrape_roster's wait_for_selector.


def ensure_logged_in(page, cfg: dict, secrets: dict, log: logging.Logger) -> None:
    sel = cfg["selectors"]
    el_to = cfg["timeouts"]["element_ms"]

    if is_present(page, sel["roster_ready_marker"], timeout=3000):
        log.info("Session still valid; roster loaded without logging in.")
        return

    mfa_continue = sel.get("mfa_continue_button")

    if is_visible(page, sel["password_input"], timeout=el_to):
        log.info("Login form detected; submitting credentials.")
        if is_visible(page, sel["username_input"], timeout=2000):
            page.fill(sel["username_input"], secrets["username"])
        page.fill(sel["password_input"], secrets["password"])
        page.click(sel["submit_button"])
        # After submit we may land on an MFA method-selection page, the TOTP
        # code prompt, or straight on the roster (device still trusted).
        wait_targets = [sel["totp_input"], sel["roster_ready_marker"]]
        if mfa_continue:
            wait_targets.append(mfa_continue)
        try:
            page.wait_for_selector(
                ", ".join(wait_targets), state="attached", timeout=el_to
            )
        except PWTimeout:
            log.warning("No MFA, TOTP, or roster page appeared after login submit.")

    # Some flows (e.g. UKG Telestaff) show an MFA method picker with the
    # authenticator app preselected and a "Continue" button before the code
    # entry page. Advance past it. Guard on the code field NOT being present so
    # this is skipped when continue/submit share a selector on the code page.
    if (
        mfa_continue
        and is_visible(page, mfa_continue, timeout=3000)
        and not is_visible(page, sel["totp_input"], timeout=500)
    ):
        log.info("MFA method page detected; clicking continue to reach code entry.")
        page.click(mfa_continue)
        try:
            page.wait_for_selector(sel["totp_input"], timeout=el_to)
        except PWTimeout:
            log.warning("TOTP code field did not appear after MFA continue.")

    if is_visible(page, sel["totp_input"], timeout=3000):
        log.info("TOTP prompt detected; generating one-time code.")
        page.fill(sel["totp_input"], pyotp.TOTP(secrets["totp_secret"]).now())
        if is_visible(page, sel["remember_device_checkbox"], timeout=2000):
            try:
                page.check(sel["remember_device_checkbox"])
                log.info("Checked 'remember this device' (renews the 7-day trust).")
            except Exception:
                log.warning("Could not check the remember-device box; continuing.")
        page.click(sel["totp_submit_button"])
    else:
        log.info("No TOTP prompt (device still trusted or already authenticated).")

    reach_roster(page, cfg, log)


ROSTER_HEADER = [
    "date", "shift", "station", "unit", "position", "name", "person_id",
    "job_title", "work_code", "status", "record_type", "start", "end", "duration",
]

# The Telestaff roster is a nested tree (#rosterFixedContent: date > battalion >
# shift > station > unit > position), not an HTML table. This walks each
# position row and pulls its fields plus the station/unit/shift/date context
# from its ancestors. Returns a list of rows aligned with ROSTER_HEADER.
ROSTER_EXTRACTOR_JS = r"""
() => {
  const root = document.querySelector('#rosterFixedContent');
  if (!root) return [];
  const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const txt = (el) => clean(el ? el.textContent : '');
  const attr = (el, a) => (el && el.getAttribute(a)) || '';
  const out = [];
  root.querySelectorAll('li.idPosition').forEach((li) => {
    const dateLi = li.closest('li.idDate');
    const shiftLi = li.closest('li.idShift');
    const stationLi = li.closest('li.idStation');
    const unitLi = li.closest('li.idUnit');
    const item = li.querySelector('.positionItem');
    const resource = li.querySelector('.nameColumn.resourceDisplay');
    const exc = li.querySelector('.exceptionColumn');
    out.push([
      attr(dateLi, 'data-date-ymd'),
      txt(shiftLi && shiftLi.querySelector('.shiftNameText')),
      txt(stationLi && stationLi.querySelector('.organizationName .bold')),
      txt(unitLi && unitLi.querySelector('.unitName .bold')),
      txt(li.querySelector('.positionName .positionNameText')),
      txt(li.querySelector('.displayNameText')) ||
        txt(li.querySelector('.vacancyDisplay .pull-left')),
      txt(li.querySelector('.idColumnText')),
      attr(resource, 'data-popup-jobtitle'),
      attr(exc, 'data-popup-title'),
      attr(exc, 'data-popup-status'),
      attr(item, 'data-record-type'),
      attr(li.querySelector('[data-field="startshift"]'), 'data-popup-value'),
      attr(li.querySelector('[data-field="endshift"]'), 'data-popup-value'),
      attr(li.querySelector('[data-field="duration"]'), 'data-popup-value'),
    ]);
  });
  return out;
}
"""


def scrape_roster(page, cfg: dict, log: logging.Logger) -> list[list[str]]:
    sel = cfg["selectors"]
    page.wait_for_selector(
        sel["roster_ready_marker"], state="attached", timeout=cfg["timeouts"]["element_ms"]
    )
    data = page.evaluate(ROSTER_EXTRACTOR_JS)
    if not data:
        log.warning("Roster container loaded but no position rows were parsed.")
        return []
    log.info("Scraped %d roster rows.", len(data))
    return [ROSTER_HEADER] + data


def render_csv(rows: list[list[str]]) -> str:
    buf = io.StringIO()
    csv.writer(buf, lineterminator="\n").writerows(rows)
    return buf.getvalue()


def write_csv_if_changed(rows: list[list[str]], out_path: Path, log: logging.Logger) -> bool:
    """Write the CSV only when its content differs from the existing file, so a
    published roster is left untouched until something actually changes.
    Returns True if the file was (re)written."""
    new_text = render_csv(rows)
    existing = out_path.read_text(encoding="utf-8") if out_path.exists() else None
    if existing == new_text:
        log.info("Roster unchanged for %s; keeping existing file.", out_path.name)
        return False
    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(new_text, encoding="utf-8")
    verb = "Updated" if existing is not None else "Wrote"
    log.info("%s roster %s (%d data rows).", verb, out_path.name, max(len(rows) - 1, 0))
    return True


ROSTER_FILE_RE = re.compile(r"^roster_(\d{4}-\d{2}-\d{2})\.csv$")


def prune_old_csvs(out_dir: Path, cfg: dict, log: logging.Logger) -> None:
    """Delete roster CSVs whose roster date is older than retain_past_days days
    before today (default 1, i.e. keep yesterday onward)."""
    retain = int(cfg.get("output", {}).get("retain_past_days", 1))
    cutoff = dt.date.today() - dt.timedelta(days=retain)
    if not out_dir.exists():
        return
    for f in out_dir.glob("roster_*.csv"):
        match = ROSTER_FILE_RE.match(f.name)
        if not match:
            continue
        try:
            file_date = dt.date.fromisoformat(match.group(1))
        except ValueError:
            continue
        if file_date < cutoff:
            f.unlink()
            log.info("Pruned old roster %s (roster date before %s).", f.name, cutoff.isoformat())


def main() -> int:
    parser = argparse.ArgumentParser(description="Grab UKG roster and export to CSV.")
    parser.add_argument("--config", default=str(HERE / "config.yaml"))
    parser.add_argument("--headful", action="store_true", help="Show the browser (first-run setup).")
    args = parser.parse_args()

    cfg = load_config(Path(args.config))
    log = build_logger(HERE / "ukg_roster.log")

    today = dt.date.today().isoformat()
    out_dir = (HERE / cfg["output"]["dir"]).resolve()
    out_path = out_dir / cfg["output"]["filename"].format(date=today)

    lock = acquire_lock(HERE / "ukg_roster.lock")
    if lock is None:
        log.info("Another instance is running; exiting.")
        return 0

    secrets = {
        "username": get_secret("username", "UKG_USERNAME"),
        "password": get_secret("password", "UKG_PASSWORD"),
        "totp_secret": get_secret("totp_secret", "UKG_TOTP_SECRET"),
    }

    profile_dir = (HERE / cfg["profile_dir"]).resolve()
    profile_dir.mkdir(parents=True, exist_ok=True)
    headless = not args.headful and cfg.get("headless", True)

    with sync_playwright() as p:
        ctx = p.chromium.launch_persistent_context(
            user_data_dir=str(profile_dir), headless=headless
        )
        page = ctx.pages[0] if ctx.pages else ctx.new_page()
        try:
            goto_roster(page, cfg)
            ensure_logged_in(page, cfg, secrets, log)
            rows = scrape_roster(page, cfg, log)
            if not rows:
                log.warning("Roster appears empty; not overwriting any prior capture.")
                return 1
            write_csv_if_changed(rows, out_path, log)
            prune_old_csvs(out_dir, cfg, log)
        except Exception as exc:  # noqa: BLE001 - top-level guard for cron
            log.error("Run failed: %s", exc)
            if cfg.get("debug_screenshots", False):
                debug_dir = (HERE / "debug").resolve()
                debug_dir.mkdir(parents=True, exist_ok=True)
                shot = debug_dir / f"fail_{today}_{dt.datetime.now():%H%M%S}.png"
                try:
                    page.screenshot(path=str(shot))
                    log.error("Saved debug screenshot: %s", shot)
                except Exception:
                    pass
            return 1
        finally:
            ctx.close()  # persists cookies, including the 7-day trust cookie
    return 0


if __name__ == "__main__":
    sys.exit(main())

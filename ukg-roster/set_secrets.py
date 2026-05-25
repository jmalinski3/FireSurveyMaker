#!/usr/bin/env python3
"""Store UKG secrets in the OS keyring (run once, interactively).

Nothing is written to disk in plaintext: keyring uses the platform secret
store (libsecret on Linux, Keychain on macOS, Credential Manager on Windows).
The password and TOTP seed are entered hidden via getpass.
"""
import getpass
import sys

import keyring
import pyotp

SERVICE = "ukg-roster"


def main() -> int:
    username = input("UKG username: ").strip()
    password = getpass.getpass("UKG password: ")
    totp_seed = getpass.getpass("TOTP secret (base32 seed): ").strip().replace(" ", "")

    try:
        code = pyotp.TOTP(totp_seed).now()
    except Exception as exc:  # noqa: BLE001
        print(f"That TOTP seed is not valid base32: {exc}", file=sys.stderr)
        return 1

    keyring.set_password(SERVICE, "username", username)
    keyring.set_password(SERVICE, "password", password)
    keyring.set_password(SERVICE, "totp_secret", totp_seed)

    print("Stored username, password, and TOTP seed in the OS keyring.")
    print(f"Sanity check - current code is {code}. It should match your authenticator app.")
    return 0


if __name__ == "__main__":
    sys.exit(main())

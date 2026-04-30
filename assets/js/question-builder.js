/* global */
/**
 * Shared question-builder factory used by both the admin survey editor and
 * the frontend "create survey" page. Exposes window.fsmCreateBuilder(config).
 *
 * config keys:
 *   root             — root element id (string)
 *   listId           — id of the questions-list container
 *   addBtnId         — id of the "add question" button
 *   saveBtnId        — id of the save button
 *   statusId         — id of the save-status element
 *   fields           — { title, description, status, visibility, startDate, endDate } DOM ids
 *   classes          — markup classes for cards / inputs / buttons (see below)
 *   restUrl, nonce   — REST API config
 *   i18n             — { saving, saved, error, ...optional labels }
 *   initialSurvey    — survey object with embedded questions[] (or null for create)
 *   savePath(survey) — function returning the REST path for the save call
 *   saveMethod(survey) — function returning 'PUT' | 'POST'
 *   onSaved(result)  — callback invoked after a successful save
 *   onSaveError(msg) — optional callback on error (defaults to writing to status el)
 */
(function () {
	'use strict';

	function escAttr(s) {
		return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	function escHtml(s) {
		return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	const TYPES = {
		multiple_choice: 'Multiple Choice',
		checkbox:        'Checkboxes',
		short_text:      'Short Text',
	};

	function firstClass(str) {
		return '.' + String(str).trim().split(/\s+/)[0];
	}

	window.fsmCreateBuilder = function (cfg) {
		const root = document.getElementById(cfg.root);
		if (!root) return null;

		const cls    = cfg.classes;
		const fields = cfg.fields || {};
		const i18n   = cfg.i18n   || {};

		let surveyData = cfg.initialSurvey || null;
		let questions  = surveyData ? (surveyData.questions || []) : [];

		function getField(id) {
			if (!id) return '';
			const el = document.getElementById(id);
			return el ? el.value : '';
		}

		function renderQuestions() {
			const list = document.getElementById(cfg.listId);
			if (!list) return;
			list.innerHTML = '';
			questions.forEach((q, idx) => list.appendChild(renderCard(q, idx)));
		}

		function renderCard(q, idx) {
			const card = document.createElement('div');
			card.className = cls.card;
			card.dataset.idx = idx;

			const typeOpts = Object.entries(TYPES)
				.map(([v, l]) => `<option value="${v}" ${q.question_type === v ? 'selected' : ''}>${escHtml(l)}</option>`)
				.join('');

			const optionsHtml = (q.options || [])
				.map((o, oi) => optionRowHtml(o.option_text || o, oi))
				.join('');

			const showOptions = ['multiple_choice', 'checkbox'].includes(q.question_type);

			card.innerHTML = `
				<div class="${cls.header}">
					${cls.dragHandleHtml || ''}
					<input type="text" class="${cls.qText}" placeholder="Question text" value="${escAttr(q.question_text || '')}">
					<select class="${cls.qType}">${typeOpts}</select>
					<label class="${cls.requiredLabel || ''}">
						<input type="checkbox" class="${cls.qRequired}" ${q.required != 0 ? 'checked' : ''}> Required
					</label>
					<button type="button" class="${cls.removeQuestion}" title="Remove question">&times;</button>
				</div>
				<div class="${cls.options}" style="display:${showOptions ? 'block' : 'none'}">
					<div class="${cls.optionsRows}">${optionsHtml}</div>
					<button type="button" class="${cls.addOption}">+ Add Option</button>
				</div>
			`;

			card.querySelector(firstClass(cls.qText)).addEventListener('input', e => { questions[idx].question_text = e.target.value; });
			card.querySelector(firstClass(cls.qRequired)).addEventListener('change', e => { questions[idx].required = e.target.checked ? 1 : 0; });
			card.querySelector(firstClass(cls.removeQuestion)).addEventListener('click', () => {
				questions.splice(idx, 1);
				renderQuestions();
			});
			card.querySelector(firstClass(cls.qType)).addEventListener('change', e => {
				questions[idx].question_type = e.target.value;
				card.querySelector(firstClass(cls.options)).style.display =
					['multiple_choice', 'checkbox'].includes(e.target.value) ? 'block' : 'none';
			});
			card.querySelector(firstClass(cls.addOption)).addEventListener('click', () => {
				if (!questions[idx].options) questions[idx].options = [];
				questions[idx].options.push('');
				renderQuestions();
			});

			bindOptionEvents(card, idx);
			return card;
		}

		function optionRowHtml(text, oi) {
			return `<div class="${cls.optionRow}" data-oi="${oi}">
				<input type="text" class="${cls.optionText}" placeholder="Option text" value="${escAttr(text)}">
				<button type="button" class="${cls.removeOption}" title="Remove option">&times;</button>
			</div>`;
		}

		function bindOptionEvents(card, idx) {
			card.querySelectorAll(firstClass(cls.optionText)).forEach((inp, oi) => {
				inp.addEventListener('input', e => {
					if (!questions[idx].options) questions[idx].options = [];
					questions[idx].options[oi] = e.target.value;
				});
			});
			card.querySelectorAll(firstClass(cls.removeOption)).forEach((btn, oi) => {
				btn.addEventListener('click', () => {
					if (questions[idx].options) questions[idx].options.splice(oi, 1);
					renderQuestions();
				});
			});
		}

		function setStatus(msg, isError) {
			const el = document.getElementById(cfg.statusId);
			if (!el) return;
			el.textContent = msg;
			if (isError && cfg.errorColor !== false) {
				el.style.color = '#dc2626';
			} else {
				el.style.color = '';
			}
		}

		function buildPayload() {
			const payload = {
				title:              getField(fields.title).trim(),
				description:        getField(fields.description),
				status:             getField(fields.status) || 'draft',
				results_visibility: getField(fields.visibility) || 'after_submit',
				questions:          questions.map((q, i) => ({
					...q,
					sort_order: i,
					options: (q.options || []).filter(o => String(o.option_text || o).trim()),
				})),
			};
			const startRaw = getField(fields.startDate);
			const endRaw   = getField(fields.endDate);
			if (startRaw) payload.start_date = startRaw.replace('T', ' ');
			if (endRaw)   payload.end_date   = endRaw.replace('T', ' ');
			return payload;
		}

		async function save() {
			const payload = buildPayload();

			if (cfg.requireTitle && !payload.title) {
				setStatus(i18n.titleRequired || 'Title is required.', true);
				const el = document.getElementById(fields.title);
				if (el) el.focus();
				return;
			}

			const saveBtn = document.getElementById(cfg.saveBtnId);
			if (saveBtn) saveBtn.disabled = true;
			setStatus(i18n.saving || 'Saving…', false);

			const method = cfg.saveMethod ? cfg.saveMethod(surveyData) : (surveyData && surveyData.id ? 'PUT' : 'POST');
			const path   = cfg.savePath ? cfg.savePath(surveyData) : (surveyData && surveyData.id ? 'surveys/' + surveyData.id : 'surveys');

			try {
				const resp = await fetch(cfg.restUrl + path, {
					method,
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body:    JSON.stringify(payload),
				});
				const result = await resp.json();

				if (resp.ok && result.id) {
					surveyData = result;
					questions  = result.questions || [];
					renderQuestions();
					setStatus(i18n.saved || 'Saved!', false);
					// onSaved owns the save button on success: callers that allow
					// further edits (admin) should re-enable it; callers that
					// redirect (frontend) should leave it disabled to prevent a
					// duplicate submission during the redirect delay.
					if (cfg.onSaved) cfg.onSaved(result, { saveBtn });
				} else {
					setStatus(result.message || i18n.error || 'An error occurred.', true);
					if (saveBtn) saveBtn.disabled = false;
				}
			} catch (e) {
				setStatus((i18n.error || 'An error occurred.') + (e && e.message ? ' (' + e.message + ')' : ''), true);
				if (saveBtn) saveBtn.disabled = false;
			}
		}

		const addBtn = document.getElementById(cfg.addBtnId);
		if (addBtn) {
			addBtn.addEventListener('click', () => {
				questions.push({ question_text: '', question_type: 'multiple_choice', required: 1, options: [] });
				renderQuestions();
			});
		}

		const saveBtn = document.getElementById(cfg.saveBtnId);
		if (saveBtn) saveBtn.addEventListener('click', save);

		renderQuestions();

		return { renderQuestions, escAttr, escHtml };
	};

	// Expose escape helpers so admin-only code (e.g. results renderer) can reuse them.
	window.fsmEscAttr = escAttr;
	window.fsmEscHtml = escHtml;
})();

/* global fsmBuilder */
(function () {
	'use strict';

	const builderEl = document.getElementById('fsm-frontend-builder');
	if (!builderEl) return;

	const cfg     = window.fsmBuilder || {};
	const restUrl = cfg.restUrl || '';
	const nonce   = cfg.nonce  || '';
	const siteUrl = cfg.siteUrl || '/';
	const i18n    = cfg.i18n   || {};
	let questions = [];

	// -------------------------------------------------------------------------
	// Question rendering
	// -------------------------------------------------------------------------
	function renderQuestions() {
		const list = document.getElementById('fsm-fe-questions');
		if (!list) return;
		list.innerHTML = '';
		questions.forEach((q, idx) => renderCard(list, q, idx));
	}

	function renderCard(container, q, idx) {
		const card = document.createElement('div');
		card.className = 'fsm-fe-question-card';

		const types = { multiple_choice: 'Multiple Choice', checkbox: 'Checkboxes', short_text: 'Short Text' };
		const typeOpts = Object.entries(types)
			.map(([v, l]) => `<option value="${v}" ${q.question_type === v ? 'selected' : ''}>${escAttr(l)}</option>`)
			.join('');

		const showOptions = ['multiple_choice', 'checkbox'].includes(q.question_type);

		card.innerHTML = `
			<div class="fsm-fe-question-card__header">
				<input type="text" class="fsm-input fsm-fe-q-text" placeholder="Question text" value="${escAttr(q.question_text || '')}">
				<select class="fsm-input fsm-fe-q-type">${typeOpts}</select>
				<label class="fsm-fe-required-label">
					<input type="checkbox" class="fsm-fe-q-required" ${q.required != 0 ? 'checked' : ''}> Required
				</label>
				<button type="button" class="fsm-btn-icon fsm-fe-remove-q" title="Remove">&times;</button>
			</div>
			<div class="fsm-fe-options" style="display:${showOptions ? 'block' : 'none'}">
				<div class="fsm-fe-options-rows">${(q.options || []).map((o, oi) => optionRowHtml(o, oi)).join('')}</div>
				<button type="button" class="fsm-btn fsm-btn--ghost fsm-fe-add-opt">+ Add Option</button>
			</div>
		`;

		card.querySelector('.fsm-fe-q-text').addEventListener('input', e => { questions[idx].question_text = e.target.value; });
		card.querySelector('.fsm-fe-q-required').addEventListener('change', e => { questions[idx].required = e.target.checked ? 1 : 0; });
		card.querySelector('.fsm-fe-remove-q').addEventListener('click', () => { questions.splice(idx, 1); renderQuestions(); });

		const typeSelect = card.querySelector('.fsm-fe-q-type');
		typeSelect.addEventListener('change', e => {
			questions[idx].question_type = e.target.value;
			card.querySelector('.fsm-fe-options').style.display = ['multiple_choice', 'checkbox'].includes(e.target.value) ? 'block' : 'none';
		});

		card.querySelector('.fsm-fe-add-opt').addEventListener('click', () => {
			if (!questions[idx].options) questions[idx].options = [];
			questions[idx].options.push('');
			renderQuestions();
		});

		bindOptionEvents(card, idx);
		container.appendChild(card);
	}

	function optionRowHtml(text, oi) {
		return `<div class="fsm-fe-option-row" data-oi="${oi}">
			<input type="text" class="fsm-input fsm-fe-opt-text" placeholder="Option" value="${escAttr(text)}">
			<button type="button" class="fsm-btn-icon fsm-fe-remove-opt">&times;</button>
		</div>`;
	}

	function bindOptionEvents(card, idx) {
		card.querySelectorAll('.fsm-fe-opt-text').forEach((inp, oi) => {
			inp.addEventListener('input', e => {
				if (!questions[idx].options) questions[idx].options = [];
				questions[idx].options[oi] = e.target.value;
			});
		});
		card.querySelectorAll('.fsm-fe-remove-opt').forEach((btn, oi) => {
			btn.addEventListener('click', () => { questions[idx].options.splice(oi, 1); renderQuestions(); });
		});
	}

	function escAttr(str) {
		return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	function setStatus(msg, isError) {
		const el = document.getElementById('fsm-fe-save-status');
		if (!el) return;
		el.textContent = msg;
		el.style.color = isError ? '#dc2626' : '#4b5563';
	}

	// -------------------------------------------------------------------------
	// Wire up buttons
	// -------------------------------------------------------------------------
	const addQBtn = document.getElementById('fsm-fe-add-question');
	if (addQBtn) {
		addQBtn.addEventListener('click', () => {
			questions.push({ question_text: '', question_type: 'multiple_choice', required: 1, options: [] });
			renderQuestions();
		});
	}

	const saveBtn = document.getElementById('fsm-fe-save');

	if (saveBtn) {
		saveBtn.addEventListener('click', async () => {
			const titleEl = document.getElementById('fsm-fe-title');
			const title   = titleEl ? titleEl.value.trim() : '';

			if (!title) {
				setStatus(i18n.titleRequired || 'Title is required.', true);
				if (titleEl) titleEl.focus();
				return;
			}

			if (!restUrl) {
				setStatus('Configuration error: REST URL missing. Is the plugin active?', true);
				return;
			}

			saveBtn.disabled = true;
			setStatus(i18n.saving || 'Saving…', false);

			const startRaw = (document.getElementById('fsm-fe-start') || {}).value || '';
			const endRaw   = (document.getElementById('fsm-fe-end')   || {}).value || '';

			const payload = {
				title,
				description:        (document.getElementById('fsm-fe-description') || {}).value || '',
				status:             (document.getElementById('fsm-fe-status')      || {}).value || 'draft',
				results_visibility: (document.getElementById('fsm-fe-visibility')  || {}).value || 'after_submit',
				start_date:         startRaw ? startRaw.replace('T', ' ') : null,
				end_date:           endRaw   ? endRaw.replace('T', ' ')   : null,
				questions:          questions.map((q, i) => ({
					...q,
					sort_order: i,
					options: (q.options || []).filter(o => String(o).trim()),
				})),
			};

			try {
				const resp = await fetch(restUrl + 'surveys', {
					method:  'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body:    JSON.stringify(payload),
				});
				const data = await resp.json();

				if (resp.ok && data.slug) {
					setStatus(i18n.saved || 'Survey created! Redirecting…', false);
					setTimeout(() => {
						window.location.href = siteUrl + 'surveys/' + data.slug + '/';
					}, 1200);
				} else {
					setStatus(data.message || i18n.error || 'An error occurred.', true);
					saveBtn.disabled = false;
				}
			} catch (err) {
				setStatus((i18n.error || 'An error occurred.') + ' (' + err.message + ')', true);
				saveBtn.disabled = false;
			}
		});
	}

	renderQuestions();
})();

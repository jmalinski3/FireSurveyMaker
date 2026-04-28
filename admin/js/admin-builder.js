/* global fsmAdmin */
(function () {
	'use strict';

	const builderEl = document.getElementById('fsm-builder');
	if (!builderEl) return;

	const restUrl = fsmAdmin.restUrl;
	const nonce   = fsmAdmin.nonce;
	const i18n    = fsmAdmin.i18n;

	let surveyData = JSON.parse(builderEl.dataset.survey || 'null');
	let questions  = surveyData ? (surveyData.questions || []) : [];

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------
	function apiFetch(path, method = 'GET', body = null) {
		const opts = {
			method,
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
		};
		if (body) opts.body = JSON.stringify(body);
		return fetch(restUrl + path, opts).then(r => r.json());
	}

	function getField(id) {
		const el = document.getElementById(id);
		return el ? el.value : '';
	}

	// -------------------------------------------------------------------------
	// Render question list
	// -------------------------------------------------------------------------
	function renderQuestions() {
		const list = document.getElementById('fsm-questions-list');
		if (!list) return;
		list.innerHTML = '';
		questions.forEach((q, idx) => renderQuestionCard(list, q, idx));
	}

	function renderQuestionCard(container, q, idx) {
		const card = document.createElement('div');
		card.className = 'fsm-question-card';
		card.dataset.idx = idx;

		const types = { multiple_choice: 'Multiple Choice', checkbox: 'Checkboxes', short_text: 'Short Text' };
		const typeOptions = Object.entries(types)
			.map(([v, l]) => `<option value="${v}" ${q.question_type === v ? 'selected' : ''}>${l}</option>`)
			.join('');

		const optionsHtml = (q.options || [])
			.map((o, oi) => renderOptionRow(o.option_text || o, oi))
			.join('');

		const showOptions = ['multiple_choice', 'checkbox'].includes(q.question_type);

		card.innerHTML = `
			<div class="fsm-question-card__header">
				<span class="fsm-drag-handle" style="cursor:grab;color:#aaa;">&#9776;</span>
				<input type="text" placeholder="Question text" value="${escAttr(q.question_text || '')}" class="regular-text fsm-q-text">
				<select class="fsm-q-type">${typeOptions}</select>
				<label style="white-space:nowrap;">
					<input type="checkbox" class="fsm-q-required" ${q.required != 0 ? 'checked' : ''}> Required
				</label>
				<button type="button" class="fsm-btn-remove-question" title="Remove question">&times;</button>
			</div>
			<div class="fsm-question-card__options" style="display:${showOptions ? 'block' : 'none'}">
				<div class="fsm-options-rows">${optionsHtml}</div>
				<button type="button" class="button fsm-add-option">+ Add Option</button>
			</div>
		`;

		card.querySelector('.fsm-q-text').addEventListener('input', e => { questions[idx].question_text = e.target.value; });
		card.querySelector('.fsm-q-required').addEventListener('change', e => { questions[idx].required = e.target.checked ? 1 : 0; });
		card.querySelector('.fsm-btn-remove-question').addEventListener('click', () => {
			questions.splice(idx, 1);
			renderQuestions();
		});

		const typeSelect = card.querySelector('.fsm-q-type');
		typeSelect.addEventListener('change', e => {
			questions[idx].question_type = e.target.value;
			const optDiv = card.querySelector('.fsm-question-card__options');
			optDiv.style.display = ['multiple_choice', 'checkbox'].includes(e.target.value) ? 'block' : 'none';
		});

		card.querySelector('.fsm-add-option').addEventListener('click', () => {
			if (!questions[idx].options) questions[idx].options = [];
			questions[idx].options.push('');
			renderQuestions();
		});

		bindOptionEvents(card, idx);
		container.appendChild(card);
	}

	function renderOptionRow(text, oi) {
		return `<div class="fsm-option-row" data-oi="${oi}">
			<input type="text" class="fsm-opt-text regular-text" placeholder="Option text" value="${escAttr(text)}">
			<button type="button" class="fsm-btn-remove-option" title="Remove option">&times;</button>
		</div>`;
	}

	function bindOptionEvents(card, idx) {
		card.querySelectorAll('.fsm-opt-text').forEach((inp, oi) => {
			inp.addEventListener('input', e => {
				if (!questions[idx].options) questions[idx].options = [];
				questions[idx].options[oi] = e.target.value;
			});
		});
		card.querySelectorAll('.fsm-btn-remove-option').forEach((btn, oi) => {
			btn.addEventListener('click', () => {
				if (questions[idx].options) questions[idx].options.splice(oi, 1);
				renderQuestions();
			});
		});
	}

	function escAttr(str) {
		return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

	// -------------------------------------------------------------------------
	// Add question button
	// -------------------------------------------------------------------------
	const addBtn = document.getElementById('fsm-add-question');
	if (addBtn) {
		addBtn.addEventListener('click', () => {
			questions.push({ question_text: '', question_type: 'multiple_choice', required: 1, options: [] });
			renderQuestions();
		});
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------
	const saveBtn    = document.getElementById('fsm-save');
	const saveStatus = document.getElementById('fsm-save-status');

	if (saveBtn) {
		saveBtn.addEventListener('click', async () => {
			saveBtn.disabled = true;
			saveStatus.textContent = i18n.saving;

			const payload = {
				title:              getField('fsm-title'),
				description:        getField('fsm-description'),
				status:             getField('fsm-status'),
				start_date:         getField('fsm-start-date').replace('T', ' ') || null,
				end_date:           getField('fsm-end-date').replace('T', ' ') || null,
				results_visibility: getField('fsm-visibility'),
				questions:          questions.map((q, i) => ({
					...q,
					sort_order: i,
					options: (q.options || []).filter(o => (o.option_text || o).trim()),
				})),
			};

			try {
				let result;
				if (surveyData && surveyData.id) {
					result = await apiFetch('surveys/' + surveyData.id, 'PUT', payload);
				} else {
					result = await apiFetch('surveys', 'POST', payload);
				}

				if (result.id) {
					surveyData = result;
					questions  = result.questions || [];
					renderQuestions();
					saveStatus.textContent = i18n.saved;
					if (!window.location.search.includes('survey_id')) {
						window.history.replaceState({}, '', window.location.href + (window.location.search ? '&' : '?') + 'page=fsm-survey-edit&survey_id=' + result.id);
					}
				} else {
					saveStatus.textContent = result.message || i18n.error;
				}
			} catch (e) {
				saveStatus.textContent = i18n.error;
			}

			saveBtn.disabled = false;
		});
	}

	// -------------------------------------------------------------------------
	// Delete survey buttons in list view
	// -------------------------------------------------------------------------
	document.querySelectorAll('.fsm-delete-survey').forEach(btn => {
		btn.addEventListener('click', async e => {
			e.preventDefault();
			if (!confirm(i18n.confirmDelete)) return;
			const id = btn.dataset.id;
			await apiFetch('surveys/' + id, 'DELETE');
			btn.closest('tr').remove();
		});
	});

	// -------------------------------------------------------------------------
	// Admin results view
	// -------------------------------------------------------------------------
	const resultsEl = document.getElementById('fsm-admin-results');
	if (resultsEl) {
		const surveyId = resultsEl.dataset.surveyId;
		const resultsNonce = resultsEl.dataset.nonce;
		const resultsRestUrl = resultsEl.dataset.restUrl;

		fetch(resultsRestUrl + 'surveys/' + surveyId + '/results', {
			headers: { 'X-WP-Nonce': resultsNonce },
		})
			.then(r => r.json())
			.then(data => {
				if (data.code) {
					resultsEl.innerHTML = '<p>' + (data.message || 'Error loading results.') + '</p>';
					return;
				}
				renderResultsInto(resultsEl, data);
			});
	}

	function renderResultsInto(container, data) {
		container.innerHTML = `<p><strong>Total responses: ${data.total_responses}</strong></p>`;
		data.questions.forEach(q => {
			const section = document.createElement('div');
			section.style.marginBottom = '24px';
			if (q.type === 'short_text') {
				section.innerHTML = `<h3>${escAttr(q.text)}</h3>
					<ul class="fsm-text-answers">${(q.answers || []).map(a => `<li>${escAttr(a)}</li>`).join('')}</ul>`;
			} else {
				const canvasId = 'fsm-chart-' + q.id;
				section.innerHTML = `<h3>${escAttr(q.text)}</h3><canvas id="${canvasId}" height="80"></canvas>`;
				container.appendChild(section);
				drawChart(canvasId, q);
				return;
			}
			container.appendChild(section);
		});
	}

	function drawChart(canvasId, q) {
		const canvas = document.getElementById(canvasId);
		if (!canvas || !window.Chart) return;
		new window.Chart(canvas, {
			type: 'bar',
			data: {
				labels: q.options.map(o => o.text),
				datasets: [{ data: q.options.map(o => o.count), backgroundColor: '#2271b1' }],
			},
			options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } },
		});
	}

	// Initial render
	renderQuestions();
})();

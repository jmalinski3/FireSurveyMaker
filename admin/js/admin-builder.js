/* global fsmAdmin */
(function () {
	'use strict';

	const builderEl = document.getElementById('fsm-builder');
	if (builderEl) {
		const initial = JSON.parse(builderEl.dataset.survey || 'null');

		window.fsmCreateBuilder({
			root:       'fsm-builder',
			listId:     'fsm-questions-list',
			addBtnId:   'fsm-add-question',
			saveBtnId:  'fsm-save',
			statusId:   'fsm-save-status',
			restUrl:    fsmAdmin.restUrl,
			nonce:      fsmAdmin.nonce,
			i18n:       fsmAdmin.i18n,
			initialSurvey: initial,
			fields: {
				title:       'fsm-title',
				description: 'fsm-description',
				status:      'fsm-status',
				visibility:  'fsm-visibility',
				startDate:   'fsm-start-date',
				endDate:     'fsm-end-date',
			},
			classes: {
				card:           'fsm-question-card',
				header:         'fsm-question-card__header',
				qText:          'fsm-q-text regular-text',
				qType:          'fsm-q-type',
				qRequired:      'fsm-q-required',
				removeQuestion: 'fsm-btn-remove-question',
				options:        'fsm-question-card__options',
				optionsRows:    'fsm-options-rows',
				addOption:      'fsm-add-option button',
				optionRow:      'fsm-option-row',
				optionText:     'fsm-opt-text regular-text',
				removeOption:   'fsm-btn-remove-option',
				dragHandleHtml: '<span class="fsm-drag-handle" style="cursor:grab;color:#aaa;">&#9776;</span>',
				requiredLabel:  '',
			},
			onSaved(result, { saveBtn }) {
				if (saveBtn) saveBtn.disabled = false;
				if (!window.location.search.includes('survey_id')) {
					const sep = window.location.search ? '&' : '?';
					window.history.replaceState({}, '', window.location.href + sep + 'page=fsm-survey-edit&survey_id=' + result.id);
				}
			},
		});
	}

	// -------------------------------------------------------------------------
	// Delete survey buttons in list view (admin-only)
	// -------------------------------------------------------------------------
	document.querySelectorAll('.fsm-delete-survey').forEach(btn => {
		btn.addEventListener('click', async e => {
			e.preventDefault();
			if (!confirm(fsmAdmin.i18n.confirmDelete)) return;
			const id = btn.dataset.id;
			await fetch(fsmAdmin.restUrl + 'surveys/' + id, {
				method:  'DELETE',
				headers: { 'X-WP-Nonce': fsmAdmin.nonce },
			});
			btn.closest('tr').remove();
		});
	});

	// -------------------------------------------------------------------------
	// Admin results view (admin-only)
	// -------------------------------------------------------------------------
	const resultsEl = document.getElementById('fsm-admin-results');
	if (resultsEl) {
		const surveyId = resultsEl.dataset.surveyId;
		const resultsNonce = resultsEl.dataset.nonce;
		const resultsRestUrl = resultsEl.dataset.restUrl;
		const escHtml = window.fsmEscHtml;

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

		function renderResultsInto(container, data) {
			container.innerHTML = `<p><strong>Total responses: ${data.total_responses}</strong></p>`;
			data.questions.forEach(q => {
				const section = document.createElement('div');
				section.style.marginBottom = '24px';
				if (q.type === 'short_text') {
					section.innerHTML = `<h3>${escHtml(q.text)}</h3>
						<ul class="fsm-text-answers">${(q.answers || []).map(a => `<li>${escHtml(a)}</li>`).join('')}</ul>`;
				} else {
					const canvasId = 'fsm-chart-' + q.id;
					section.innerHTML = `<h3>${escHtml(q.text)}</h3><canvas id="${canvasId}" height="80"></canvas>`;
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
	}
})();

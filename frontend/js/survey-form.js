/* global fsmFrontend */
(function () {
	'use strict';

	const cfg = window.fsmFrontend || {};

	document.querySelectorAll('.fsm-form').forEach(form => {
		form.addEventListener('submit', async function (e) {
			e.preventDefault();
			const surveyId = form.dataset.surveyId;
			const status   = form.querySelector('.fsm-form__status');
			const submit   = form.querySelector('[type="submit"]');

			submit.disabled   = true;
			status.textContent = cfg.i18n.submitting;

			const answers = {};
			form.querySelectorAll('.fsm-form__question').forEach(qEl => {
				const qid  = qEl.dataset.questionId;
				const type = qEl.dataset.type;

				if (type === 'multiple_choice') {
					const checked = qEl.querySelector('input[type="radio"]:checked');
					if (checked) answers[qid] = parseInt(checked.value, 10);
				} else if (type === 'checkbox') {
					const checked = Array.from(qEl.querySelectorAll('input[type="checkbox"]:checked'));
					if (checked.length) answers[qid] = checked.map(c => parseInt(c.value, 10));
				} else if (type === 'short_text') {
					const inp = qEl.querySelector('input[type="text"]');
					if (inp && inp.value.trim()) answers[qid] = inp.value.trim();
				}
			});

			try {
				const resp = await fetch(cfg.restUrl + 'surveys/' + surveyId + '/submit', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
					body: JSON.stringify({ answers }),
				});
				const data = await resp.json();

				if (resp.ok) {
					form.style.display = 'none';
					status.textContent = cfg.i18n.thankYou;
					const surveyEl = form.closest('.fsm-survey');
					const resultsUrl = surveyEl ? surveyEl.dataset.resultsUrl : null;
					if (resultsUrl) {
						const link = document.createElement('a');
						link.href = resultsUrl;
						link.textContent = ' View Results';
						link.className = 'fsm-btn fsm-btn--secondary';
						status.after(link);
					}
				} else if (resp.status === 409) {
					status.textContent = cfg.i18n.alreadyResponded;
				} else {
					status.textContent = data.message || cfg.i18n.error;
					submit.disabled = false;
				}
			} catch (err) {
				status.textContent = cfg.i18n.error;
				submit.disabled = false;
			}
		});
	});
})();

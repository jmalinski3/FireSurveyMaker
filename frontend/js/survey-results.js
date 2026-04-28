/* global fsmFrontend, Chart */
(function () {
	'use strict';

	const container = document.getElementById('fsm-results-container');
	if (!container) return;

	const surveyId  = container.dataset.surveyId;
	const nonce     = container.dataset.nonce;
	const restUrl   = container.dataset.restUrl;

	fetch(restUrl + 'surveys/' + surveyId + '/results', {
		headers: { 'X-WP-Nonce': nonce },
	})
		.then(r => r.json())
		.then(data => {
			if (data.code) {
				container.innerHTML = '<p class="fsm-notice fsm-notice--warning">' + escHtml(data.message || 'Unable to load results.') + '</p>';
				return;
			}
			renderResults(container, data);
		})
		.catch(() => {
			container.innerHTML = '<p class="fsm-notice fsm-notice--warning">Error loading results.</p>';
		});

	function renderResults(el, data) {
		el.innerHTML = '';

		const summary = document.createElement('p');
		summary.className = 'fsm-results__summary';
		summary.textContent = 'Total responses: ' + data.total_responses;
		el.appendChild(summary);

		data.questions.forEach(q => {
			const section = document.createElement('div');
			section.className = 'fsm-results__question';

			const heading = document.createElement('h3');
			heading.className = 'fsm-results__question-title';
			heading.textContent = q.text;
			section.appendChild(heading);

			if (q.type === 'short_text') {
				const list = document.createElement('ul');
				list.className = 'fsm-text-answers';
				(q.answers || []).forEach(a => {
					const li = document.createElement('li');
					li.textContent = a;
					list.appendChild(li);
				});
				if (!q.answers || !q.answers.length) {
					list.innerHTML = '<li><em>No responses yet.</em></li>';
				}
				section.appendChild(list);
			} else {
				const wrapper = document.createElement('div');
				wrapper.className = 'fsm-chart-wrapper';
				const canvas = document.createElement('canvas');
				canvas.setAttribute('aria-label', q.text + ' chart');
				wrapper.appendChild(canvas);
				section.appendChild(wrapper);

				el.appendChild(section);
				drawBarChart(canvas, q);
				return;
			}
			el.appendChild(section);
		});
	}

	function drawBarChart(canvas, q) {
		if (!window.Chart) return;
		new Chart(canvas, {
			type: 'bar',
			data: {
				labels: q.options.map(o => o.text + ' (' + o.pct + '%)'),
				datasets: [{
					data: q.options.map(o => o.count),
					backgroundColor: '#2563eb',
					borderRadius: 4,
				}],
			},
			options: {
				indexAxis: 'y',
				plugins: { legend: { display: false } },
				scales: {
					x: { beginAtZero: true, ticks: { stepSize: 1 } },
				},
				responsive: true,
				maintainAspectRatio: true,
			},
		});
	}

	function escHtml(str) {
		const d = document.createElement('div');
		d.appendChild(document.createTextNode(str));
		return d.innerHTML;
	}
})();

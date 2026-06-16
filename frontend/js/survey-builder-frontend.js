/* global fsmBuilder */
(function () {
	'use strict';

	const builderEl = document.getElementById('fsm-frontend-builder');
	if (!builderEl) return;

	const cfg = window.fsmBuilder || {};
	const siteUrl = cfg.siteUrl || '/';

	window.fsmCreateBuilder({
		root:          'fsm-frontend-builder',
		listId:        'fsm-fe-questions',
		addBtnId:      'fsm-fe-add-question',
		saveBtnId:     'fsm-fe-save',
		statusId:      'fsm-fe-save-status',
		restUrl:       cfg.restUrl || '',
		nonce:         cfg.nonce   || '',
		i18n:          cfg.i18n    || {},
		initialSurvey: null,
		requireTitle:  true,
		fields: {
			title:       'fsm-fe-title',
			description: 'fsm-fe-description',
			status:      'fsm-fe-status',
			visibility:  'fsm-fe-visibility',
			startDate:   'fsm-fe-start',
			endDate:     'fsm-fe-end',
		},
		classes: {
			card:           'fsm-fe-question-card',
			header:         'fsm-fe-question-card__header',
			qText:          'fsm-fe-q-text fsm-input',
			qType:          'fsm-fe-q-type fsm-input',
			qRequired:      'fsm-fe-q-required',
			removeQuestion: 'fsm-fe-remove-q fsm-btn-icon',
			options:        'fsm-fe-options',
			optionsRows:    'fsm-fe-options-rows',
			addOption:      'fsm-fe-add-opt fsm-btn fsm-btn--ghost',
			optionRow:      'fsm-fe-option-row',
			optionText:     'fsm-fe-opt-text fsm-input',
			removeOption:   'fsm-fe-remove-opt fsm-btn-icon',
			dragHandleHtml: '',
			requiredLabel:  'fsm-fe-required-label',
		},
		onSaved(result) {
			if (result.slug) {
				setTimeout(() => {
					window.location.href = siteUrl + 'surveys/' + result.slug + '/';
				}, 1200);
			}
		},
	});
})();

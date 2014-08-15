/**
 * @file js/controllers/grid/users/reviewer/form/ReviewerFormFooterHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerFormFooterHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Handler for the reviewer form footer
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $footerElement the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			ReviewerFormFooterHandler = function($footerElement, options) {

		this.parent($footerElement, options);

		$('input[id^=\'responseDueDate\']').datepicker({dateFormat: 'yy-mm-dd'});
		$('input[id^=\'reviewDueDate\']').datepicker({dateFormat: 'yy-mm-dd'});

		$('#filesAccordion').accordion({
			collapsible: true,
			active: false,
			// WARNING: The following two options are deprecated in JQueryUI.
			autoHeight: false,
			clearStyle: true
		});
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.form.
					ReviewerFormFooterHandler,
			$.pkp.classes.Handler);

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

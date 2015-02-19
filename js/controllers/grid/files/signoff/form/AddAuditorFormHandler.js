/**
 * @defgroup js_controllers_grid_files_signoff_form
 */
/**
 * @file js/controllers/grid/files/signoff/AddAuditorFormHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AddAuditorFormHandler
 * @ingroup js_controllers_grid_files_signoff_form
 *
 * @brief Handle the "add auditor" form.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.files = $.pkp.controllers.grid.files ||
			{ signoff: { form: { } } };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.files.signoff.form.AddAuditorFormHandler =
			function($form, options) {

		this.parent($form, options);

		// Set response due date to one week in the future
		// FIXME: May want to make a press setting
		var currentTime = new Date(),
				month = currentTime.getMonth() + 1,
				day = currentTime.getDate() + 7,
				year = currentTime.getFullYear();

		$('input[id^="responseDueDate"]').datepicker(
				{ dateFormat: 'mm-dd-yy', minDate: '0', autoSize: true});
		$('input[id^="responseDueDate"]').datepicker(
				'setDate', month + '-' + day + '-' + year);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.files.signoff.form.
					AddAuditorFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

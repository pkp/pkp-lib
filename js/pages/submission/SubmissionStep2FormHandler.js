/**
 * @defgroup js_pages_submission
 */

/**
 * @file js/pages/submission/SubmissionStep2FormHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionStep2FormHandler
 * @ingroup js_pages_submission
 *
 * @brief Handle the submission step 2 form.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.pages.submission.SubmissionStep2FormHandler =
			function($form, options) {

		this.parent($form, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.submission.SubmissionStep2FormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	/**
	 * Public callback used to prevent buttons in the submission files
	 * list panel from submitting the form.
	 *
	 * This is a temporary workaround because buttons in the list panel
	 * can cause the form to submit without firing the `submit` event
	 * in jQuery.
	 *
	 * This method is bound to the form component's onsubmit attribute
	 * to ensure that all actions which trigger the form submission
	 * are checked before it is submitted.
	 *
	 * There are still some cases that this doesn't catch and those
	 * are routed through this.submitForm() below.
	 *
	 * @param {Object} event
	 * @return {boolean}
	 */
	$.pkp.pages.submission.SubmissionStep2FormHandler.prototype.checkSubmit =
			function(event) {
		event.preventDefault();
		event.stopPropagation();
		return false;
	};


	/**
	 * Check if the form submission was made by clicking on one of the
	 * form submit buttons before submitting it.
	 *
	 * This is a workaround for embedding the submission files list panel
	 * into the form. See docs at this.checkSubmit()
	 *
	 * @param {Object} validator The validator plug-in.
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 */
	$.pkp.pages.submission.SubmissionStep2FormHandler.prototype.submitForm =
			function(validator, formElement) {
		var submitButton = formElement.querySelector('[id^="submitFormButton"]'),
				cancelButton = formElement.querySelector('[id^="cancelFormButton"]');

		if (validator.submitButton.id == submitButton.id ||
				validator.submitButton.id == cancelButton.id) {
			this.parent('submitForm', validator, formElement);
		} else {
			this.hideSpinner();
		}
	};


}(jQuery));

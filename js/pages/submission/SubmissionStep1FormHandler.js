/**
 * @defgroup js_pages_submission
 */
// Create the namespace.
jQuery.pkp.pages.submission = jQuery.pkp.pages.submission || { };

/**
 * @file js/pages/submission/SubmissionStep1FormHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionStep1FormHandler
 * @ingroup js_pages_submission
 *
 * @brief Handle the submission step 1 form.
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
	$.pkp.pages.submission.SubmissionStep1FormHandler =
			function($form, options) {

		this.parent($form, options);

		this.showSectionPolicy(null);
		$('#sectionId').change(this.callbackWrapper(this.showSectionPolicy));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.submission.SubmissionStep1FormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Public methods.
	//
	/**
	 * Show the correct section policy whenever the selected section is changed
	 * @param {Event} event The triggering event.
	 */
	$.pkp.pages.submission.SubmissionStep1FormHandler.
			prototype.showSectionPolicy = function(event) {
		$('.section-policy').hide();
		$('.section-policy.section-id-' + $('#sectionId').val()).fadeIn();
	};


}(jQuery));

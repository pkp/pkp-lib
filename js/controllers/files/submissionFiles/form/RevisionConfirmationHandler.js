/**
 * @file js/controllers/RevisionConfirmationHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RevisionConfirmationHandler
 * @ingroup js_controllers_files_submissionFiles_form
 *
 * @brief Revision confirmation form handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.FormHandler
	 *
	 * @param {jQuery} $form The wrapped HTML form element.
	 * @param {Object} options Form validation options.
	 */
	$.pkp.controllers.files.submissionFiles.form.RevisionConfirmationHandler =
			function($form, options) {

		this.parent($form, options);

		// Save the delete url.
		this.deleteUrl_ = options.deleteUrl;

		// Show the possible revision message.
		$form.find('#possibleRevision').show('slide');

		// Subscribe wizard events.
		this.bind('wizardAdvanceRequested', this.wizardAdvanceRequested);
		this.bind('wizardCancelRequested', this.wizardCancelRequested);

		// Allow wizard advance.
		$form.trigger('enableAdvance');
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.files.submissionFiles.form.RevisionConfirmationHandler,
			$.pkp.controllers.FormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called when a cancel event occurs.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.files.submissionFiles.form.RevisionConfirmationHandler.
			prototype.deleteUrl_ = '';


	//
	// Public methods
	//
	/**
	 * Handle the "advance requested" event triggered by the enclosing wizard.
	 *
	 * @param {HTMLElement} wizardElement The calling wizard.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.files.submissionFiles.form.RevisionConfirmationHandler.
			prototype.wizardAdvanceRequested = function(wizardElement, event) {

		var $uploadForm = this.getHtmlElement();
		var revisedFileId = $uploadForm.find('#revisedFileId').val().parseInt();
		if (revisedFileId > 0) {
			// Confirm the revision.
			$uploadForm.submit();
		} /* else {
			// Do nothing, i.e. advance directly to the
			// next step.
		} */
	};


	/**
	 * Handle the "cancel requested" event triggered by the enclosing wizard.
	 *
	 * @param {HTMLElement} wizardElement The calling wizard.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.files.submissionFiles.form.RevisionConfirmationHandler.
			prototype.wizardCancelRequested = function(wizardElement, event) {

		// If the user presses cancel after uploading a file then delete the file.
		if (this.deleteUrl_ !== '') {
			$.post(this.deleteUrl_);
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

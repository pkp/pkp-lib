/**
 * @file js/controllers/wizard/fileUpload/form/RevisionConfirmationHandler.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RevisionConfirmationHandler
 * @ingroup js_controllers_wizard_fileUpload_form
 *
 * @brief Revision confirmation tab handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQuery} $form The wrapped HTML form element.
	 * @param {Object} options Form validation options.
	 */
	$.pkp.controllers.wizard.fileUpload.form.RevisionConfirmationHandler =
			function($form, options) {

		this.parent($form, options);

		// Show the possible revision message.
		$form.find('#possibleRevision').show('slide');

		// Subscribe to wizard events.
		this.bind('wizardAdvanceRequested', this.wizardAdvanceRequested);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.wizard.fileUpload.form.RevisionConfirmationHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Public methods
	//
	/**
	 * Handle the "advance requested" event triggered by the enclosing wizard.
	 *
	 * @param {HTMLElement} wizardElement The calling wizard.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.wizard.fileUpload.form.RevisionConfirmationHandler.
			prototype.wizardAdvanceRequested = function(wizardElement, event) {

		var $confirmationForm = this.getHtmlElement();
		var revisedFileId = parseInt(
				$confirmationForm.find('#revisedFileId').val(), 10);
		if (revisedFileId > 0) {
			// Submit the form.
			$confirmationForm.submit();
			event.preventDefault();
		}
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.wizard.fileUpload.form.RevisionConfirmationHandler.
			prototype.handleResponse = function(formElement, jsonData) {

		if (jsonData.status === true) {
			// Trigger the file uploaded event.
			this.trigger('fileUploaded', jsonData.uploadedFile);
		}

		this.parent('handleResponse', formElement, jsonData);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

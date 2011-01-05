/**
 * @defgroup js_controllers_files_submissionFiles
 */
// Create the submissionFiles namespace
jQuery.pkp.controllers.files = jQuery.pkp.controllers.files ||
			{ submissionFiles: { } };

/**
 * @file js/controllers/files/submissionFiles/FileUploadWizardHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadWizardHandler
 * @ingroup js_controllers_files_submissionFiles
 *
 * @brief File uploader wizard handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.WizardHandler
	 *
	 * @param {jQuery} $wizard The wrapped HTML form element.
	 * @param {Object} options Wizard options.
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler =
			function($wizard, options) {

		this.parent($wizard, options);

		// Save the delete url.
		this.deleteUrl_ = options.deleteUrl;

		// Bind events of the nested widgets.
		this.bind('fileUploaded', this.handleFileUploaded);
		this.bind('fileUploadComplete', this.handleFileUploadComplete);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler,
			$.pkp.controllers.WizardHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called when a cancel event occurs.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler.
			prototype.deleteUrl_ = '';


	/**
	 * Information about the uploaded file (once there is one).
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler.
			prototype.uploadedFile_ = null;


	//
	// Public methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler.
			prototype.wizardCancelRequested = function(wizardElement, event) {

		// If the user presses cancel after uploading a file then delete the file.
		if (this.uploadedFile_) {
			$.post(this.deleteUrl_, this.uploadedFile_,
					$.pkp.classes.Helper.curry(this.wizardCancelSuccess, this,
							wizardElement, event), 'json');

			// Do not cancel immediately.
			event.preventDefault();
		}

		this.parent('wizardCancelRequested', wizardElement, event);
	};


	/**
	 * Callback triggered when the deletion of a file after clicking
	 * the cancel button was successful.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The original event.
	 * @param {Object} jsonData The JSON data returned by the server on
	 *  file deletion.
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler.
			prototype.wizardCancelSuccess = function(wizardElement, event, jsonData) {

		if (jsonData.status === true) {
			// Delete the uploaded file info and return to the wizard cancel method.
			this.uploadedFile_ = null;
			this.wizardCancel(wizardElement, event);
		} else {
			alert(jsonData.content);
		}
	};


	/**
	 * Handle the "file uploaded" event triggered by the
	 * file upload/revision confirmation forms whenever the
	 * uploaded file changed.
	 *
	 * @param {$.pkp.controllers.FormHandler} callingForm The form
	 *  that triggered the event.
	 * @param {Event} event The upload event.
	 * @param {Object} uploadedFile Information about the uploaded
	 *  file.
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler.
			prototype.handleFileUploaded = function(callingForm, event, uploadedFile) {

		// Save the uploaded file information.
		this.uploadedFile_ = uploadedFile;
	};


	/**
	 * Handle the "file upload complete" event triggered by the
	 * file upload/revision confirmation forms when the file
	 * upload step is completed.
	 *
	 * @param {$.pkp.controllers.FormHandler} callingForm The form
	 *  that triggered the event.
	 * @param {Event} event The upload complete event.
	 */
	$.pkp.controllers.files.submissionFiles.FileUploadWizardHandler.
			prototype.handleFileUploadComplete = function(callingForm, event) {

		// Advance the wizard to the meta-data step.
		this.getHtmlElement().trigger('wizardAdvance');
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

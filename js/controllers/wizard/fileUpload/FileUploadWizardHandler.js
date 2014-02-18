/**
 * @defgroup controllers_wizard_fileUpload
 */
/**
 * @file js/controllers/wizard/fileUpload/FileUploadWizardHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadWizardHandler
 * @ingroup controllers_wizard_fileUpload
 *
 * @brief File uploader wizard handler.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.wizard.fileUpload =
			$.pkp.controllers.wizard.fileUpload || { };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.wizard.WizardHandler
	 *
	 * @param {jQueryObject} $wizard The wrapped HTML form element.
	 * @param {Object} options Wizard options.
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler =
			function($wizard, options) {

		this.parent($wizard, options);

		// Save action urls.
		this.deleteUrl_ = options.deleteUrl;
		this.metadataUrl_ = options.metadataUrl;
		this.finishUrl_ = options.finishUrl;

		// Bind events of the nested upload forms.
		this.bind('fileUploaded', this.handleFileUploaded);

		// Initially disable the continue button.
		this.getContinueButton().button('disable');
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler,
			$.pkp.controllers.wizard.WizardHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called when a cancel event occurs.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.deleteUrl_ = '';


	/**
	 * The URL from which to load the meta-data form.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.metadataUrl_ = '';


	/**
	 * The URL from which to load the finish form.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.finishUrl_ = '';


	/**
	 * Information about the uploaded file (once there is one).
	 * @private
	 * @type {{fileId: number}?}
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.uploadedFile_ = null;


	//
	// Public methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.tabsSelect = function(tabsElement, event, ui) {

		// The last two tabs require a file to be uploaded.
		if (ui.index > 0) {
			if (!this.uploadedFile_) {
				throw new Error('Uploaded file missing!');
			}

			// Set the correct URLs.
			var $wizard = this.getHtmlElement(), newUrl = '';
			switch (ui.index) {
				case 1:
					newUrl = this.metadataUrl_;
					break;

				case 2:
					newUrl = this.finishUrl_;
					break;

				default:
					throw new Error('Unsupported tab index.');
			}

			newUrl = newUrl + '&fileId=' + this.uploadedFile_.fileId;
			$wizard.tabs('url', ui.index, newUrl);
		}

		return /** @type {boolean} */ (
				this.parent('tabsSelect', tabsElement, event, ui));
	};


	/**
	 * Overridden version of WizardHandler's wizardAdvance handler.
	 * This version allows a user to return to all tabs but the very
	 * first one (the actual file upload).
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.wizardAdvance = function(wizardElement, event) {

		// The wizard can only be advanced one step at a time.
		// The step cannot be greater than the number of wizard
		// tabs and not less than 1.
		var currentStep = this.getCurrentStep(),
				lastStep = this.getNumberOfSteps() - 1,
				targetStep = currentStep + 1,
				$wizard = this.getHtmlElement(),
				$continueButton;

		// Do not advance beyond the last step.
		if (targetStep > lastStep) {
			throw new Error('Trying to set an invalid wizard step!');
		}

		// Enable the target step.
		$wizard.tabs('enable', targetStep);

		// Advance to the target step.
		$wizard.tabs('select', targetStep);

		// Disable the previous step if it is the first one.
		if (currentStep === 0) {
			$wizard.tabs('disable', currentStep);
		}

		// If this is the last step then change the text on the
		// continue button to finish.
		if (targetStep === lastStep) {
			$continueButton = this.getContinueButton();
			$continueButton.button('option', 'label',
					/** @type {string} */ (this.getFinishButtonText()));
			$continueButton.button('enable');
		}
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.tabsLoad = function(tabsElement, event, ui) {

		var $wizard = this.getHtmlElement(),
				$newFileButton,
				$progressIndicator = this.getProgressIndicator();

		// In the last step: Bind click a event to the button that re-starts
		// the upload process.
		if (ui.index === 2) {
			$newFileButton = $('#newFile', $wizard);
			if ($newFileButton.length !== 1) {
				throw new Error('Did not find "new file" button!');
			}
			$newFileButton.button();
			$newFileButton.bind('click', this.callbackWrapper(this.startWizard));
		}

		$progressIndicator.hide();

		return /** @type {boolean} */ (
				this.parent('tabsLoad', tabsElement, event, ui));
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.formValid = function(formElement, event) {

		// Ignore form validation events for the upload form.
		if (this.getCurrentStep() === 0 &&
				this.getHtmlElement().find('#uploadConfirmationForm').length === 0 &&
				!this.uploadedFile_) {
			return;
		}

		this.parent('formValid', formElement, event);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.wizardCancelRequested = function(wizardElement, event) {

		if (this.parent('wizardCancelRequested', wizardElement, event)) {
			// If the user presses cancel after uploading a file then delete the file.
			if (this.uploadedFile_) {
				$.post(this.deleteUrl_, this.uploadedFile_,
						$.pkp.classes.Helper.curry(this.wizardCancelSuccess, this,
								wizardElement, event), 'json');

				// The uploaded file is being dealt with; reset.
				this.uploadedFile_ = null;

				// Do not cancel immediately.
				event.preventDefault();
			} else {
				return true;
			}
			return false;
		} else {
			// Stop the cancel request.
			return false;
		}
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
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.wizardCancelSuccess = function(wizardElement, event, jsonData) {

		var processedJsonData = this.handleJson(jsonData);
		if (processedJsonData !== false) {
			// Cancel the wizard.
			this.trigger('wizardCancel');
		}
	};


	/**
	 * Handle the "file uploaded" event triggered by the
	 * file upload/revision confirmation forms whenever the
	 * uploaded file changed.
	 *
	 * @param {$.pkp.controllers.form.AjaxFormHandler} callingForm The form
	 *  that triggered the event.
	 * @param {Event} event The upload event.
	 * @param {{fileId: number}} uploadedFile Information about the uploaded
	 *  file.
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.handleFileUploaded = function(callingForm, event, uploadedFile) {

		// Save the uploaded file information.
		this.uploadedFile_ = uploadedFile;
	};


	//
	// Protected methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.wizard.fileUpload.FileUploadWizardHandler.
			prototype.startWizard = function() {

		// Reset the uploaded file.
		this.uploadedFile_ = null;

		this.parent('startWizard');
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

/**
 * @defgroup js_controllers_wizard_fileUpload_form
 */
/**
 * @file js/controllers/wizard/fileUpload/form/FileUploadFormHandler.js
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadFormHandler
 * @ingroup js_controllers_wizard_fileUpload_form
 *
 * @brief File upload tab handler.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.wizard.fileUpload.form =
			$.pkp.controllers.wizard.fileUpload.form || { };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form The wrapped HTML form element.
	 * @param {Object} options Form validation options.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler =
			function($form, options) {

		this.parent($form, options);

		// Set internal state properties.
		this.hasFileSelector_ = options.hasFileSelector;
		this.hasGenreSelector_ = options.hasGenreSelector;

		if (options.presetRevisedFileId) {
			this.presetRevisedFileId_ = options.presetRevisedFileId;
		}
		this.fileGenres_ = options.fileGenres;

		// Attach the uploader handler to the uploader HTML element.
		this.attachUploader_(options.$uploader, options.uploaderOptions);

		this.uploaderSetup(options.$uploader);

		// When a user selects a submission to revise then the
		// the file genre chooser must be disabled.
		var $revisedFileId = $form.find('#revisedFileId'),
				$genreId;

		$revisedFileId.change(this.callbackWrapper(this.revisedFileChange));
		if (this.hasGenreSelector_) {
			$genreId = $form.find('#genreId');
			$genreId.change(this.callbackWrapper(this.genreChange));
			// initially, hide the upload botton on the form.
			$form.find('.plupload_button.plupload_start').hide();
		}
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * Whether the file upload form has a file selector.
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler
			.hasFileSelector_ = false;


	/**
	 * Whether the file upload form has a genre selector.
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler
			.hasGenreSelector_ = false;


	/**
	 * A preset revised file id (if any).
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler
			.presetRevisedFileId_ = null;


	/**
	 * All currently available file genres.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler
			.fileGenres_ = null;


	//
	// Public methods
	//
	/**
	 * The setup callback of the uploader.
	 * @param {jQueryObject} $uploader Element that contains the plupload object.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			uploaderSetup = function($uploader) {

		var pluploader = $uploader.plupload('getUploader');
		// Subscribe to uploader events.
		pluploader.bind('BeforeUpload',
				this.callbackWrapper(this.prepareFileUploadRequest));
		pluploader.bind('FileUploaded',
				this.callbackWrapper(this.handleUploadResponse));
	};


	/**
	 * Prepare the request parameters for the file upload request.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			prepareFileUploadRequest = function(caller, pluploader) {

		var $uploadForm = this.getHtmlElement(),
				multipartParams = { },
				// Add the uploader user group id.
				$uploaderUserGroupId = $uploadForm.find('#uploaderUserGroupId'),
				$revisedFileId, $genreId;

		$uploaderUserGroupId.attr('disabled', 'disabled');
		multipartParams.uploaderUserGroupId = $uploaderUserGroupId.val();

		// Add the revised file to the upload message.
		if (this.hasFileSelector_) {
			$revisedFileId = $uploadForm.find('#revisedFileId');
			$revisedFileId.attr('disabled', 'disabled');
			multipartParams.revisedFileId = $revisedFileId.val();
		} else {
			if (this.presetRevisedFileId_ !== null) {
				multipartParams.revisedFileId = this.presetRevisedFileId_;
			} else {
				multipartParams.revisedFileId = 0;
			}
		}

		// Add the file genre to the upload message.
		if (this.hasGenreSelector_) {
			$genreId = $uploadForm.find('#genreId');
			$genreId.attr('disabled', 'disabled');
			multipartParams.genreId = $genreId.val();
		} else {
			multipartParams.genreId = '';
		}

		// Add the upload message parameters to the uploader.
		pluploader.settings.multipart_params = multipartParams;
	};


	/**
	 * Handle the response of a "file upload" request.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 * @param {Object} file The data of the uploaded file.
	 * @param {{response: string}} ret The serialized JSON response.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			handleUploadResponse = function(caller, pluploader, file, ret) {

		// Handle the server's JSON response.
		var jsonData = this.handleJson($.parseJSON(ret.response)),
				$uploadForm = this.getHtmlElement();

		if (jsonData !== false) {
			// Trigger the file uploaded event.
			this.trigger('fileUploaded', jsonData.uploadedFile);

			if (jsonData.content === '') {
				// remove the 'add files' button to prevent repeated uploads.
				// Note: we must disable the type="file" element or else Chrome
				// will let a user click through the disabled button and add
				// new files.
				$uploadForm.find(':file').attr('disabled', 'disabled');
				$uploadForm.find('a.plupload_add').button('disable');
				// Trigger formValid to enable to continue button
				this.trigger('formValid');
			} else {
				// Display the revision confirmation form.
				this.getHtmlElement().replaceWith(jsonData.content);
			}
		}
	};


	/**
	 * Internal callback to handle form submission.
	 *
	 * @param {Object} validator The validator plug-in.
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			submitForm = function(validator, formElement) {

		// There is no form to submit (file already uploaded).
		// Trigger event to signal that user requests the form to be submitted.
		this.trigger('formSubmitted');
	};


	/**
	 * Handle the "change" event of the revised file selector.
	 * @param {HTMLElement} revisedFileElement The original context in
	 *  which the event was triggered.
	 * @param {Event} event The change event.
	 * @return {boolean} Event handling status.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			revisedFileChange = function(revisedFileElement, event) {

		var $uploadForm = this.getHtmlElement(),
				$revisedFileId = $uploadForm.find('#revisedFileId'),
				$genreId = $uploadForm.find('#genreId');

		if ($revisedFileId.val() === '') {
			// New file...
			$genreId.removeAttr('disabled');
		} else {
			// Revision...
			$genreId.val(this.fileGenres_[$revisedFileId.val()]);
			$genreId.attr('disabled', 'disabled');
			$uploadForm.find('.plupload_button.plupload_start').show();
		}
		return false;
	};


	/**
	 * Handle the "change" event of the genre selector, if it exists.
	 * @param {HTMLElement} genreElement The original context in
	 *  which the event was triggered.
	 * @param {Event} event The change event.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			genreChange = function(genreElement, event) {

		var $uploadForm = this.getHtmlElement(),
				$genreId = $uploadForm.find('#genreId');
		if ($genreId.val() === '') {
			// genre is empty
			$uploadForm.find('.plupload_button.plupload_start').hide();
		} else {
			$uploadForm.find('.plupload_button.plupload_start').show();
		}
	};


	//
	// Private methods
	//
	/**
	 * Attach the uploader handler.
	 * @private
	 * @param {jQueryObject} $uploader The wrapped HTML uploader element.
	 * @param {Object} options Uploader options.
	 */
	$.pkp.controllers.wizard.fileUpload.form.FileUploadFormHandler.prototype.
			attachUploader_ = function($uploader, options) {

		// Attach the uploader handler to the uploader div.
		$uploader.pkpHandler('$.pkp.controllers.UploaderHandler', options);
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

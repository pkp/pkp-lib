/**
 * @defgroup js_controllers_files_submissionFiles_form
 */
// Create the submissionFiles form namespace
jQuery.pkp.controllers.files.submissionFiles.form =
			jQuery.pkp.controllers.files.submissionFiles.form || { };

/**
 * @file js/controllers/FileUploadFormHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadFormHandler
 * @ingroup js_controllers_files_submissionFiles_form
 *
 * @brief File upload tab handler.
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
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler =
			function($form, options) {

		this.parent($form, options);

		// Set internal state properties.
		this.hasFileSelector_ = options.hasFileSelector;
		this.hasGenreSelector_ = options.hasGenreSelector;
		if (options.presetRevisedFileId) {
			this.presetRevisedFileId_ = options.presetRevisedFileId;
		}
		this.monographFileGenres_ = options.monographFileGenres;

		// Attach the uploader handler to the uploader HTML element.
		options.uploaderOptions.setup = this.callbackWrapper(this.uploaderSetup);
		this.attachUploader_(options.$uploader, options.uploaderOptions);

		// When a user selects a submission to revise then the
		// the file genre chooser must be disabled.
		var $revisedFileId = $form.find('#revisedFileId');
		$revisedFileId.change(this.callbackWrapper(this.revisedFileChange));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler,
			$.pkp.controllers.FormHandler);


	//
	// Private properties
	//
	/**
	 * Whether the file upload form has a file selector.
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler
			.hasFileSelector_ = false;


	/**
	 * Whether the file upload form has a genre selector.
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler
			.hasGenreSelector_ = false;


	/**
	 * A preset revised file id (if any).
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler
			.presetRevisedFileId_ = null;


	/**
	 * All currently available monograph file genres.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler
			.monographFileGenres_ = null;


	//
	// Public methods
	//
	/**
	 * The setup callback of the uploader.
	 * @param {Object} uploaderOptions The uploader options object
	 *  from which this callback is being called.
	 * @param {Object} pluploader The pluploader object.
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler.prototype.
			uploaderSetup = function(uploaderOptions, pluploader) {

		// Subscribe to uploader events.
		pluploader.bind('FilesAdded',
				this.callbackWrapper(this.uploaderFilesAdded));
		pluploader.bind('QueueChanged',
				this.callbackWrapper(this.uploaderQueueChanged));
		pluploader.bind('FileUploaded',
				this.callbackWrapper(this.uploaderFileUploaded));
	};


	/**
	 * Handle the "files added" event of the uploader.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 * @param {Object} file The data of the uploaded file.
	 *
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler.prototype.
			uploaderFilesAdded = function(caller, pluploader, file) {

		// Prevent > 1 files from being added.
		if (pluploader.files.length > 1) {
			pluploader.splice(0, 1);
			pluploader.refresh();
		}
	};


	/**
	 * Handle the "queue changed" event of the uploader.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler.prototype.
			uploaderQueueChanged = function(caller, pluploader) {

		var $uploadForm = this.getHtmlElement();
		var multipartParams = { };

		// Add the revised file to the upload message.
		if (this.hasFileSelector_) {
			var $revisedFileId = $uploadForm.find('#revisedFileId');
			$revisedFileId.attr('disabled', 'disabled');
			multipartParams.revisedFileId = $revisedFileId.val();
		} else {
			multipartParams.revisedFileId = this.presetRevisedFileId_;
		}

		// Add the file genre to the upload message.
		if (this.hasGenreSelector_) {
			var $genreId = $uploadForm.find('#genreId');
			$genreId.attr('disabled', 'disabled');
			multipartParams.genreId = $genreId.val();
		} else {
			multipartParams.genreId = '';
		}

		// Add the upload message parameters to the uploader.
		pluploader.settings.multipart_params = multipartParams;
	};


	/**
	 * Handle the "file uploaded" event of the uploader.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 * @param {Object} file The data of the uploaded file.
	 * @param {string} ret The serialized JSON response.
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler.prototype.
			uploaderFileUploaded = function(caller, pluploader, file, ret) {

		// Handle the server's JSON response.
		var jsonData = this.handleJson($.parseJSON(ret.response));
		if (jsonData !== false) {
			// Trigger the file uploaded event.
			this.trigger('fileUploaded', jsonData.uploadedFile);

			if (jsonData.content === '') {
				// Send the form submission event.
				this.trigger('formSubmitted');
			} else {
				// Display the revision confirmation form.
				this.getHtmlElement().replaceWith(jsonData.content);
			}
		}
	};


	/**
	 * Handle the "change" event of the revised file selector.
	 * @param {HTMLElement} revisedFileElement The original context in
	 *  which the event was triggered.
	 * @param {Event} event The change event.
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler.prototype.
			revisedFileChange = function(revisedFileElement, event) {

		var $uploadForm = this.getHtmlElement();
		var $revisedFileId = $uploadForm.find('#revisedFileId');
		var $genreId = $uploadForm.find('#genreId');
		if ($revisedFileId.val() === 0) {
			// New file...
			$genreId.attr('disabled', '');
		} else {
			// Revision...
			$genreId.val(this.monographFileGenres_[$revisedFileId.val()]);
			$genreId.attr('disabled', 'disabled');
		}
	};


	//
	// Private methods
	//
	/**
	 * Attach the uploader handler.
	 * @private
	 * @param {jQuery} $uploader The wrapped HTML uploader element.
	 * @param {Object} options Uploader options.
	 */
	$.pkp.controllers.files.submissionFiles.form.FileUploadFormHandler.prototype.
			attachUploader_ = function($uploader, options) {

		// Attach the uploader handler to the uploader div.
		$uploader.pkpHandler('$.pkp.controllers.UploaderHandler', options);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

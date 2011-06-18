/**
 * @file js/controllers/form/FileUploadFormHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FileUploadFormHandler
 * @ingroup js_controllers_form
 *
 * @brief File upload form handler.
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
	$.pkp.controllers.form.FileUploadFormHandler =
			function($form, options) {

		this.parent($form, options);

		// Attach the uploader handler to the uploader HTML element.
		options.uploaderOptions.setup = this.callbackWrapper(this.uploaderSetup);
		this.attachUploader_(options.$uploader, options.uploaderOptions);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.form.FileUploadFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Public methods
	//
	/**
	 * The setup callback of the uploader.
	 * @param {Object} uploaderOptions The uploader options object
	 *  from which this callback is being called.
	 * @param {Object} pluploader The pluploader object.
	 */
	$.pkp.controllers.form.FileUploadFormHandler.prototype.
			uploaderSetup = function(uploaderOptions, pluploader) {

		// Subscribe to uploader events.
		pluploader.bind('FilesAdded',
				this.callbackWrapper(this.limitQueueSize));
		pluploader.bind('FileUploaded',
				this.callbackWrapper(this.handleUploadResponse));
	};


	/**
	 * Limit the queue size of the uploader to one file only.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 * @param {Object} file The data of the uploaded file.
	 *
	 */
	$.pkp.controllers.form.FileUploadFormHandler.prototype.
			limitQueueSize = function(caller, pluploader, file) {

		// Prevent > 1 files from being added.
		if (pluploader.files.length > 1) {
			pluploader.splice(0, 1);
			pluploader.refresh();
		}
	};


	/**
	 * Handle the response of a "file upload" request.
	 * @param {Object} caller The original context in which the callback was called.
	 * @param {Object} pluploader The pluploader object.
	 * @param {Object} file The data of the uploaded file.
	 * @param {string} ret The serialized JSON response.
	 */
	$.pkp.controllers.form.FileUploadFormHandler.prototype.
			handleUploadResponse = function(caller, pluploader, file, ret) {

		// Handle the server's JSON response.
		var jsonData = this.handleJson($.parseJSON(ret.response));
		if (jsonData !== false) {
			// Trigger the file uploaded event.
			this.trigger('fileUploaded', jsonData.uploadedFile);

			if (jsonData.content === '') {
				// Successful upload to temporary file; save to main form.
				var $uploadForm = this.getHtmlElement();
				var $temporaryFileId = $uploadForm.find('#temporaryFileId');
				$temporaryFileId.val(jsonData.temporaryFileId);
			} else {
				// Display the revision confirmation form.
				this.getHtmlElement().replaceWith(jsonData.content);
			}
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
	$.pkp.controllers.form.FileUploadFormHandler.prototype.
			attachUploader_ = function($uploader, options) {

		// Attach the uploader handler to the uploader div.
		$uploader.pkpHandler('$.pkp.controllers.UploaderHandler', options);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

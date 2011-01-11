/**
 * @file js/controllers/UploaderHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UploaderHandler
 * @ingroup js_controllers
 *
 * @brief PKP file uploader widget handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $uploader the wrapped HTML uploader element.
	 * @param {Object} options options to be passed
	 *  into the validator plug-in.
	 */
	$.pkp.controllers.UploaderHandler = function($uploader, options) {
		this.parent($uploader, options);

		// Check whether we really got an empty div to attach
		// our uploader to.
		if (!($uploader.is('div') && $uploader.text() === '')) {
			throw Error(['An uploader widget controller can only be attached',
				' to an empty div!'].join(''));
		}

		// Create uploader settings.
		var uploaderOptions = $.extend(
				{ },
				// Default settings.
				this.self('DEFAULT_PROPERTIES_'),
				// Non-default settings.
				{
					url: options.uploadUrl,
					setup: options.setup,
					// Flash settings
					flash_swf_url: options.baseUrl +
							'/lib/pkp/js/lib/plupload/plupload.flash.swf',
					// Silverlight settings
					silverlight_xap_url: options.baseUrl +
							'/lib/pkp/js/lib/plupload/plupload.silverlight.xap'
				});

		// Create the uploader with the puploader plug-in.
		// Setup the upload widget.
		$uploader.pluploadQueue(uploaderOptions);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.UploaderHandler, $.pkp.classes.Handler);


	//
	// Private static properties
	//
	/**
	 * Default options
	 * @private
	 * @type {Object}
	 * @const
	 */
	$.pkp.controllers.UploaderHandler.DEFAULT_PROPERTIES_ = {
		// General settings
		runtimes: 'html5,flash,silverlight,html4',
		max_file_size: '20mb',
		multi_selection: false,
		file_data_name: 'uploadedFile',
		multipart: true
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

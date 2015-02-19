/**
 * @file js/controllers/informationCenter/SignoffNotesHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffNotesHandler
 * @ingroup js_controllers_informationCenter
 *
 * @brief Signoff information center "notes" handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $notesDiv A wrapped HTML element that
	 *  represents the "notes" interface element.
	 * @param {Object} options Notes options.
	 */
	$.pkp.controllers.informationCenter.SignoffNotesHandler =
			function($notesDiv, options) {
		this.parent($notesDiv, options);

		// Store the signoff notes form fetch URL.
		this.signoffNotesFormUrl_ = options.signoffNotesFormUrl;

		// Bind for select signoff event, triggered by the drop down
		// element inside this widget.
		this.bind('selectSignoff', this.handleRefreshNotesForm_);

		// Check if we can automatically load the notes form after
		// the dropdown finished loading its options.
		this.bind('dropDownOptionSet', this.handleDropDownOptionSet_);

		// Load the notes form.
		var signoffId = null;
		if (options.signoffId) {
			signoffId = options.signoffId;
		}
		this.loadNoteForm_(signoffId);

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.informationCenter.SignoffNotesHandler,
			$.pkp.classes.Handler
	);


	//
	// Private properties
	//
	/**
	 * The URL to be called to fetch the signoff notes form.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.informationCenter.SignoffNotesHandler.
			prototype.signoffNotesFormUrl_ = '';


	//
	// Private methods.
	//
	/**
	 * Handle the "note added" event triggered by the
	 * note form whenever a new note is added.
	 *
	 * @param {$.pkp.controllers.form.AjaxFormHandler} callingForm The widget
	 *  that triggered the event.
	 * @param {Event} event The upload event.
	 * @param {number} signoffId The signoff ID.
	 * @private
	 */
	$.pkp.controllers.informationCenter.SignoffNotesHandler.
			prototype.handleRefreshNotesForm_ =
			function(callingForm, event, signoffId) {
		if (signoffId !== 0) {
			// Fetch the form
			this.loadNoteForm_(signoffId);
		} else {
			// Else it was the placeholder; blank out the form
			var $notesFormContainer = $('#signoffNotesFormContainer');
			$notesFormContainer.children().remove();
		}
	};


	/**
	 * Automatically loads the note form if drop down have only
	 * one signoff id as option.
	 * @param {HTMLElement} dropdown The element that called the event.
	 * @param {Event} event The upload event.
	 * @private
	 */
	$.pkp.controllers.informationCenter.SignoffNotesHandler.
			prototype.handleDropDownOptionSet_ = function(dropdown, event) {
		var $dropDown = $('#signoffSelect', this.getHtmlElement()),
				$options = $('option', $dropDown),
				signoffId;

		if ($options.length == 2) {
			signoffId = /** @type {string} */ $('option', $dropDown).next().val();
			$dropDown.val(signoffId);

			this.loadNoteForm_(signoffId);
		}
	};


	/**
	 * Send a request to load the signoff notes form.
	 * @param {number|string} signoffId The signoff id.
	 * @private
	 */
	$.pkp.controllers.informationCenter.SignoffNotesHandler.prototype.
			loadNoteForm_ = function(signoffId) {
		if (signoffId !== undefined && signoffId) {
			$.get(this.signoffNotesFormUrl_, { signoffId: signoffId },
					this.callbackWrapper(this.showFetchedNoteForm_), 'json');
		}
	};


	/**
	 * Show the fetched note form.
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.informationCenter.SignoffNotesHandler.prototype.
			showFetchedNoteForm_ = function(ajaxContext, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				// Find the container and remove all children.
				$notesFormContainer = $('#signoffNotesFormContainer');

		$notesFormContainer.children().remove();

		// Replace it with the form content.
		$notesFormContainer.append(processedJsonData.content);
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

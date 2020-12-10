/**
 * @defgroup js_controllers_informationCenter
 */
// Create the modal namespace.
jQuery.pkp.controllers.informationCenter =
			jQuery.pkp.controllers.informationCenter || { };


/**
 * @file js/controllers/informationCenter/NotesHandler.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NotesHandler
 * @ingroup js_controllers_informationCenter
 *
 * @brief Information center "notes" tab handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $notesDiv A wrapped HTML element that
	 *  represents the "notes" interface element.
	 * @param {Object} options Tabbed modal options.
	 */
	$.pkp.controllers.informationCenter.NotesHandler =
			function($notesDiv, options) {
		this.parent($notesDiv, options);

		// Refresh the widget when a note is added or deleted
		this.bind('noteAdded', this.handleRefreshNoteList);
		this.bind('noteDeleted', this.handleRefreshNoteList);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.informationCenter.NotesHandler,
			$.pkp.classes.Handler
	);


	//
	// Public methods
	//
	/**
	 * Handle the "note added" event triggered by the
	 * note form whenever a new note is added.
	 *
	 * @param {$.pkp.controllers.form.AjaxFormHandler} callingForm The form
	 *  that triggered the event.
	 * @param {Event} event The upload event.
	 * @param {string} html Rendered HTML to refresh the notes handler
	 */
	$.pkp.controllers.informationCenter.NotesHandler.
			prototype.handleRefreshNoteList = function(callingForm, event, html) {

		// Scroll back to the top of the notes list, where the note will appear
		$('.pkp_modal').first().scrollTop(0);

		this.replaceWith(html);
	};


}(jQuery));

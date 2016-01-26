/**
 * @file js/pages/workflow/SubmissionHeaderHandler.js
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHeaderHandler
 * @ingroup js_pages_workflow
 *
 * @brief Handler for the workflow header.
 *
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $submissionHeader The HTML element encapsulating
	 *  the header div.
	 * @param {{
	 *   participantToggleSelector: string
	 *   }} options Handler options.
	 */
	$.pkp.pages.workflow.SubmissionHeaderHandler =
			function($submissionHeader, options) {

		this.parent($submissionHeader, options);

		this.bind('gridRefreshRequested', this.refreshWorkflowContent_);
		this.publishEvent('stageParticipantsChanged');

		this.participantToggleSelector_ = options.participantToggleSelector;
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.workflow.SubmissionHeaderHandler,
			$.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * Site handler options.
	 * @private
	 * @type {string?}
	 */
	$.pkp.pages.workflow.SubmissionHeaderHandler
			.prototype.participantToggleSelector_ = null;


	//
	// Private functions
	//
	/**
	 * Potentially refresh workflow content on contained grid changes.
	 *
	 * @param {jQueryObject} callingElement The calling element.
	 *  that triggered the event.
	 * @param {Event} event The event.
	 * @private
	 */
	$.pkp.pages.workflow.SubmissionHeaderHandler.prototype.refreshWorkflowContent_ =
			function(callingElement, event) {

		var $updateSourceElement = $(event.target);
		if ($updateSourceElement.attr('id').match(/^stageParticipantGridContainer/)) {
			// If the participants grid was the event source, we
			// may need to re-draw workflow contents.
			this.trigger('stageParticipantsChanged');

			// We also want to close the participants grid view
			// every time a change is made there.
			$(this.participantToggleSelector_).click();
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

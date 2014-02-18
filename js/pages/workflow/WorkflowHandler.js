/**
 * @defgroup js_pages_workflow
 */
/**
 * @file js/pages/workflow/WorkflowHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorkflowHandler
 * @ingroup js_pages_workflow
 *
 * @brief Base handler for the workflow pages.
 *
 */
(function($) {

	/** @type {Object} */
	$.pkp.pages.workflow = $.pkp.pages.workflow || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $workflowElement The HTML element encapsulating
	 *  the production page.
	 * @param {Object} options Handler options.
	 */
	$.pkp.pages.workflow.WorkflowHandler =
			function($workflowElement, options) {

		this.parent($workflowElement, options);

		this.bind('stageParticipantsChanged', this.handleStageParticipantsChanged_);
		this.bind('dataChanged', this.dataChangedHandler_);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.workflow.WorkflowHandler,
			$.pkp.classes.Handler);


	//
	// Private functions
	//
	/**
	 * Potentially refresh workflow content on participant change.
	 *
	 * @param {jQueryObject} callingElement The calling element.
	 *  that triggered the event.
	 * @param {Event} event The event.
	 * @private
	 */
	$.pkp.pages.workflow.WorkflowHandler.prototype.handleStageParticipantsChanged_ =
			function(callingElement, event) {

		// Find and reload editor decision action divs.
		this.getHtmlElement().find('.editorDecisionActions').each(function() {
			var handler = $.pkp.classes.Handler.getHandler($(this));
			handler.reload();
		});
	};


	/**
	 * Potentially refresh contained grid.
	 *
	 * @param {jQueryObject} callingElement The calling element.
	 *  that triggered the event.
	 * @param {Event} event The event.
	 * @param {Object} eventData Event data.
	 * @private
	 */
	$.pkp.pages.workflow.WorkflowHandler.prototype.dataChangedHandler_ =
			function(callingElement, event, eventData) {

		if ($(event.target, this.getHtmlElement()).children('a').
				attr('id').match(/submissionEntry/)) {
			// Refresh the format grid on this page, if any.
			var $formatsGrid = $('[id^="formatsGridContainer"]',
					this.getHtmlElement()).children('div');
			$formatsGrid.trigger('dataChanged', [eventData]);
			$formatsGrid.trigger('notifyUser', [$formatsGrid]);
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

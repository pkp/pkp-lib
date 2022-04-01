/**
 * @file js/controllers/EditorialActionsHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialActionsHandler
 * @ingroup js_controllers
 *
 * @brief A handler for the editorial actions button in the workflow
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $element The outer <div> element
	 * @param {Object} options Handler options.
	 */
	$.pkp.controllers.EditorialActionsHandler = function($element, options) {
		this.parent($element, options);
		$element.find('.pkp_workflow_change_decision')
				.click(this.callbackWrapper(this.showActions_));
		$element.find('[data-decision]')
				.click(this.callbackWrapper(this.emitRevisionDecision_));
		$element.find('[data-recommendation]')
				.click(this.callbackWrapper(this.emitRevisionRecommendation_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.EditorialActionsHandler, $.pkp.classes.Handler);


	//
	// Private methods
	//
	/**
	 * Show the editorial actions
	 * @private
	 * @param {HTMLElement} sourceElement The clicked link.
	 * @param {Event} event The triggered event (click).
	 */
	$.pkp.controllers.EditorialActionsHandler.prototype.showActions_ =
			function(sourceElement, event) {
		this.getHtmlElement().find('.pkp_workflow_change_decision').hide();
		this.getHtmlElement().find('.pkp_workflow_decisions_options').removeClass('pkp_workflow_decisions_options_hidden');
	};

	/**
	 * Emit an event when a request revisions decision is initiated
	 *
	 * @param {HTMLElement} sourceElement The clicked link.
	 * @param {Event} event The triggered event (click).
	 */
	$.pkp.controllers.EditorialActionsHandler.prototype.emitRevisionDecision_ =
			function(sourceElement, event) {
		var $el = $(sourceElement);
		pkp.eventBus.$emit('decision:revisions', $el.data('reviewRoundId'));
	};

	/**
	 * Emit an event when a request revisions recommendation is initiated
	 *
	 * @param {HTMLElement} sourceElement The clicked link.
	 * @param {Event} event The triggered event (click).
	 */
	$.pkp.controllers.EditorialActionsHandler.prototype.emitRevisionRecommendation_ =
			function(sourceElement, event) {
		var $el = $(sourceElement);
		pkp.eventBus.$emit('recommendation:revisions', $el.data('reviewRoundId'));
	};


}(jQuery));

/**
 * @file js/controllers/EditorialActionsHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
		$element.find('.pkp_workflow_change_decision').click(this.callbackWrapper(this.showActions_));
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
		this.getHtmlElement().find('.pkp_workflow_decided_actions').show();
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));
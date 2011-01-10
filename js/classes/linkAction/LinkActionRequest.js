/**
 * @defgroup js_classes_linkAction
 */
// Define the namespace
$.pkp.classes.linkAction = $.pkp.classes.linkAction || {};


/**
 * @file js/classes/linkAction/LinkActionRequest.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LinkActionRequest
 * @ingroup js_classes_linkAction
 *
 * @brief Base class for all link action requests.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @param {jQuery} $linkActionElement The element the link
	 *  action was attached to.
	 * @param {Object} options Configuration of the link action
	 *  request.
	 */
	$.pkp.classes.linkAction.LinkActionRequest =
			function($linkActionElement, options) {

		// Save the reference to the link action element.
		this.$linkActionElement = $linkActionElement;

		// Save the link action request options.
		this.options = options;
	};


	//
	// Protected properties
	//
	/**
	 * The element the link action was attached to.
	 * @protected
	 * @type {Object}
	 */
	$.pkp.classes.linkAction.LinkActionRequest.prototype.
			$linkActionElement = null;


	/**
	 * The link action request options.
	 * @protected
	 * @type {Object}
	 */
	$.pkp.classes.linkAction.LinkActionRequest.prototype.options = null;


	//
	// Public methods
	//
	/**
	 * Callback that will be bound to the link action element.
	 * @param {HTMLElement} element The element that triggered the link
	 *  action activation event.
	 * @param {Event} event The event that activated the link action.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.classes.linkAction.LinkActionRequest.prototype.activate =
			function(element, event) {

		return false;
	};


	/**
	 * Callback that will be bound to the 'action finished' event of the
	 * link action. This will execute the response handler.
	 * @param {HTMLElement} element The action handler that triggered
	 *  the event.
	 * @param {Event} event "action finished" event.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.classes.linkAction.LinkActionRequest.prototype.finish =
			function(element, event) {

		return false;
	};


	//
	// Protected methods
	//
	/**
	 * Retrieve the link action request options.
	 * @return {Object} The link action request options.
	 */
	$.pkp.classes.linkAction.LinkActionRequest.prototype.getOptions = function() {
		return this.options;
	};


	/**
	 * Retrieve the element the link action was attached to.
	 * @return {Object} The element the link action was attached to.
	 */
	$.pkp.classes.linkAction.LinkActionRequest.prototype.
			getLinkActionElement = function() {

		return this.$linkActionElement;
	};


	/** @param {jQuery} $ jQuery closure. */
})(jQuery);

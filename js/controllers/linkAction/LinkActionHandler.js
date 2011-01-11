/**
 * @defgroup js_controllers_linkAction
 */
// Create the linkAction namespace.
jQuery.pkp.controllers.linkAction = jQuery.pkp.controllers.linkAction || { };

/**
 * @file js/controllers/linkAction/LinkActionHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LinkActionHandler
 * @ingroup js_controllers_linkAction
 *
 * @brief Link action handler that executes the action's handler when activated
 *  and delegates the action handler's response to the action's response
 *  handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $handledElement The clickable element
	 *  the link action will be attached to.
	 * @param {Object} options Configuration of the link action
	 *  handler. The object must contain the following elements:
	 *  - actionRequest: The action to be executed when the link
	 *                   action is being activated.
	 *  - actionRequestOptions: Configuration of the action request.
	 *  - actionResponse: The action's response listener.
	 *  - actionResponseOptions: Options for the response listener.
	 */
	$.pkp.controllers.linkAction.LinkActionHandler =
			function($handledElement, options) {
		this.parent($handledElement, options);

		// Instantiate the link action request.
		if (!options.actionRequest || !options.actionRequestOptions) {
			throw Error(['The "actionRequest" and "actionRequestOptions"',
				'settings are required in a LinkActionHandler'].join(''));
		}
		this.linkActionRequest_ =
				/** @type {$.pkp.classes.linkAction.LinkActionRequest} */
				($.pkp.classes.Helper.objectFactory(
						options.actionRequest,
						[$handledElement, options.actionRequestOptions]));

		// Bind the link action request to the handled element.
		$handledElement.click(this.callbackWrapper(
				this.linkActionRequest_.activate, this.linkActionRequest_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.linkAction.LinkActionHandler,
			$.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The link action request object.
	 * @private
	 * @type {$.pkp.classes.linkAction.LinkActionRequest}
	 */
	$.pkp.controllers.linkAction.LinkActionHandler.prototype.
			linkActionRequest_ = null;


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

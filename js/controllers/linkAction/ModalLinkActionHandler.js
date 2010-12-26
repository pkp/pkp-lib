/**
 * @defgroup js_controllers_linkAction
 */
// Create the linkAction namespace.
jQuery.pkp.controllers.linkAction = jQuery.pkp.controllers.linkAction || { };

/**
 * @file js/controllers/linkAction/ModalLinkActionHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ModalLinkActionHandler
 * @ingroup js_controllers_linkAction
 *
 * @brief Link action handler that opens a simple dialog modal
 *  when being clicked.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $handledElement The clickable element
	 *  the link action will be attached to.
	 * @param {Object} options Options to be passed through to
	 *  the modal.
	 * @see $.pkp.controllers.modal.Modal
	 */
	$.pkp.controllers.linkAction.ModalLinkActionHandler =
			function($handledElement, options) {
		this.parent($handledElement);

		// Save the modal options.
		this.options_ = options;

		// Bind to the handled element.
		this.bind('click', this.modalOpen);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.linkAction.ModalLinkActionHandler,
			$.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The modal link action options.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.linkAction.ModalLinkActionHandler.prototype.options_ = null;


	/**
	 * A pointer to the dialog HTML element.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.linkAction.ModalLinkActionHandler.prototype.dialog_ = null;


	/**
	 * Callback that will be bound to the link action element.
	 * @protected
	 * @param {HTMLElement} element The element that
	 *  triggered the event.
	 * @param {Event} event Click event.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.controllers.linkAction.ModalLinkActionHandler.prototype.modalOpen =
			function(element, event) {

		// If there is no title then try to retrieve a title
		// from the calling element's text.
		var options = this.options_, modalOptions = options.modalOptions;
		if (!modalOptions) {
			throw Error(['The "modalOptions" setting is required ',
				'in a ModalLinkActionHandler'].join(''));
		}

		var $handledElement = $(element);
		if (modalOptions.title === undefined) {
			var title = $handledElement.text();
			if (title === '') {
				// Try to retrieve title from calling button's title attribute.
				title = $handledElement.attr('title');
			}
			modalOptions.title = title;
		}

		// Generate a unique ID.
		var uuid = $.pkp.classes.Helper.uuid();

		// Instantiate the modal.
		if (!options.modalHandler) {
			throw Error(['The "modalHandler" setting is required ',
				'in a ModalLinkActionHandler'].join(''));
		}
		this.dialog_ = $('<div id=' + uuid + '></div>').pkpHandler(
				options.modalHandler, modalOptions);

		// Subscribe to the dialog handler's 'removed' event so that
		// we can clean up.
		var dialogHandler = this.self('getHandler', this.dialog_);
		dialogHandler.bind('pkpRemoveHandler',
				this.callbackWrapper(this.modalRemove));

		return false;
	};


	/**
	 * Callback that will be bound to the 'remove' event of the
	 * modal handler.
	 * @param {HTMLElement} element The modal widget that
	 *  triggered the event.
	 * @param {Event} event Remove event.
	 */
	$.pkp.controllers.linkAction.ModalLinkActionHandler.prototype.modalRemove =
			function(element, event) {
		this.dialog_.remove();
	};



/** @param {jQuery} $ jQuery closure. */
})(jQuery);

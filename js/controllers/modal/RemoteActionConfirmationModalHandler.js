/**
 * @file js/controllers/modal/RemoteActionConfirmationModalHandler.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RemoteActionConfirmationModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A confirmation modal that executes a remote action on confirmation.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.ConfirmationModalHandler
	 *
	 * @param {jQuery} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object} options Non-default options to configure
	 *  the modal.
	 *
	 *  Options are:
	 *  - remoteAction string An action to be executed when the confirmation
	 *    button has been clicked.
	 *  - All options from the ConfirmationModalHandler and ModalHandler
	 *    widgets.
	 *  - All options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		// Configure the remote action (URL) to be called when
		// the modal closes.
		this.remoteAction_ = options.remoteAction;
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.modal.RemoteActionConfirmationModalHandler,
			$.pkp.controllers.modal.ConfirmationModalHandler);


	//
	// Private properties
	//
	/**
	 * A remote action to be executed when the confirmation button
	 * has been clicked.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			remoteAction_ = null;


	//
	// Protected methods
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			checkOptions = function(options) {

		// Check the mandatory options of the ModalHandler handler.
		if (!this.parent('checkOptions', options)) {
			return false;
		}

		// Check for our own mandatory options.
		// The cancel button is mandatory for remote action confirmation modals.
		return typeof options.cancelButton === 'string' &&
				typeof options.remoteAction === 'string';
	};


	//
	// Public methods
	//
	/**
	 * Callback that will be activated when the modal's
	 * confirm button is clicked.
	 *
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			modalConfirm = function(dialogElement) {

		$.post(this.remoteAction_,
				this.callbackWrapper(this.remoteResponse), 'json');
	};


	//
	// Protected methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.RemoteActionConfirmationModalHandler.prototype.
			remoteResponse = function(ajaxOptions, jsonData) {

		jsonData = this.parent('remoteResponse', ajaxOptions, jsonData);
		if (jsonData !== false) {
			this.modalClose(ajaxOptions);
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

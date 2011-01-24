/**
 * @file js/controllers/modal/ConfirmationModalHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ConfirmationModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A modal that has a cancel button.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.ModalHandler
	 *
	 * @param {jQuery} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object} options non-default Dialog options
	 *  to be passed into the dialog widget.
	 *
	 *  Options are:
	 *  - cancelButton string the name for the cancel button
	 *  - url string an action to be executed if the confirmation button has
	 *    been pressed.
	 *  - all options from the ModalHandler widget
	 *  - all options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		if (options.remoteAction) {
			this.confirmationAction_ = options.remoteAction;
		}
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.ConfirmationModalHandler,
			$.pkp.controllers.modal.ModalHandler);


	//
	// Private properties
	//
	/**
	 * A remote action to be executed when the confirmation button
	 * has been pressed.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.
			confirmationAction_ = null;


	//
	// Protected methods
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.checkOptions =
			function(options) {
		// Check the mandatory options of the ModalHandler handler.
		if (!this.parent('checkOptions', options)) {
			return false;
		}

		// Check for our own mandatory options.
		return typeof options.okButton === 'string' &&
				typeof options.cancelButton === 'string' &&
				typeof options.dialogText === 'string';
	};


	/** @inheritDoc */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.mergeOptions =
			function(options) {
		// Let the parent class prepare the options first.
		var internalOptions = this.parent('mergeOptions', options);

		// Configure confirmation button.
		internalOptions.buttons = { };
		internalOptions.buttons[options.okButton] =
				this.callbackWrapper(this.modalConfirm);
		delete options.okButton;

		// Configure the cancel button.
		internalOptions.buttons[options.cancelButton] =
				this.callbackWrapper(this.modalCancel);
		delete options.cancelButton;

		// Add the modal dialog text.
		var $handledElement = this.getHtmlElement();
		$handledElement.html(internalOptions.dialogText);
		delete internalOptions.dialogText;

		return internalOptions;
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
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.modalConfirm =
			function(dialogElement) {

		if (this.confirmationAction_) {
			$.post(this.confirmationAction_,
					this.callbackWrapper(this.remoteResponse), 'json');
		} else {
			this.getHtmlElement().dialog('close');
		}
	};


	/**
	 * Callback that will be activated when the modal's
	 * cancel button is clicked.
	 *
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.modalCancel =
			function(dialogElement) {
		this.getHtmlElement().dialog('close');
	};


	//
	// Protected methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.remoteResponse =
			function(ajaxOptions, jsonData) {

		jsonData = this.parent('remoteResponse', ajaxOptions, jsonData);
		if (jsonData !== false) {
			this.getHtmlElement().dialog('close');
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

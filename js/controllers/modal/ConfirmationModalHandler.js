/**
 * @file js/controllers/modal/ConfirmationModalHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ConfirmationModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A modal that displays a static explanatory text and has cancel and
 *  confirmation buttons.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.ModalHandler
	 *
	 * @param {jQueryObject} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object.<string, *>} options Non-default options to configure
	 *  the modal.
	 *
	 *  Options are:
	 *  - okButton string the name for the confirmation button.
	 *  - cancelButton string the name for the cancel button
	 *    (or false for no button).
	 *  - dialogText string the text to be displayed in the modal.
	 *  - All options from the ModalHandler widget.
	 *  - All options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.ConfirmationModalHandler,
			$.pkp.controllers.modal.ModalHandler);


	//
	// Protected methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.checkOptions =
			function(options) {
		// Check the mandatory options of the ModalHandler handler.
		if (!this.parent('checkOptions', options)) {
			return false;
		}

		// Hack to prevent closure compiler type mismatches
		var castOptions = /** @type {{okButton: string,
				cancelButton: string, dialogText: string}} */ options;

		// Check for our own mandatory options.
		return typeof castOptions.okButton === 'string' &&
				(/** @type {boolean} */ (castOptions.cancelButton) === false ||
				typeof castOptions.cancelButton === 'string') &&
				typeof castOptions.dialogText === 'string';
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.mergeOptions =
			function(options) {
		// Let the parent class prepare the options first.
		var internalOptions = /** @type {Object.<string, *>} */ (
				this.parent('mergeOptions', options)),
				$handledElement;

		// Configure confirmation button.
		internalOptions.buttons = { };
		internalOptions.buttons[options.okButton] =
				this.callbackWrapper(this.modalConfirm);
		delete options.okButton;

		// Configure the cancel button.
		if (options.cancelButton) {
			internalOptions.buttons[options.cancelButton] =
					this.callbackWrapper(this.modalClose);
			delete options.cancelButton;
		}

		// Add the modal dialog text.
		$handledElement = this.getHtmlElement();
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

		// The default implementation will simply close the modal.
		this.modalClose(dialogElement);
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

/**
 * @file js/controllers/modal/ConfirmationModalHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	 * @param {{
	 *  callback: Function,
	 *  callbackArgs: Object
	 *  }} options Non-default options to configure the modal.
	 *
	 *  Options are:
	 *  - okButton string the name for the confirmation button.
	 *  - cancelButton string the name for the cancel button
	 *    (or false for no button).
	 *  - dialogText string the text to be displayed in the modal.
	 *  - All options from the ModalHandler widget.
	 *  - callback function A callback function to close when confirmed
	 *  - callbackArgs object Arguments to pass to the callback function
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler =
			function($handledElement, options) {

		/** create props for Vue.js dialog component */
		this.dialogProps = {
			title: options.title,
			message: options.dialogText,
			actions: [],
			closeLegacyHandler: this.callbackWrapper(this.modalClose),
			modalStyle: options.modalStyle
		};

		if (options.okButton) {
			this.dialogProps.actions.push({
				label: options.okButton,
				isWarnable: options.modalStyle === 'negative',
				callback: this.callbackWrapper(this.modalConfirm)
			});
		}

		if (options.cancelButton) {
			this.dialogProps.actions.push({
				label: options.cancelButton,
				isWarnable: options.modalStyle !== 'negative',
				callback: this.callbackWrapper(this.modalClose)
			});
		}

		this.parent($handledElement, options);

		this.callback_ = options.callback || null;
		this.callbackArgs_ = options.callbackArgs || null;

		// Bind to the confirmation button
		$handledElement.find('.pkpModalConfirmButton')
				.on('click', this.callbackWrapper(this.modalConfirm));
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.ConfirmationModalHandler,
			$.pkp.controllers.modal.ModalHandler);


	//
	// Private properties
	//
	/**
	 * A callback to fire when confirmed
	 * @private
	 * @type {?Function}
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.
			callback_ = null;


	/**
	 * Arguments to pass to the callback function
	 * @private
	 * @type {?Object}
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.
			callbackArgs_ = null;


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
				cancelButton: string, dialogText: string}} */ (options);

		// Check for our own mandatory options.
		return typeof castOptions.okButton === 'string' &&
				(/** @type {boolean} */ (castOptions.cancelButton) === false ||
				typeof castOptions.cancelButton === 'string') &&
				typeof castOptions.dialogText === 'string';
	};


	/**
	 * Open dialog - pass the props to the modalStore to display Vue.js dialog
	 * @param {jQueryObject} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @protected
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.modalOpen =
			function($handledElement) {

		this.parent('modalOpen', $handledElement);
		pkp.eventBus.$emit('open-dialog-vue', {
			dialogProps: this.dialogProps
		});
	};


	/**
	 * Callback that will be activated when the modal's
	 * confirm button is clicked.
	 *
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 * @param {Event} event The click event.
	 */
	$.pkp.controllers.modal.ConfirmationModalHandler.prototype.modalConfirm =
			function(dialogElement, event) {

		// The default implementation will simply close the modal.
		this.modalClose(dialogElement);

		if (this.callback_) {
			this.callback_.call(null, this.callbackArgs_);
		}
	};


}(jQuery));

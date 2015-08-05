/**
 * @file js/controllers/modal/ButtonConfirmationModalHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ButtonConfirmationModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A confirmation modal that displays a confirmation message before
 *  actually triggering a click event on the calling element (usually a
 *  button).
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.ConfirmationModalHandler
	 *
	 * @param {jQueryObject} $handledElement The modal.
	 * @param {Object} options Non-default options to configure
	 *  the modal.
	 *
	 *  Options are:
	 *  - button jQuery The button to be clicked on success.
	 *  - All options from the ConfirmationModalHandler and ModalHandler
	 *    widgets.
	 *  - All options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.ButtonConfirmationModalHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		// Bind to the confirmation button
		$handledElement.find('.pkpModalConfirmButton').on('click',this.callbackWrapper(this.modalConfirm));

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.modal.ButtonConfirmationModalHandler,
			$.pkp.controllers.modal.ConfirmationModalHandler);


	//
	// Protected methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.ButtonConfirmationModalHandler.prototype.checkOptions =
			function(options) {
		// Check inherited options
		if (!this.parent('checkOptions', options)) {
			return false;
		}


		return typeof options.$button == 'object' && options.$button.length == 1;
	};

	/**
	 * Callback that will be activated when the modal is confirmed
	 *
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 */
	$.pkp.controllers.modal.ButtonConfirmationModalHandler.prototype.modalConfirm =
			function(dialogElement) {

		// Close the modal first so that the linkaction is no longer disabled
		this.modalClose(dialogElement);

		// Trigger the link/button action
		if ( this.options.$button.attr('type') == 'submit' ) {
			this.options.$button.trigger('submit');
		} else {
			this.options.$button.click();
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

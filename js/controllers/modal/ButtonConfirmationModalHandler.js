/**
 * @file js/controllers/modal/ButtonConfirmationModalHandler.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
	 * @extends $.pkp.controllers.modal.CallbackConfirmationModalHandler
	 *
	 * @param {jQuery} $handledElement The modal.
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

		// Bind our own handler to the parent's confirmation callback.
		options.confirmationCallback = this.callbackWrapper(this.clickButton);

		this.parent($handledElement, options);

		// Save the button.
		this.$button_ = options.$button;

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.modal.ButtonConfirmationModalHandler,
			$.pkp.controllers.modal.CallbackConfirmationModalHandler);


	//
	// Private properties
	//
	/**
	 * The button we'll click on when activated.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.modal.CallbackConfirmationModalHandler.prototype.
			$button_ = null;


	//
	// Protected methods
	//
	/**
	 * Callback that clicks the handling element.
	 * @protected
	 */
	$.pkp.controllers.modal.ButtonConfirmationModalHandler.prototype.
			clickButton = function() {

		var $button = this.$button_;
		if ($button.attr('type') == 'submit') {
			// Trigger a submit event when the calling element is of the
			// "submit" type. Otherwise the no submit event will be triggered
			// when clicking a submit button. Use trigger() to let the event
			// bubble in all browsers.
			// FIXME: Test this in IE. According to the jQuery doc the event
			// should bubble there, too. But who knows...
			$button.trigger('submit');
		} else {
			// Click the calling element.
			$button.click();
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

/**
 * @defgroup js_controllers_modal
 */
// Create the modal namespace.
jQuery.pkp.controllers.modal = jQuery.pkp.controllers.modal || { };

/**
 * @file js/controllers/modal/ModalHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief Basic modal implementation.
 *
 *  A basic wrapper around jQueryUI's dialog widget with
 *  PKP-specific configuration.
 *
 *  This implementation of a modal has only one button and
 *  expects a simple message string.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $handledElement The modal.
	 * @param {Object} options Non-default dialog options
	 *  to be passed into the dialog widget.
	 *
	 *  Options are:
	 *  - dialogText string the content of the modal
	 *  - okButton string the name for the confirmation button
	 *  - all options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.ModalHandler = function($handledElement, options) {
		this.parent($handledElement, options);

		// Check the options.
		if (!this.checkOptions(options)) {
			throw Error('Missing or invalid modal options!');
		}

		// Clone the options object before we manipulate them.
		var internalOptions = $.extend(true, {}, options);

		// Merge user and default options.
		internalOptions = this.mergeOptions(internalOptions);

		// Open the modal.
		$handledElement.dialog(internalOptions);

		// Fix title bar.
		this.fixTitleBar_($handledElement, internalOptions);

		// Bind the close event.
		this.bind('dialogclose', this.dialogClose);

		// Publish some otherwise private events triggered
		// by nested widgets so that they can be handled by
		// the element that opened the modal.
		this.publishEvent('redirectRequested');
		this.publishEvent('dataChanged');
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.ModalHandler,
			$.pkp.classes.Handler);


	//
	// Private static properties
	//
	/**
	 * Default options
	 * @private
	 * @type {Object}
	 * @const
	 */
	$.pkp.controllers.modal.ModalHandler.DEFAULT_OPTIONS_ = {
		autoOpen: true,
		width: 710,
		modal: true,
		draggable: false,
		resizable: false,
		position: ['center', 100]
	};


	//
	// Protected methods
	//
	/**
	 * Check whether the correct options have been
	 * given for this modal.
	 * @protected
	 * @param {Object} options Dialog options.
	 * @return {boolean} True if options are ok.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.checkOptions =
			function(options) {

		// Check for basic configuration requirements.
		return typeof options === 'object' &&
				options.buttons === undefined;
	};


	/**
	 * Determine the options based on
	 * default options.
	 * @protected
	 * @param {Object} options Non-default dialog
	 *  options.
	 * @return {Object} The default options merged
	 *  with the non-default options.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.mergeOptions =
			function(options) {

		// Merge the user options into the default options.
		var mergedOptions = $.extend(true, { },
				this.self('DEFAULT_OPTIONS_'), options);
		return mergedOptions;
	};


	//
	// Public methods
	//
	/**
	 * Callback that will be activated when the modal's
	 * close icon is clicked.
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} event The triggering event (e.g. a click on
	 *  a close button. Not set if called via callback.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.modalClose =
			function(callingContext, event) {

		// Close the modal dialog.
		var $modalElement = this.getHtmlElement();
		$modalElement.dialog('close');
		return false;
	};


	/**
	 * Callback that will be bound to the close event
	 * triggered when the dialog is closed.
	 *
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.dialogClose =
			function(dialogElement) {
		// Remove the dialog including our button callbacks.
		var $dialogElement = $(dialogElement);
		$dialogElement.dialog('destroy');

		// Remove the dialog content.
		this.getHtmlElement().empty();

		// Return the handled DOM element to its
		// original state.
		this.remove();
	};


	//
	// Private methods
	//
	/**
	 * Change the default title bar to our customized version.
	 *
	 * @private
	 * @param {jQuery} $handledElement The element the
	 *  dialog was created on.
	 * @param {Object} options The dialog options.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.fixTitleBar_ =
			function($handledElement, options) {

		// The new titlebar.
		var $titleBar = $('<div class="pkp_controllers_modal_titleBar"></div>');

		// Title bar icon.
		var iconClass = options.titleIcon || null;
		if (iconClass) {
			$titleBar.append(['<span class="icon ', iconClass, '" />'].join(''));
		}

		// Title text.
		var title = options.title || null;
		if (title) {
			$titleBar.append(['<span class="text">', title, '</span>'].join(''));
		}

		// Close icon.
		var canClose = options.canClose || '1';
		if (canClose) {
			var $closeButton = $(['<a class="close ui-corner-all" href="#">',
				'<span class="ui-icon ui-icon-closethick">',
				'close</span></a>"'].join(''));
			$closeButton.click(this.callbackWrapper(this.modalClose));
			$titleBar.append($closeButton);
		}

		// Replace the original title bar with our own implementation.
		$titleBar.append($('<span style="clear:both" />'));
		$handledElement.parent().find('.ui-dialog-titlebar').replaceWith($titleBar);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

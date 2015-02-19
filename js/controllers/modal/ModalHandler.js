/**
 * @defgroup js_controllers_modal
 */
// Create the modal namespace.
jQuery.pkp.controllers.modal = jQuery.pkp.controllers.modal || { };

/**
 * @file js/controllers/modal/ModalHandler.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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

		// Fix title bar and close button.
		this.fixTitleBar_($handledElement, internalOptions);
		this.fixCloseButton_($handledElement, internalOptions);

		// if our modal is too narrow ('auto' width, with small amounts
		// of content), resize to prevent title wrapping.
		if ($handledElement.width() < 400) {
			$handledElement.dialog('option', 'width', '400');
		}

		// if the modal is too wide ('auto' width with large amounts
		// of content), resize to prevent modals that are too wide.
		if ($handledElement.width() > 710) {
			$handledElement.dialog('option', 'width', '710');
		}

		$handledElement.dialog('option', 'position', ['center', 100]);

		// Bind the close event.
		this.bind('dialogclose', this.dialogClose);

		// Publish some otherwise private events triggered
		// by nested widgets so that they can be handled by
		// the element that opened the modal.
		this.publishEvent('redirectRequested');
		this.publishEvent('dataChanged');
		this.publishEvent('containerReloadRequested');
		this.publishEvent('modalCanceled');
		this.publishEvent('updateHeader');
		this.publishEvent('updateSidebar');

		// Bind notify user event.
		this.bind('notifyUser', this.redirectNotifyUserEventHandler_);

		// Click outside to close.
		var canClose = options.canClose || '1';
		if (canClose) {
			$handledElement.parent().next('.ui-widget-overlay').
					click(this.callbackWrapper(this.outsideClick));
		}
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
	 * @param {Event} event The triggering event (e.g. a click on
	 *  a close button. Not set if called via callback.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.modalClose =
			function(callingContext, event) {

		if (event && event.currentTarget.id == 'cancelFormButton') {
			this.trigger('modalCanceled');
		}
		// Close the modal dialog.
		var $modalElement = this.getHtmlElement();
		// get the form from this modalElement so we can unregister it
		var $form = $modalElement.find('form').first();

		// modalClose is called on both 'cancel' and 'close' events.  With
		// callbacks both callingContext and event are undefined. So,
		// unregister this form with SiteHandler.

		if ($form.length == 1) {
			var handler = $.pkp.classes.Handler.getHandler($('#' + $form.attr('id')));
			if (handler.formChangesTracked) {
				if (!confirm($.pkp.locale.form_dataHasChanged)) {
					return false;
				} else {
					this.trigger('unregisterAllForms');
				}
			}
		}

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


	/**
	 * Callback that will be bound to the outside click event.
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event} event The outside click event.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.outsideClick =
			function(callingContext, event) {

		// Close the dialog.
		this.modalClose(callingContext, event);
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

		// Close icon.
		var canClose = options.canClose || '1';
		if (canClose) {
			var $closeButton = $(['<a class="close ui-corner-all" href="#">',
				'<span class="xIcon">',
				'close</span></a>"'].join(''));
			$closeButton.click(this.callbackWrapper(this.modalClose));
			$titleBar.append($closeButton);
		}

		// Title text.
		var title = options.title || null;
		var iconClass = options.titleIcon || '';
		if (title) {
			$titleBar.append(['<div class="large_sprite modal_title ' +
						iconClass + '"></div><h2>', title, '</h2>'].join(''));
		}

		// Replace the original title bar with our own implementation.
		$titleBar.append($('<span style="clear:both" />'));
		$handledElement.parent().find('.ui-dialog-titlebar').replaceWith($titleBar);
	};


	/**
	 * Change the default close button to our customized version.
	 *
	 * @private
	 * @param {jQuery} $handledElement The element the
	 *  dialog was created on.
	 * @param {Object} options The dialog options.
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.fixCloseButton_ =
			function($handledElement, options) {

		// The new close button.
		var $closeButton = $('<a id="cancelFormButton" ' +
				'class="cancelFormButton" href="#">Cancel</a>');

		var $parentElement = $handledElement.parent();
		// make changes to widen the button bar, and move the close button to the left
		$parentElement.find('.ui-dialog-buttonset').
				css({'width': '90%', 'padding': '10px 20px'});
		$parentElement.find('.ui-dialog-buttonset button:first').
				css({'float': 'right', 'margin': '0px'});

		// Replace the original close button with our own implementation.
		$parentElement.find('.ui-dialog-buttonset button:last').
				replaceWith($closeButton);
		// bind modalClose to the new button
		$parentElement.find('.cancelFormButton').
				bind('click', this.callbackWrapper(this.modalClose));
	};


	/**
	 * Handler to redirect to the correct notification widget the
	 * notify user event.
	 * @param {HTMLElement} sourceElement The element that issued the
	 * "notifyUser" event.
	 * @param {Event} event The "notify user" event.
	 * @param {HTMLElement} triggerElement The element that triggered
	 * the "notifyUser" event.
	 * @private
	 */
	$.pkp.controllers.modal.ModalHandler.prototype.redirectNotifyUserEventHandler_ =
			function(sourceElement, event, triggerElement) {

		// Use the notification helper to redirect the notify user event.
		$.pkp.classes.notification.NotificationHelper.
				redirectNotifyUserEvent(this, triggerElement);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

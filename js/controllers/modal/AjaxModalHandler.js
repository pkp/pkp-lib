/**
 * @file js/controllers/modal/AjaxModalHandler.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A modal that retrieves content from
 *  a remote AJAX endpoint.
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
	 *  - url string the remote AJAX endpoint that will be used
	 *    to retrieve the content of the modal.
	 *  - all options documented for the jQueryUI dialog widget,
	 *    except for the buttons parameter which is not supported.
	 */
	$.pkp.controllers.modal.AjaxModalHandler = function($handledElement, options) {
		this.parent($handledElement, options);

		// We assume that AJAX modals usually contain forms and
		// therefore bind to form events by default.
		this.bind('formSubmitted', this.modalClose);
		this.bind('formCanceled', this.modalClose);
		this.bind('ajaxHtmlError', this.modalClose);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.AjaxModalHandler,
			$.pkp.controllers.modal.ModalHandler);


	//
	// Protected methods
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.AjaxModalHandler.prototype.checkOptions =
			function(options) {
		// Check the mandatory options of the ModalHandler handler.
		if (!this.parent('checkOptions', options)) {
			return false;
		}

		// Check for our own mandatory options.
		return typeof options.url === 'string';
	};


	/** @inheritDoc */
	$.pkp.controllers.modal.AjaxModalHandler.prototype.mergeOptions =
			function(options) {
		// Bind open event.
		this.bind('dialogopen', this.dialogOpen);

		// Call parent.
		return this.parent('mergeOptions', options);
	};


	/** @inheritDoc */
	$.pkp.controllers.modal.AjaxModalHandler.prototype.modalClose =
			function(callingContext, event) {

		if (event.type == 'formSubmitted') {
			// Trigger the notify user event.
			this.getHtmlElement().parent().trigger('notifyUser');
		}

		return this.parent('modalClose');
	};


	/**
	 * Callback that will be bound to the open event
	 * triggered when the dialog is opened.
	 * @protected
	 * @param {HTMLElement} dialogElement The element the
	 *  dialog was created on.
	 */
	$.pkp.controllers.modal.AjaxModalHandler.prototype.dialogOpen =
			function(dialogElement) {
		// Make sure that the modal will remain on screen.
		var $dialogElement = $(dialogElement);
		$dialogElement.css({'max-height': 600, 'overflow-y': 'auto',
			'z-index': '10000'});

		// Retrieve remote modal content.
		var url = $dialogElement.dialog('option' , 'url');
		$dialogElement.pkpAjaxHtml(url);
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);

/**
 * @file js/controllers/modal/AjaxLegacyPluginModalHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxLegacyPluginModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief An ajax modal to be used in plugins management. This is part of a
 * temporary solution, while we don't modernize the UI of the plugins. The
 * functionalities implemented here are not necessary anywhere else and, sometimes,
 * are duplicating solutions that we already have in another handlers. DON'T USE this
 * handler if you are not showing legacy plugins management content.
 * FIXME After modernizing the UI of the plugins, remove this handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.AjaxModalHandler
	 *
	 * @inheritDoc
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler = function($handledElement, options) {
		this.parent($handledElement, options);

			this.bind('refreshLegacyPluginModal', this.callbackWrapper(this.refreshModalHandler_));
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.AjaxLegacyPluginModalHandler,
			$.pkp.controllers.modal.AjaxModalHandler);


	//
	// Private properties
	//
	/**
	 * The url used to fetch content to be displayed.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.url_ = null;


	//
	// Protected methods.
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.dialogOpen =
			function(dialogElement) {
		// Make sure that the modal will remain on screen.
		var $dialogElement = $(dialogElement);
		$dialogElement.css({'max-height': 420, 'overflow-y': 'auto',
			'z-index': '10000'});

		// Retrieve remote modal content.
		var url = $dialogElement.dialog('option' , 'url');
		this.url_ = url;
		var callback = this.callbackWrapper(this.refreshModalCallback_);
		$dialogElement.pkpAjaxHtml(url, callback);
	};


	//
	// Private helper methods.
	//
	/**
	 * Controls modal refreshing.
	 *
	 * @private
	 *
	 * @param {$jQuery} sourceElement The containing element.
	 * @param {String} url The url to fetch new content.
	 * @param {String} content The already fetched content to be shown.
	 * @param {Boolean} submit Use the url to submit the forms inside this modal?
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.refreshModal_ =
			function(url, content, submit) {

		var $dialogElement = $(this.getHtmlElement());

		if (url) {
			// Store the url that was used to fetch the content.
			this.url_ = url;
			var responseHandler = this.callbackWrapper(this.handleResponse_);

			// We want to submit a form?
			if (submit) {
				// Get all forms in modal to serialize them.
				var $forms = $('form', this.getHtmlElement());

				// Post the forms data using the passed url.
				$.post(url, $forms.serialize(), responseHandler, 'json');
			} else {
				// Fetch new content.
				$.getJSON(url, responseHandler);
			}
		} else if (content) {
			// Replace the modal content.
			$dialogElement.html(content);

			// Call the refresh modal callback to bind events and transform
			// html elements again.
			this.refreshModalCallback_();
		}
	};

	/**
	 * Modal refresh callback. Retrieve all html elements that we want to control
	 * and bind event handlers.
	 * @private
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.refreshModalCallback_ =
			function() {
		var $dialogElement = this.getHtmlElement();

		// Fix modal title.
		var $newTitle = $('h2', $dialogElement);
		if ($newTitle.length > 0) {
			var $currentTitle = $dialogElement.parent().find('.pkp_controllers_modal_titleBar h2');
			$currentTitle.replaceWith($newTitle);
		}

		// Insert the class to identify legacy plugin content.
		$dialogElement.addClass('legacy_plugin_content');

		// Create tabs for menu links.
		var $menu = $('.menu');
		if ($menu.length > 0) {
			$menu.tabs();
		}

		// Transform buttons.
		var $buttons = $(':submit, :button, :reset');
		if ($buttons.length > 0) $buttons.button();

		// Attach click handler on every link inside this modal.
		var $linkElements = $('a', $dialogElement);
		if ($linkElements.length > 0) {
			$linkElements.bind('click', this.callbackWrapper(this.clickLinkHandler_));
		}

		// Attach form submit handlers.
		var $formElements = $('form.pkp_form', $dialogElement);
		if ($formElements.length > 0) {
			$formElements.bind('submit', this.callbackWrapper(this.submitFormHandler_));
		}

		// Reset the scrolling of the modal.
		$dialogElement.scrollTop(0);

		// Go to the anchor defined in url that fetched the content, if any.
		var url = this.url_;
		if (url.match('#')) {
			var pageAnchor = url.split('#')[1];

			// Scroll always down (because of the scroll reseting) to the page anchor.
			$dialogElement.scrollTop($('a[name="' + pageAnchor + '"]').position().top);
		}

		// Notify user with any existing notification.
		this.trigger('notifyUser', this.getHtmlElement());
	};

	/**
	 * Refresh modal event handler.
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.refreshModalHandler_ =
			function($modal, modalHtmlElement, event, url) {
		// Refresh modal using the event data as url to fetch the content.
		if (url) {
			// Fetch content again using this url.
			var $forms = $('form', this.getHtmlElement());
			if ($forms.length > 0) {
				// We have forms, so submit their data again.
				this.refreshModal_(url, null, true);
			} else {
				// No forms, request new content only.
				this.refreshModal_(url);
			}
		}
	};

	/**
	 * Link click event handler.
	 * @param {object} link
	 * @return {boolean}
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.clickLinkHandler_ =
			function(link) {
		// Get the element that triggered the event.
		var $link = $(link);

		$link.unbind('click', this.clickLinkHandler_);

		// Get the url of the link that triggered the event.
		var url = $link.attr('href');
		this.refreshModal_(url);
		return false;
	};

	/**
	 * Link click event handler.
	 * @param {object} link
	 * @return {boolean}
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.submitFormHandler_ =
			function(form, event) {
		// Get the element that triggered the event.
		var $form = $(form);

		$form.unbind('submit', this.submitFormHandler_);

		// Get the url of the form to submit via ajax.
		var url = $form.attr('action');
		this.refreshModal_(url, null, true);

		return false;
	};

	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.handleResponse_ =
			function(formElement, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			if (jsonData.content !== '') {
				// The request returned content. Refresh modal replacing it.
				this.refreshModal_(null, jsonData.content);
			}
		}
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);

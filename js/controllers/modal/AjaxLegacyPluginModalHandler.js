/**
 * @file js/controllers/modal/AjaxLegacyPluginModalHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxLegacyPluginModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief An ajax modal to be used in plugins management. This is part of a
 * temporary solution, while we don't modernize the UI of the plugins. The
 * functionalities implemented here are not necessary anywhere else and,
 * sometimes, are duplicating solutions that we already have in another
 * handlers. DON'T USE this handler if you are not showing legacy plugins
 * management content.
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
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler =
			function($handledElement, options) {
		this.parent($handledElement, options);

		this.bind('refreshLegacyPluginModal',
				this.callbackWrapper(this.refreshModalHandler_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.modal.AjaxLegacyPluginModalHandler,
			$.pkp.controllers.modal.AjaxModalHandler);


	//
	// Private properties
	//
	/**
	 * The url used to fetch content to be displayed.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.url_ = null;


	//
	// Protected methods.
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.modalOpen =
			function(dialogElement) {
		// Make sure that the modal will remain on screen.
		var $dialogElement = $(dialogElement),
				url, callback;

		$dialogElement.css({'max-height': 600, 'overflow-y': 'auto',
			'z-index': '10000'});

		// Retrieve remote modal content.
		url = $dialogElement.dialog('option' , 'url');
		this.url_ = url;
		callback = this.callbackWrapper(this.refreshModalCallback_);
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
	 * @param {string?} url The url to fetch new content.
	 * @param {string|null=} opt_content The already fetched content to be shown.
	 * @param {boolean=} opt_submit Use the url to submit the forms inside this
	 *  modal?
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.refreshModal_ =
			function(url, opt_content, opt_submit) {

		var $dialogElement = $(this.getHtmlElement()),
				responseHandler, $forms;

		if (url) {
			// Store the url that was used to fetch the content.
			this.url_ = url;
			responseHandler = this.callbackWrapper(this.handleResponse_);

			// We want to submit a form?
			if (opt_submit) {
				// Get all forms in modal to serialize them.
				$forms = $('form', this.getHtmlElement());

				// Post the forms data using the passed url.
				$.post(url, $forms.serialize(), responseHandler, 'json');
			} else {
				// Fetch new content.
				$.getJSON(url, responseHandler);
			}
		} else if (opt_content) {
			// Replace the modal content.
			$dialogElement.html(opt_content);

			// Call the refresh modal callback to bind events and transform
			// html elements again.
			this.refreshModalCallback_();
		}
	};


	/**
	 * Modal refresh callback. Transform html elements.
	 * @private
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.
			refreshModalCallback_ = function() {
		var $dialogElement = this.getHtmlElement(),
				// Fix modal title.
				$newTitle = $('h2', $dialogElement),
				$currentTitle, $buttons, $menu, $oldMenu, $newMenu,
				$links, clickLinkHandler, bindEventsCallback,
				$formElements, url, pageAnchor;

		if ($newTitle.length > 0) {
			$currentTitle = $dialogElement.parent()
					.find('.pkp_controllers_modal_titleBar h2');
			$currentTitle.replaceWith($newTitle);
		}

		// Insert the class to identify legacy plugin content.
		$dialogElement.addClass('legacy_plugin_content');

		// Transform buttons.
		$buttons = $(':submit, :button, :reset');
		if ($buttons.length > 0) {
			$buttons.button();
		}

		// Transform menu links.
		$menu = $('.menu', $dialogElement);
		if ($menu.length > 0) {
			$oldMenu = $.extend(true, {}, $menu);
			$newMenu = $('<div class="menu"></div>');
			$menu.replaceWith($newMenu);
			$menu = $('.menu');
			$menu.append($oldMenu);

			$menu.addClass(
					'ui-tabs ui-widget ui-widget-content ui-corner-all');
			$menu.children().addClass(
					'ui-tabs-nav ui-helper-reset ui-helper-clearfix ' +
					'ui-widget-header ui-corner-all');
			$menu.children().children().
					addClass('ui-state-default ui-corner-top');
			$menu.children().find('.current').
					addClass('ui-tabs-selected ui-state-active');
		}

		// Bind click handlers.
		$links = $('a', this.getHtmlElement());
		clickLinkHandler = this.callbackWrapper(this.clickLinkHandler_);
		bindEventsCallback = this.callbackWrapper(this.bindClickEvent_);
		$links.each(function(index, element) {
			bindEventsCallback(element, clickLinkHandler);
		});

		// Bind form submit handlers.
		$formElements = $('form.pkp_form', $dialogElement);
		if ($formElements.length > 0) {
			$formElements.bind('submit', this.callbackWrapper(this.submitFormHandler_));
			$formElements.find('#cancelFormButton').unbind('click');
			$formElements.find('#cancelFormButton').
					bind('click', this.callbackWrapper(this.modalClose));
		}

		// Reset the scrolling of the modal.
		$dialogElement.scrollTop(0);

		// Go to the anchor defined in url that fetched the content, if any.
		url = this.url_;
		if (url.match('#')) {
			pageAnchor = url.split('#')[1];

			// Scroll always down (because of the scroll reseting) to the page anchor.
			$dialogElement.scrollTop($('a[name="' + pageAnchor + '"]').position().top);
		}

		// Notify user with any existing notification.
		this.trigger('notifyUser', [this.getHtmlElement()]);
	};


	/**
	 * Refresh modal event handler.
	 *
	 * @private
	 * @param {jQueryObject} $context Wrapping element.
	 * @param {HTMLElement} modalHtmlElement Modal element.
	 * @param {Event} event Incoming event.
	 * @param {string} url Modal refresh URL.
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.
			prototype.refreshModalHandler_ =
			function($context, modalHtmlElement, event, url) {
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
	 * Binds a handler function to the click event of a passed
	 * link element.
	 *
	 * @private
	 * @param {Object} contextElement The context element.
	 * @param {Object} linkElement The link HTML element.
	 * @param {Function} clickLinkHandler The function to be called when
	 * the click event is triggered.
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.bindClickEvent_ =
			function(contextElement, linkElement, clickLinkHandler) {
		var $link = $(linkElement),
				onclickHandler;

		// Check for the presence of scripts defined inside
		// the element tag, as attributes.
		if ($link.attr('onclick')) {
			// We have an event handler. Make sure this handler will be passed to
			// our click handler to be executed too.
			onclickHandler = $link.attr('onclick');

			// We don't want the onclick handler being executed twice.
			$link.removeAttr('onclick');

			// Bind our click handler, passing the onclick handler as parameter.
			$link.bind('click', function(event) {
				clickLinkHandler(event, onclickHandler);
			});
		} else {
			// We don't have onclick attribute defined. Just bind our click handler.
			$link.bind('click', clickLinkHandler);
		}
	};


	/**
	 * Link click event handler.
	 *
	 * @private
	 * @param {Object} contextElement Containing element.
	 * @param {Event} event Incoming event.
	 * @param {Object} onclickHandler On-click handler.
	 * @return {boolean} Event handling status.
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.
			clickLinkHandler_ = function(contextElement, event, onclickHandler) {
		event.preventDefault();

		// We want to make sure that every script defined inside the onclick
		// attribute will be executed first, and that we respect its return result.
		if (onclickHandler) {
			// We apply the onclickHandler back to its original context, the link.
			if (onclickHandler.apply(event.target) === false) {
				// Destroy the object.
				onclickHandler = null;
				// The onclick handler returned false, so we stop our click handler
				// execution here too.
				return false;
			}
			// The onclick handler returned true, just destroy the object and
			// continue with our click handler execution.
			onclickHandler = null;
		}

		// Get the element that triggered the event.
		var $link = $(event.target), url;

		$link.unbind('click');

		// Get the url of the link that triggered the event.
		url = /** @type {string} */ ($link.attr('href'));
		this.refreshModal_(url);

		return true;
	};


	/**
	 * Submit form event handler.
	 *
	 * @private
	 * @param {Object} form Form element.
	 * @param {Event} event Incoming event.
	 * @return {boolean} Event handling status.
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.
			submitFormHandler_ = function(form, event) {
		// Get the element that triggered the event.
		var $form = $(form), url;

		$form.unbind('submit');

		// Get the url of the form to submit via ajax.
		url = /** @type {string} */ ($form.attr('action'));
		this.refreshModal_(url, null, true);

		return false;
	};


	/**
	 * Ajax response handler.
	 *
	 * @private
	 * @param {Object} element Containing element.
	 * @param {Object} jsonData Server response.
	 */
	$.pkp.controllers.modal.AjaxLegacyPluginModalHandler.prototype.handleResponse_ =
			function(element, jsonData) {

		var processedJsonData = this.handleJson(jsonData);
		if (processedJsonData !== false) {
			if (processedJsonData.content !== '') {
				// The request returned content. Refresh modal replacing it.
				this.refreshModal_(null, processedJsonData.content);
			} else {
				if (processedJsonData.status &&
						processedJsonData.event.name === 'dataChanged') {
					this.trigger('formSubmitted');
				}
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

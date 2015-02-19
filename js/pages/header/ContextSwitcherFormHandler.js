/**
 * @defgroup js_pages_header
 */
/**
 * @file js/pages/header/ContextSwitcherFormHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextSwitcherFormHandler
 * @ingroup js_pages_header
 *
 * @brief Handler for the context switcher.
 *
 */
(function($) {

	/** @type {Object} */
	$.pkp.pages = $.pkp.pages || { header: { } };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.FormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.pages.header.ContextSwitcherFormHandler =
			function($form, options) {

		this.parent($form, options);

		$('select.applyPlugin', $form).selectBox();

		// Attach form elements events.
		$('#contextSwitcherSelect', $form).change(
				this.callbackWrapper(this.switchContextHandler_));
	};

	$.pkp.classes.Helper.inherits(
			$.pkp.pages.header.ContextSwitcherFormHandler,
			$.pkp.controllers.form.FormHandler);


	//
	// Private helper methods
	//
	/**
	 * Switch between contexts.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.pages.header.ContextSwitcherFormHandler.prototype.switchContextHandler_ =
			function(sourceElement, event) {

		var $sourceElement = $(sourceElement),
				link = $sourceElement.val();

		if (link !== '') {
			this.trigger('redirectRequested', [link]);
		}
	};


}(jQuery));

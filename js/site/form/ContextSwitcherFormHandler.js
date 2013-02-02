/**
 * @defgroup js_site_form
 */
/**
 * @file js/site/form/ContextSwitcherFormHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ContextSwitcherFormHandler
 * @ingroup js_site_form
 *
 * @brief Handler for the context switcher.
 *
 */
(function($) {

	/** @type {Object} */
	$.pkp.site = $.pkp.site ||
			{ form: { } };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.FormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.site.form.ContextSwitcherFormHandler =
			function($form, options) {

		this.parent($form, options);

		$('select.applyPlugin', $form).selectBox();

		// Attach form elements events.
		$('#contextSwitcherSelect', $form).change(
				this.callbackWrapper(this.switchContextHandler_));
	};

	$.pkp.classes.Helper.inherits(
			$.pkp.site.form.ContextSwitcherFormHandler,
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
	$.pkp.site.form.ContextSwitcherFormHandler.prototype.switchContextHandler_ =
			function(sourceElement, event) {

		var $sourceElement = $(sourceElement),
				link = $sourceElement.val();

		if (link !== '') {
			this.trigger('redirectRequested', [link]);
		}
	};


}(jQuery));

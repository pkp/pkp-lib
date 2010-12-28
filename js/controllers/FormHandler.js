/**
 * @defgroup js_controllers
 */
// Create the controllers namespace.
jQuery.pkp.controllers = jQuery.pkp.controllers || { };

/**
 * @file js/controllers/FormHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormHandler
 * @ingroup js_controllers
 *
 * @brief PKP form handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $form the wrapped HTML form element.
	 * @param {Object} options options to be passed
	 *  into the validator plug-in.
	 */
	$.pkp.controllers.FormHandler = function($form, options) {
		this.parent($form);

		// Check whether we really got a form.
		if (!$form.is('form')) {
			throw Error(['A FormHandler controller can only be bound',
				' to an HTML form element!'].join(''));
		}

		// Activate and configure the validation plug-in.
		$form.validate({
			submitHandler: this.callbackWrapper(this.handleSubmit)
		});
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.FormHandler, $.pkp.classes.Handler);


	//
	// Public static methods
	//
	/**
	 * Internal callback called after form validation to handle form
	 * submission.
	 *
	 * You can override this handler if you want to do custom validation
	 * before you submit the form.
	 *
	 * @param {Object} validator The validator plug-in.
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 */
	$.pkp.controllers.FormHandler.prototype.handleSubmit =
			function(validator, formElement) {

		// The default implementation will post the form,
		// and act depending on the returned JSON message.
		var $form = this.getHtmlElement();
		$.post($form.attr('action'), $form.serialize(),
				this.callbackWrapper(this.handleResponse), 'json');
	};


	/**
	 * Internal callback called after form validation to handle form
	 * submission.
	 *
	 * You can override this handler if you want to do custom validation
	 * before you submit the form.
	 *
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 * @param {Object} jsonData The data returned from the server.
	 * @return {boolean} The response status.
	 */
	$.pkp.controllers.FormHandler.prototype.handleResponse =
			function(formElement, jsonData) {

		// The default implementation handles JSON errors.
		var $form = this.getHtmlElement();
		if (jsonData.status === true) {
			// Replace the form content.
			$form.replaceWith(jsonData.content);
		} else {
			// Display an error message.
			alert(jsonData.content);
		}
		return jsonData.status;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

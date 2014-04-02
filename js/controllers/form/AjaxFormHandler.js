/**
 * @file js/controllers/form/AjaxFormHandler.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AjaxFormHandler
 * @ingroup js_controllers_form
 *
 * @brief Form handler that submits the form to the server via AJAX and
 *  either replaces the form if it is re-rendered by the server or
 *  triggers the "formSubmitted" event after the server confirmed
 *  form submission.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.FormHandler
	 *
	 * @param {jQuery} $form the wrapped HTML form element.
	 * @param {Object} options options to configure the AJAX form handler.
	 */
	$.pkp.controllers.form.AjaxFormHandler = function($form, options) {
		options.submitHandler = this.submitForm;
		this.parent($form, options);

		this.bind('refreshForm', this.refreshFormHandler_);
		this.publishEvent('containerReloadRequested');
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.form.AjaxFormHandler,
			$.pkp.controllers.form.FormHandler);


	/**
	 * Overridden default from FormHandler -- disable form controls
	 * on AJAX forms by default.
	 * @private
	 * @type {Boolean}
	 */
	$.pkp.controllers.form.AjaxFormHandler.prototype.
			disableControlsOnSubmit_ = true;


	//
	// Public methods
	//
	/**
	 * Internal callback called after form validation to handle form
	 * submission.
	 *
	 * @param {Object} validator The validator plug-in.
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 */
	$.pkp.controllers.form.AjaxFormHandler.prototype.submitForm =
			function(validator, formElement) {

		// This form implementation will post the form,
		// and act depending on the returned JSON message.
		var $form = this.getHtmlElement();

		this.disableFormControls();

		$.post($form.attr('action'), $form.serialize(),
				this.callbackWrapper(this.handleResponse), 'json');
	};


	/**
	 * Callback to replace the element's content.
	 *
	 * @private
	 *
	 * @param {$jQuery} sourceElement The containing element.
	 * @param {Event} event The calling event.
	 * @param {String} content The content to replace with.
	 */
	$.pkp.controllers.form.AjaxFormHandler.prototype.refreshFormHandler_ =
			function(sourceElement, event, content) {

		if (content) {
			// Get the form that we're updating
			var $element = this.getHtmlElement();

			// Replace the form content
			$element.replaceWith(content);
		}
	};


	/**
	 * Internal callback called after form validation to handle the
	 * response to a form submission.
	 *
	 * You can override this handler if you want to do custom handling
	 * of a form response.
	 *
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 * @param {Object} jsonData The data returned from the server.
	 * @return {boolean} The response status.
	 */
	$.pkp.controllers.form.AjaxFormHandler.prototype.handleResponse =
			function(formElement, jsonData) {

		jsonData = this.handleJson(jsonData);
		if (jsonData !== false) {
			if (jsonData.content === '') {
				// Notify any nested formWidgets of form submitted event.
				var formSubmittedEvent = new $.Event('formSubmitted');
				$(this.getHtmlElement()).find('.formWidget').trigger(formSubmittedEvent);

				// Trigger the "form submitted" event.
				this.trigger('formSubmitted');

				// Fire off any other optional events.
				this.publishChangeEvents();
			} else {
				if (jsonData.reloadContainer !== undefined) {
					this.trigger('dataChanged');
					this.trigger('containerReloadRequested', jsonData);
					return jsonData.status;
				}

				// Redisplay the form.
				var $form = this.getHtmlElement();
				$form.replaceWith(jsonData.content);
			}
		}

		$(this.getHtmlElement()).find('.pkp_helpers_progressIndicator').hide();
		this.getHtmlElement().find(':submit').button('enable');

		// Trigger the notify user event, passing this
		// html element as data.
		this.trigger('notifyUser', this.getHtmlElement());

		return jsonData.status;
	};

/** @param {jQuery} $ jQuery closure. */
})(jQuery);

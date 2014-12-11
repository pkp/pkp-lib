/**
 * @defgroup js_controllers_form
 */
/**
 * @file js/controllers/form/FormHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FormHandler
 * @ingroup js_controllers_form
 *
 * @brief Abstract form handler.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.form = $.pkp.controllers.form || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {{
	 *  transformButtons: boolean,
	 *  submitHandler: Function,
	 *  cancelRedirectUrl: string,
	 *  disableControlsOnSubmit: boolean,
	 *  trackFormChanges: boolean,
	 *  enableDisablePairs: Object,
	 *  usernameSuggestionTextAlert: string
	 *  }} options options to configure the form handler.
	 */
	$.pkp.controllers.form.FormHandler = function($form, options) {
		this.parent($form, options);

		// Check whether we really got a form.
		if (!$form.is('form')) {
			throw new Error(['A form handler controller can only be bound',
				' to an HTML form element!'].join(''));
		}

		// Transform all form buttons with jQueryUI.
		if (options.transformButtons !== false) {
			$('.button', $form).button();
		}

		// Activate and configure the validation plug-in.
		if (options.submitHandler) {
			this.callerSubmitHandler_ = options.submitHandler;
		}

		// Set the redirect-to URL for the cancel button (if there is one).
		if (options.cancelRedirectUrl) {
			this.cancelRedirectUrl_ = options.cancelRedirectUrl;
		}

		// specific forms may override the form's default behavior
		// to warn about unsaved changes.
		if (typeof options.trackFormChanges !== 'undefined') {
			this.trackFormChanges = options.trackFormChanges;
		}

		// disable submission controls on certain forms.
		if (options.disableControlsOnSubmit) {
			this.disableControlsOnSubmit = options.disableControlsOnSubmit;
		}

		// Items that should enable or disable each other.
		if (options.enableDisablePairs) {
			this.enableDisablePairs_ = options.enableDisablePairs;
			this.setupEnableDisablePairs();
		}

		// Set data for suggesting usernames.  Both keys should be present.
		if (options.fetchUsernameSuggestionUrl &&
				options.usernameSuggestionTextAlert) {
			this.fetchUsernameSuggestionUrl_ = options.fetchUsernameSuggestionUrl;
			this.usernameSuggestionTextAlert_ = options.usernameSuggestionTextAlert;
		}

		var validator = $form.validate({
			onfocusout: this.callbackWrapper(this.onFocusOutValidation_),
			errorClass: 'error',
			highlight: function(element, errorClass) {
				$(element).parent().parent().addClass(errorClass);
			},
			unhighlight: function(element, errorClass) {
				$(element).parent().parent().removeClass(errorClass);
			},
			submitHandler: this.callbackWrapper(this.submitHandler_),
			showErrors: this.callbackWrapper(this.showErrors)
		});

		// Activate the cancel button (if present).
		$('[id^=\'cancelFormButton-\']', $form)
				.click(this.callbackWrapper(this.cancelForm));

		// Activate the reset button (if present).
		$('#resetFormButton', $form).click(this.callbackWrapper(this.resetForm));
		$form.find('.showMore, .showLess').bind('click', this.switchViz);

		// Attach handler to suggest username button (if present)
		$('[id^="suggestUsernameButton"]', $form).click(
				this.callbackWrapper(this.generateUsername));


		// Initial form validation.
		if (validator.checkForm()) {
			this.trigger('formValid');
		} else {
			this.trigger('formInvalid');
		}

		this.initializeTinyMCE();

		// bind a handler to make sure tinyMCE fields are populated.
		$('#submitFormButton', $form).click(this.callbackWrapper(
				this.pushTinyMCEChanges_));

		// bind a handler to handle change events on input fields.
		// 1. For normal inputs...
		$(':input', $form).change(this.callbackWrapper(this.formChange));
		// 2. For other kinds of controls like listbuilders
		this.bind('formChange', this.callbackWrapper(this.formChange));

		// ensure that date picker modals are hidden when clicked away from.
		$form.click(this.callbackWrapper(this.hideDatepicker_));

		this.publishEvent('tinyMCEInitialized');
		this.bind('tinyMCEInitialized', this.tinyMCEInitHandler_);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.form.FormHandler,
			$.pkp.classes.Handler);


	//
	// Protected properties
	//
	/**
	 * If true, the FormHandler will disable the submit button if the form
	 * successfully validates and is submitted.
	 * @protected
	 * @type {boolean}
	 */
	$.pkp.controllers.form.FormHandler.prototype.disableControlsOnSubmit = false;


	/**
	 * By default, all FormHandler instances and subclasses track changes to
	 * form data.
	 * @protected
	 * @type {boolean}
	 */
	$.pkp.controllers.form.FormHandler.prototype.trackFormChanges = true;


	//
	// Private properties
	//
	/**
	 * If provided, the caller's submit handler, which will be
	 * triggered to save the form.
	 * @private
	 * @type {Function?}
	 */
	$.pkp.controllers.form.FormHandler.prototype.callerSubmitHandler_ = null;


	/**
	 * If provided, the URL to redirect to when the cancel button is clicked
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.form.FormHandler.prototype.cancelRedirectUrl_ = null;


	/**
	 * Only submit a track event for this form once.
	 * @type {boolean}
	 */
	$.pkp.controllers.form.FormHandler.prototype.formChangesTracked = false;


	/**
	 * An object containing items that should enable or disable each other.
	 * @private
	 * @type {Object?}
	 */
	$.pkp.controllers.form.FormHandler.prototype.enableDisablePairs_ = null;


	/**
	 * The URL to be called to fetch a username suggestion.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.form.FormHandler.
			prototype.fetchUsernameSuggestionUrl_ = '';


	/**
	 * The message that will be displayed if users click on suggest
	 * username button with no data in lastname.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.form.FormHandler.
			prototype.usernameSuggestionTextAlert_ = '';


	//
	// Public methods
	//
	/**
	 * Internal callback called whenever the validator has to show form errors.
	 *
	 * @param {Object} validator The validator plug-in.
	 * @param {Object} errorMap An associative list that attributes
	 *  element names to error messages.
	 * @param {Array} errorList An array with objects that contains
	 *  error messages and the corresponding HTMLElements.
	 */
	$.pkp.controllers.form.FormHandler.prototype.showErrors =
			function(validator, errorMap, errorList) {

		// ensure that rich content elements have their
		// values stored before validation.
		if (typeof tinyMCE !== 'undefined') {
			tinyMCE.EditorManager.triggerSave();
		}

		// Clone the form validator before checking the entire form.
		// We need to clone otherwise the internal validator error
		// list will be changed when the show errors timeout is
		// executed, showing/hiding the wrong errors.
		var validatorClone = $.extend(true, {}, validator);

		// Show errors generated by the form change.
		// Use a timer so we make sure that concurrent triggered events
		// are handled before the error messages appear in the UI.
		//
		// The main issue  is a click event in cancel buttons while a non
		// valid field is focused. Without the timer, the UI is changed
		// before the click action is complete (the mouse up will not occur in
		// the cancel link, because it will be moved by the error messages).
		setTimeout(this.callbackWrapper(function() {
			validatorClone.defaultShowErrors();
			validatorClone = null;
		}), 250);

		// Emit validation events.
		if (validator.checkForm()) {
			// Trigger a "form valid" event.
			this.trigger('formValid');
		} else {
			// Trigger a "form invalid" event.
			this.trigger('formInvalid');
			this.enableFormControls();
		}
	};


	/**
	 * Internal callback called when a form element changes.
	 *
	 * @param {HTMLElement} formElement The form element that generated the event.
	 * @param {Event} event The formChange event.
	 */
	$.pkp.controllers.form.FormHandler.prototype.formChange =
			function(formElement, event) {
		if (this.trackFormChanges && !this.formChangesTracked) {
			this.formChangesTracked = true;
			this.trigger('formChanged');
		}
	};


	//
	// Protected methods
	//
	/**
	 * Protected method to disable a form's submit control if it is
	 * desired.
	 *
	 * @return {boolean} true.
	 * @protected
	 */
	$.pkp.controllers.form.FormHandler.prototype.disableFormControls =
			function() {

		// We have made it to submission, disable the form control if
		// necessary, submit the form.
		if (this.disableControlsOnSubmit) {
			this.getHtmlElement().find(':submit').attr('disabled', 'disabled').
					addClass('ui-state-disabled');
		}
		return true;
	};


	/**
	 * Protected method to reenable a form's submit control if it is
	 * desired.
	 *
	 * @return {boolean} true.
	 * @protected
	 */
	$.pkp.controllers.form.FormHandler.prototype.enableFormControls =
			function() {

		this.getHtmlElement().find(':submit').removeAttr('disabled').
				removeClass('ui-state-disabled');
		return true;
	};


	/**
	 * Internal callback called to cancel the form.
	 *
	 * @param {HTMLElement} cancelButton The cancel button.
	 * @param {Event} event The event that triggered the
	 *  cancel button.
	 * @return {boolean} false.
	 */
	$.pkp.controllers.form.FormHandler.prototype.cancelForm =
			function(cancelButton, event) {

		this.unregisterForm();
		this.trigger('formCanceled');
		return false;
	};


	/**
	 * Unregister form for tracking changed data.
	 */
	$.pkp.controllers.form.FormHandler.prototype.unregisterForm =
			function() {
		// Trigger the "form canceled" event and unregister the form.
		this.formChangesTracked = false;
		this.trigger('unregisterChangedForm');
	};


	/**
	 * Internal callback called to reset the form.
	 *
	 * @param {HTMLElement} resetButton The reset button.
	 * @param {Event} event The event that triggered the
	 *  reset button.
	 * @return {boolean} false.
	 */
	$.pkp.controllers.form.FormHandler.prototype.resetForm =
			function(resetButton, event) {

		//unregister the form.
		this.formChangesTracked = false;
		this.trigger('unregisterChangedForm');

		var $form = this.getHtmlElement();
		$form.each(function() {
			this.reset();
		});

		return false;
	};


	/**
	 * Configures the enable/disable pair bindings between a checkbox
	 * and some other form element.
	 *
	 * @return {boolean} true.
	 */
	$.pkp.controllers.form.FormHandler.prototype.setupEnableDisablePairs =
			function() {

		var formElement = this.getHtmlElement(), key;
		for (key in this.enableDisablePairs_) {
			$(formElement).find("[id^='" + key + "']").bind(
					'click', this.callbackWrapper(this.toggleDependentElement_));
		}
		return true;
	};


	/**
	 * Internal callback called to submit the form
	 * without further validation.
	 *
	 * @param {Object} validator The validator plug-in.
	 */
	$.pkp.controllers.form.FormHandler.prototype.submitFormWithoutValidation =
			function(validator) {

		// NB: When setting a submitHandler in jQuery's validator
		// plugin then the submit event will always be canceled and our
		// return value will be ignored (see the handle() method in the
		// validator plugin). The only way around this seems to be unsetting
		// the submit handler before calling the submit method again.
		validator.settings.submitHandler = null;
		this.disableFormControls();
		this.getHtmlElement().submit();
		this.formChangesTracked = false;
	};


	/**
	 * Event handler that is called when the suggest username button is clicked.
	 */
	$.pkp.controllers.form.FormHandler.prototype.
			generateUsername = function() {

		var $form = this.getHtmlElement(),
				firstName, lastName, fetchUrl;

		if ($('[id^="lastName"]', $form).val() === '') {
			// No last name entered; cannot suggest. Complain.
			alert(this.usernameSuggestionTextAlert_);
			return;
		}

		// Fetch entered names
		firstName = /** @type {string} */ $('[id^="firstName"]', $form).val();
		lastName = /** @type {string} */ $('[id^="lastName"]', $form).val();

		// Replace dummy values in the URL with entered values
		fetchUrl = this.fetchUsernameSuggestionUrl_.
				replace('FIRST_NAME_DUMMY', firstName).
				replace('LAST_NAME_DUMMY', lastName);

		$.get(fetchUrl, this.callbackWrapper(this.setUsername), 'json');
	};


	/**
	 * Check JSON message and set it to username, back on form.
	 * @param {HTMLElement} formElement The Form HTML element.
	 * @param {JSONType} jsonData The jsonData response.
	 */
	$.pkp.controllers.form.FormHandler.prototype.
			setUsername = function(formElement, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$form = this.getHtmlElement();

		if (processedJsonData === false) {
			throw new Error('JSON response must be set to true!');
		}

		$('[id^="username"]', $form).val(processedJsonData.content);
	};


	//
	// Private Methods
	//
	/**
	 * Internal callback called after form validation to handle form
	 * submission.
	 *
	 * NB: Returning from this method without explicitly submitting
	 * the form will cancel form submission.
	 *
	 * @private
	 *
	 * @param {Object} validator The validator plug-in.
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 */
	$.pkp.controllers.form.FormHandler.prototype.submitHandler_ =
			function(validator, formElement) {

		// Notify any nested formWidgets of the submit action.
		var formSubmitEvent = new $.Event('formSubmitRequested');
		$(formElement).find('.formWidget').trigger(formSubmitEvent);

		// If the default behavior was prevented for any reason, stop.
		if (formSubmitEvent.isDefaultPrevented()) {
			return;
		}

		$(formElement).find('.pkp_helpers_progressIndicator').show();

		this.trigger('unregisterChangedForm');

		if (this.callerSubmitHandler_ !== null) {
			this.formChangesTracked = false;
			// A form submission handler (e.g. Ajax) was provided. Use it.
			this.callbackWrapper(this.callerSubmitHandler_).
					call(validator, formElement);
		} else {
			// No form submission handler was provided. Use the usual method.
			this.submitFormWithoutValidation(validator);
		}
	};


	/**
	 * Internal callback called to push TinyMCE changes back to fields
	 * so they can be validated.
	 *
	 * @param {HTMLElement} submitButton The submit button.
	 * @param {Event} event The event that triggered the
	 *  submit button.
	 * @return {boolean} true.
	 * @private
	 */
	$.pkp.controllers.form.FormHandler.prototype.pushTinyMCEChanges_ =
			function(submitButton, event) {

		// ensure that rich content elements have their
		// values stored before validation.
		if (typeof tinyMCE !== 'undefined') {
			tinyMCE.EditorManager.triggerSave();
		}
		return true;
	};


	/**
	 * Enables or disables the item which depends on the state of source of the
	 * Event.
	 * @param {HTMLElement} sourceElement The element which generated the event.
	 * @param {Event} event The event.
	 * @return {boolean} true.
	 * @private
	 */
	$.pkp.controllers.form.FormHandler.prototype.toggleDependentElement_ =
			function(sourceElement, event) {
		var formElement, elementId, targetElement;

		formElement = this.getHtmlElement();
		elementId = $(sourceElement).attr('id');
		targetElement = $(formElement).find(
				"[id^='" + this.enableDisablePairs_[elementId] + "']");

		if ($(sourceElement).is(':checked')) {
			$(targetElement).attr('disabled', '');
		} else {
			$(targetElement).attr('disabled', 'disabled');
		}

		return true;
	};


	/**
	 * Bind a blur handler on tinyMCE instances inside this form
	 * to call form validation on form elements that stores the correspondent
	 * tinyMCE editors.
	 * @private
	 * @param {HTMLElement} input The input element that triggered the
	 * event.
	 * @param {Event} event The tinyMCE initialized event.
	 * @param {Object} tinyMCEObject An array containing the tinyMCE object inside
	 * this multilingual element handler that was initialized.
	 */
	$.pkp.controllers.form.FormHandler.prototype.tinyMCEInitHandler_ =
			function(input, event, tinyMCEObject) {

		var editorId = tinyMCEObject.id;

		tinyMCEObject.on('blur', this.callbackWrapper(function(tinyMCEObject) {
			// Save the current tinyMCE value to the form element.
			tinyMCEObject.save();

			// Get the form element that stores the tinyMCE data.
			var $form = this.getHtmlElement(),
					formElement = $('#' + editorId, $form),
					// Validate only this element.
					validator = $form.validate();

			validator.element(formElement);
		}));
	};


	/**
	 * Blur event handler, attached to all input fields on this form
	 * by the form validator. It's passed as an option when initializing
	 * the validator.
	 *
	 * It will make sure that fields are always validated on blur. Without this
	 * users can delete data from a required field and move to another one
	 * without receiving any validation alert.
	 * @private
	 * @param {Object} validator Validator.
	 * @param {Object} element Element.
	 * @return {boolean} True so the blur event can still be handled.
	 */
	$.pkp.controllers.form.FormHandler.prototype.onFocusOutValidation_ =
			function(validator, element) {

		var $form = this.getHtmlElement();
		// Make sure the element is still present in form.
		if ($(element).parents('#' + $form.attr('id')).length) {
			validator.element(element);
		}

		return true;
	};


	/**
	 * Hide a date picker if a user clicks outside of the element.
	 * @private
	 * @param {Object} formElement Element.
	 * @param {Event} event The event.
	 */
	$.pkp.controllers.form.FormHandler.prototype.hideDatepicker_ =
			function(formElement, event) {

		var originalEvent, ele, form;

		originalEvent = event.originalEvent;
		ele = originalEvent.relatedTarget;

		form = this.getHtmlElement();
		if (!$(ele).hasClass('hasDatepicker') &&
				!$(ele).hasClass('ui-datepicker') &&
				!$(ele).hasClass('ui-icon') &&
				!$(ele).hasClass('ui-datepicker-next') &&
				!$(ele).hasClass('ui-datepicker-prev') &&
				!$(ele).parent().parents('.ui-datepicker').length) {
			$(form).find('.hasDatepicker').datepicker('hide');
		}
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

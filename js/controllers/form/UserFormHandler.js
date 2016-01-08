/**
 * @file js/controllers/form/UserFormHandler.js
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserFormHandler
 * @ingroup js_controllers_form
 *
 * @brief Add tools to the AjaxFormHandler facilitating user creation/editing.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {{
	 *  fetchUsernameSuggestionUrl: string,
	 *  usernameSuggestionTextAlert: string
	 *  }} options options to configure the form handler.
	 */
	$.pkp.controllers.form.UserFormHandler = function($form, options) {
		this.parent($form, options);

		// Set data for suggesting usernames.  Both keys should be present.
		if (options.fetchUsernameSuggestionUrl &&
				options.usernameSuggestionTextAlert) {
			this.fetchUsernameSuggestionUrl_ = options.fetchUsernameSuggestionUrl;
			this.usernameSuggestionTextAlert_ = options.usernameSuggestionTextAlert;
		}

		// Attach handler to suggest username button (if present)
		$('[id^="suggestUsernameButton"]', $form).click(
				this.callbackWrapper(this.generateUsername));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.form.UserFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called to fetch a username suggestion.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.form.UserFormHandler.
			prototype.fetchUsernameSuggestionUrl_ = '';


	/**
	 * The message that will be displayed if users click on suggest
	 * username button with no data in lastname.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.form.UserFormHandler.
			prototype.usernameSuggestionTextAlert_ = '';


	//
	// Protected methods
	//
	/**
	 * Event handler that is called when the suggest username button is clicked.
	 *
	 * @param {HTMLElement} el clicked by this event
	 * @param {Event} e triggered
	 */
	$.pkp.controllers.form.UserFormHandler.prototype.
			generateUsername = function(el, e) {

		// Don't submit the form!
		e.preventDefault();

		var $form = this.getHtmlElement(),
				firstName, lastName, fetchUrl;

		if ($('[name="lastName"]', $form).val() === '') {
			// No last name entered; cannot suggest. Complain.
			alert(this.usernameSuggestionTextAlert_);
			return;
		}

		// Fetch entered names
		firstName = /** @type {string} */ $('[name="firstName"]', $form).val();
		lastName = /** @type {string} */ $('[name="lastName"]', $form).val();

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
	$.pkp.controllers.form.UserFormHandler.prototype.
			setUsername = function(formElement, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$form = this.getHtmlElement();

		if (processedJsonData === false) {
			throw new Error('JSON response must be set to true!');
		}

		// Re-validate the field
		$('[id^="username"]', $form).val(processedJsonData.content)
				.trigger('blur');
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

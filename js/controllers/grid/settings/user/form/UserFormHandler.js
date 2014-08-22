/**
 * @defgroup js_controllers_grid_users_user_form User form javascript
 */
/**
 * @file js/controllers/grid/settings/user/form/UserFormHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserFormHandler
 * @ingroup js_controllers_grid_settings_user_form
 *
 * @brief Handle the user settings form.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.settings =
			$.pkp.controllers.grid.settings || { user: { form: { } }};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.settings.user.form.UserFormHandler =
			function($form, options) {

		this.parent($form, options);

		// Set data to private variables.
		this.fetchUsernameSuggestionUrl_ = options.fetchUsernameSuggestionUrl;
		this.usernameSuggestionTextAlert_ = options.usernameSuggestionTextAlert;

		// Attach form elements events.
		$('[id^="generatePassword"]', $form).click(
				this.callbackWrapper(this.setGenerateRandom));
		$('[id^="suggestUsernameButton"]', $form).click(
				this.callbackWrapper(this.generateUsername));

		// Check the generate password check box.
		if ($('[id^="generatePassword"]', $form).attr('checked')) {
			this.setGenerateRandom('[id^="generatePassword"]');
		}

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.settings.user.form.UserFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called to fetch a username suggestion.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.settings.user.form.UserFormHandler.
			prototype.fetchUsernameSuggestionUrl_ = '';


	/**
	 * The message that will be displayed if users click on suggest
	 * username button with no data in lastname.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.settings.user.form.UserFormHandler.
			prototype.usernameSuggestionTextAlert_ = '';


	//
	// Public methods.
	//
	/**
	 * @see AjaxFormHandler::submitForm
	 * @param {Object} validator The validator plug-in.
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 */
	$.pkp.controllers.grid.settings.user.form.UserFormHandler.prototype.
			submitForm = function(validator, formElement) {

		var $form = this.getHtmlElement();
		$(':password', $form).prop('disabled', false);
		this.parent('submitForm', validator, formElement);
	};


	/**
	 * Event handler that is called when generate password checkbox is
	 * clicked.
	 * @param {string} checkbox The checkbox input element.
	 */
	$.pkp.controllers.grid.settings.user.form.UserFormHandler.prototype.
			setGenerateRandom = function(checkbox) {

		// JQuerify the element
		var $checkbox = $(checkbox),
				$form = this.getHtmlElement(),
				passwordValue = '',
				activeAndCheck = 0;

		if ($checkbox.attr('checked')) {
			passwordValue = '********';
			activeAndCheck = 1;
		}
		$(':password', $form).
				attr('disabled', activeAndCheck).val(passwordValue);
		$('[id^="sendNotify"]', $form).attr('disabled', activeAndCheck).
				attr('checked', activeAndCheck);
	};


	/**
	 * Event handler that is called when the suggest username button is clicked.
	 */
	$.pkp.controllers.grid.settings.user.form.UserFormHandler.prototype.
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
	$.pkp.controllers.grid.settings.user.form.UserFormHandler.prototype.
			setUsername = function(formElement, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$form = this.getHtmlElement();

		if (processedJsonData === false) {
			throw new Error('JSON response must be set to true!');
		}

		$('[id^="username"]', $form).val(processedJsonData.content);
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

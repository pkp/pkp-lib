/**
 * @defgroup js_controllers_grid_users_reviewer_form
 */
/**
 * @file js/controllers/grid/users/reviewer/form/AddReviewerFormHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AddReviewerFormHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Handle the Add Reviewer form (and template for message body).
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer =
			$.pkp.controllers.grid.users.reviewer ||
			{ form: { } };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler = function($form, options) {

		this.parent($form, options);

		// Set the URL to retrieve templates from.
		if (options.templateUrl) {
			this.templateUrl_ = options.templateUrl;
		}

		// Store username suggestion details
		this.fetchUsernameSuggestionUrl_ = options.fetchUsernameSuggestionUrl;
		this.usernameSuggestionTextAlert_ = options.usernameSuggestionTextAlert;

		// Attach form elements events.
		$form.find('#template').change(
				this.callbackWrapper(this.selectTemplateHandler_));
		$('[id^="suggestUsernameButton"]', $form).click(
				this.callbackWrapper(this.generateUsername));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.form.
					AddReviewerFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called to fetch a username suggestion.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.fetchUsernameSuggestionUrl_ = '';


	/**
	 * The message that will be displayed if users click on suggest
	 * username button with no data in lastname.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.usernameSuggestionTextAlert_ = '';


	/**
	 * The URL to use to retrieve template bodies
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.templateUrl_ = null;


	//
	// Public methods
	//
	/**
	 * Event handler that is called when the suggest username button is clicked.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.generateUsername = function() {

		var $form = this.getHtmlElement(),
				firstName, lastName, fetchUrl;

		if ($('[id^="lastname"]', $form).val() === '') {
			// No last name entered; cannot suggest. Complain.
			alert(this.usernameSuggestionTextAlert_);
			return;
		}

		// Fetch entered names
		firstName = /** @type {string} */ $('[id^="firstname"]', $form).val();
		lastName = /** @type {string} */ $('[id^="lastname"]', $form).val();

		// Replace dummy values in the URL with entered values
		fetchUrl = this.fetchUsernameSuggestionUrl_.
				replace('FIRST_NAME_DUMMY', firstName).
				replace('LAST_NAME_DUMMY', lastName);

		$.get(fetchUrl, this.callbackWrapper(this.setUsername), 'json');
	};


	/**
	 * Check JSON message and set it to username, back on form.
	 * @param {HTMLElement} formElement The Form HTML element.
	 * @param {JSON} jsonData The jsonData response.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.setUsername =
			function(formElement, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$form = this.getHtmlElement();

		if (processedJsonData === false) {
			throw new Error('JSON response must be set to true!');
		}

		$('[id^="username"]', $form).val(processedJsonData.content);
	};


	//
	// Private methods
	//
	/**
	 * Respond to an "item selected" call by triggering a published event.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.selectTemplateHandler_ =
					function(sourceElement, event) {

		var $form = this.getHtmlElement();
		$.post(this.templateUrl_, $form.find('#template').serialize(),
				this.callbackWrapper(this.updateTemplate), 'json');
	};


	/**
	 * Internal callback to replace the textarea with the contents of the
	 * template body.
	 *
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 * @param {Object} jsonData The data returned from the server.
	 * @return {boolean} The response status.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.updateTemplate =
					function(formElement, jsonData) {

		var $form = this.getHtmlElement(),
				processedJsonData = this.handleJson(jsonData);

		if (processedJsonData !== false) {
			if (processedJsonData.content !== '') {
				$form.find('textarea[name="personalMessage"]')
						.val(processedJsonData.content);
			}
		}
		return processedJsonData.status;
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

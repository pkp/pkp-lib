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

		// Attach form elements events.
		$('[id^="generatePassword"]', $form).click(
				this.callbackWrapper(this.setGenerateRandom));

		// Check the generate password check box.
		if ($('[id^="generatePassword"]', $form).attr('checked')) {
			this.setGenerateRandom('[id^="generatePassword"]');
		}

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.settings.user.form.UserFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


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
		$(':password', $form).removeAttr('disabled');
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

		if ($checkbox.prop('checked')) {
			passwordValue = '********';
			activeAndCheck = 'disabled';
		} else {
			passwordValue = '';
			activeAndCheck = '';
		}
		$(':password', $form).
				prop('disabled', activeAndCheck).val(passwordValue);
		$('[id^="sendNotify"]', $form).attr('disabled', activeAndCheck).
				prop('checked', activeAndCheck);
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

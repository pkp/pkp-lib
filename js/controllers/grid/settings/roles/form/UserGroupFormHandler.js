/**
 * @defgroup js_controllers_grid_roles_form Role form javascript
 */
/**
 * @file js/controllers/grid/settings/roles/form/UserGroupFormHandler.js
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGroupFormHandler
 * @ingroup js_controllers_grid_settings_roles_form
 *
 * @brief Handle the role create/edit form.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.settings.roles =
			$.pkp.controllers.grid.settings.roles || { form: { }};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler =
			function($form, options) {

		var $roleId = $('[id^="roleId"]', $form);

		this.parent($form, options);

		// Set the role IDs for which the self-registration checkbox
		// is relevant.
		if (options.selfRegistrationRoleIds) {
			this.selfRegistrationRoleIds_ = options.selfRegistrationRoleIds;
		}

		// Initialize the "permit self register" checkbox disabled
		// state based on the form's current selection
		this.updatePermitSelfRegistration(
				/** @type {string} */ ($roleId.val()));

		// ...and make sure it's updated when changing roles
		$roleId.change(this.callbackWrapper(this.changeRoleId));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The list of self-registration role IDs
	 * @private
	 * @type {Object?}
	 */
	$.pkp.controllers.grid.settings.roles.form.
			UserGroupFormHandler.prototype.selfRegistrationRoleIds_ = null;


	//
	// Private methods.
	//
	/**
	 * Event handler that is called when the role ID dropdown is changed.
	 * @param {string} dropdown The dropdown input element.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler.prototype.
			changeRoleId = function(dropdown) {

		this.updatePermitSelfRegistration(
				/** @type {string} */ ($(dropdown).val()));
	};


	/**
	 * Update the enabled/disabled state of the permitSelfRegistration
	 * checkbox.
	 * @param {number} roleId The role ID to select.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler.prototype.
			updatePermitSelfRegistration = function(roleId) {

		// JQuerify the element
		var $checkbox = $('[id^="permitSelfRegistration"]'),
				$form = this.getHtmlElement(),
				i,
				found = false;

		for (i = 0; i < this.selfRegistrationRoleIds_.length; i++) {
			if (this.selfRegistrationRoleIds_[i] == roleId) {
				found = true;

			}
		}

		if (found) {
			$checkbox.removeAttr('disabled');
		} else {
			$checkbox.attr('disabled', 'disabled');
			$checkbox.removeAttr('checked');
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

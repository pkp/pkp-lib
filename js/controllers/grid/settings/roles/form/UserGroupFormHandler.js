/**
 * @defgroup js_controllers_grid_roles_form Role form javascript
 */
/**
 * @file js/controllers/grid/settings/roles/form/UserGroupFormHandler.js
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
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
	 * @param {{
	 *   groupOptionRestrictions: Array,
	 *   roleForbiddenStagesJSON: Object.<string, *>,
	 *   stagesSelector: string
	 *   }} options form options.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler =
			function($form, options) {

		var $roleId = $('[id^="roleId"]', $form);

		this.parent($form, options);

		if (options.groupOptionRestrictions) {
			this.groupOptionRestrictions_ = options.groupOptionRestrictions;
		}

		this.roleForbiddenStages_ = options.roleForbiddenStagesJSON.content;
		this.stagesSelector_ = options.stagesSelector;

		this.updateGroupOptionRestrictions(
			/** @type {string} */ ($roleId.val()));

		// ...also initialize the stage options, disabling the ones
		// that are forbidden for the current role.
		this.updateStageOptions(
				/** @type {string} */ ($roleId.val()));

		// ...and make sure both it's updated when changing roles.
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
			UserGroupFormHandler.prototype.groupOptionRestrictions_ = null;


	/**
	 * A list of role forbidden stages.
	 * @private
	 * @type {Object}
	 */
	$.pkp.controllers.grid.settings.roles.form.
			UserGroupFormHandler.prototype.roleForbiddenStages_ = null;


	/**
	 * The stage options selector.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.settings.roles.form.
			UserGroupFormHandler.prototype.stagesSelector_ = null;


	//
	// Private methods.
	//
	/**
	 * Event handler that is called when the role ID dropdown is changed.
	 * @param {string} dropdown The dropdown input element.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler.prototype.
			changeRoleId = function(dropdown) {

		var dropDownValue = $(dropdown).val(); /** @type {string} */

		this.updateGroupOptionRestrictions(/** @type {string} */ (dropdownValue));

		// Also update the stages options.
		this.updateStageOptions(/** @type {string} */ (dropDownValue));

	};


	/**
	 * Update the enabled/disabled state of group options based on the selected
	 * role
	 * @param {number|string} roleId The role ID to select.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler.prototype.
			updateGroupOptionRestrictions = function(roleId) {

		var $form = this.getHtmlElement(),
				$checkbox,
				i,
				r;

		roleId = typeof roleId === 'string' ? parseInt(roleId, 10) : roleId;

		for (i in this.groupOptionRestrictions_) {
			$checkbox = $('[id^="' + i + '"]', $form);
			if ($checkbox.length) {
				if (this.groupOptionRestrictions_[i].indexOf(roleId) > -1) {
					$checkbox.removeAttr('disabled');
				} else {
					$checkbox.attr('disabled', 'disabled');
					$checkbox.removeAttr('checked');
				}
			}
		}
	};


	/**
	 * Update the stage options.
	 * @param {number} roleId The role ID to select.
	 */
	$.pkp.controllers.grid.settings.roles.form.UserGroupFormHandler.prototype.
			updateStageOptions = function(roleId) {

		// JQuerify the element
		var $htmlElement = this.getHtmlElement(),
				$stageContainer = $htmlElement.find('#userGroupStageContainer'),
				$stageOptions = $(this.stagesSelector_, $htmlElement).filter('input'),
				i,
				stageId = null;

		$stageOptions.removeAttr('disabled');

		if (this.roleForbiddenStages_[roleId] != undefined) {
			for (i = 0; i < this.roleForbiddenStages_[roleId].length; i++) {
				stageId = this.roleForbiddenStages_[roleId][i];
				$stageOptions.filter('input[value="' + stageId + '"]').
						attr('disabled', 'disabled');
			}
		}

		if ($htmlElement.find(
				'input[id^=\'assignedStages-\']:enabled').length == 0) {
			$stageContainer.hide('slow');
			$('#showTitle').attr('disabled', 'disabled');
		} else {
			$stageContainer.show('slow');
			$('#showTitle').removeAttr('disabled');
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

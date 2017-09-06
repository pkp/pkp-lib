/**
 * @defgroup js_controllers_grid_users_stageParticipant_form
 */
/**
 * @file js/controllers/grid/users/stageParticipant/form/StageParticipantNotifyHandler.js
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantNotifyHandler
 * @ingroup js_controllers_grid_users_stageParticipant_form
 *
 * @brief Handle Stage participant notification forms.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.users.stageParticipant.form =
			$.pkp.controllers.grid.users.stageParticipant.form || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.users.stageParticipant.form.
			StageParticipantNotifyHandler = function($form, options) {

		this.parent($form, options);

		// Set the URL to retrieve templates from.
		if (options.templateUrl) {
			this.templateUrl_ = options.templateUrl;
		}

		// Set the user group IDs with the recommendOnly possibility
		if (options.possibleRecommendOnlyUserGroupIds) {
			this.possibleRecommendOnlyUserGroupIds_ =
					options.possibleRecommendOnlyUserGroupIds;
		}

		// Set the user group IDs with the recommendOnly option set
		if (options.recommendOnlyUserGroupIds) {
			this.recommendOnlyUserGroupIds_ = options.recommendOnlyUserGroupIds;
		}

		// Update the recommendOnly option display when user group changes
		// or user is selected
		$('input[name=\'userGroupId\'], input[name=\'userIdSelected\']', $form)
				.change(this.callbackWrapper(this.updateRecommendOnly));

		// Attach form elements events.
		$form.find('#template').change(
				this.callbackWrapper(this.selectTemplateHandler_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.stageParticipant.form.
					StageParticipantNotifyHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to use to retrieve template bodies
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.users.stageParticipant.form.
			StageParticipantNotifyHandler.prototype.templateUrl_ = null;


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
	$.pkp.controllers.grid.users.stageParticipant.form.
			StageParticipantNotifyHandler.prototype.selectTemplateHandler_ =
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
	$.pkp.controllers.grid.users.stageParticipant.form.
			StageParticipantNotifyHandler.prototype.updateTemplate =
					function(formElement, jsonData) {

		var $form = this.getHtmlElement(),
				processedJsonData = this.handleJson(jsonData),
				jsonDataContent =
				/** @type {{variables: Object, body: string}} */ (jsonData.content),
				$textarea = $form.find('textarea[name="message"]'),
				editor =
				tinyMCE.EditorManager.get(/** @type {string} */ ($textarea.attr('id')));

		$textarea.attr('data-variables', JSON.stringify(jsonDataContent.variables));
		editor.setContent(jsonDataContent.body);

		return processedJsonData.status;
	};


	/**
	 * Update the enabled/disabled and checked state of the recommendOnly checkbox.
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 */
	$.pkp.controllers.grid.users.stageParticipant.form.
			StageParticipantNotifyHandler.prototype.updateRecommendOnly =
			function(sourceElement, event) {

		var $form = this.getHtmlElement(),
				$filterUserGroupId = $form.find('input[name=\'userGroupId\']'),
				$checkbox = $form.find('input[id^=\'recommendOnly\']'),
				$checkboxDiv = $form.find('.recommendOnlyWrapper'),
				i,
				found = false,
				filterUserGroupIdVal = /** @type {string} */ $filterUserGroupId.val();

		// If user group changes, hide the recommendOnly option
		if ($(sourceElement).prop('name') == 'userGroupId') {
			$checkbox.attr('disabled', 'disabled');
			$checkbox.removeAttr('checked');
			$checkboxDiv.hide();
		} else if ($(sourceElement).prop('name') == 'userIdSelected' &&
				!$checkboxDiv.is(':visible')) {
			// Display recommendOnly option if
			// an user group with a possible recommendOnly option is selected
			for (i = 0; i < this.possibleRecommendOnlyUserGroupIds_.length; i++) {
				if (this.possibleRecommendOnlyUserGroupIds_[i] == filterUserGroupIdVal) {
					$checkbox.removeAttr('disabled');
					$checkboxDiv.show();
					// Select the recommendOnly option if
					// an user group with a recommendOnly option set is selected
					for (i = 0; i < this.recommendOnlyUserGroupIds_.length; i++) {
						if (this.recommendOnlyUserGroupIds_[i] == filterUserGroupIdVal) {
							$checkbox.prop('checked', true);
							break;
						}
					}
					break;
				}
			}
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
	$.pkp.controllers.grid.users.stageParticipant.form.
			StageParticipantNotifyHandler.prototype.handleResponse =
			function(formElement, jsonData) {

		// Reload the query grid to show the newly created query.
		$.pkp.classes.Handler.getHandler($('#queriesGrid .pkp_controllers_grid'))
				.trigger('dataChanged');

		return /** @type {boolean} */ (this.parent(
				'handleResponse', formElement, jsonData));
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

/**
 * @file js/controllers/grid/users/reviewer/form/ReviewReminderFormHandler.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminderFormHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Handle the review reminder form (template selection / ajax submit).
 */
(function ($) {

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer =
		$.pkp.controllers.grid.users.reviewer || {};

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer.form =
		$.pkp.controllers.grid.users.reviewer.form || {};

	/**
	 * @constructor
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form
	 * @param {Object} options
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			ReviewReminderFormHandler = function ($form, options) {
		this.parent($form, options);

		if (options.templateUrl) {
			this.templateUrl_ = options.templateUrl;
		}

		$form.find('#template')
			.change(this.callbackWrapper(this.selectTemplateHandler_));
	};

	$.pkp.classes.Helper.inherits(
		$.pkp.controllers.grid.users.reviewer.form.ReviewReminderFormHandler,
		$.pkp.controllers.form.AjaxFormHandler
	);

	/**
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.users.reviewer.form.ReviewReminderFormHandler.
			prototype.templateUrl_ = null;

	/**
	 * @private
	 */
	$.pkp.controllers.grid.users.reviewer.form.ReviewReminderFormHandler.
			prototype.selectTemplateHandler_ = function () {
		var $form = this.getHtmlElement();

		$.post(
			this.templateUrl_,
			$form.find('#template').serialize(),
			this.callbackWrapper(this.updateTemplate_),
			'json'
		);
	};

	/**
	 * @private
	 * @param {HTMLElement} formElement
	 * @param {Object} jsonData
	 * @return {boolean}
	 */
	$.pkp.controllers.grid.users.reviewer.form.ReviewReminderFormHandler.
			prototype.updateTemplate_ = function (formElement, jsonData) {
		var $form = this.getHtmlElement();
		var processedJsonData = this.handleJson(jsonData);
		if (!processedJsonData.status) {
			return false;
		}

		var content = jsonData.content || {};
		var body = content.body || '';

		var $textarea = $form.find('textarea[name="message"]');
		var editor = tinyMCE.EditorManager.get($textarea.attr('id'));

		if (content.variables) {
			$textarea.attr('data-variables', JSON.stringify(content.variables));
		}

		if (editor) {
			editor.setContent(body);
		} else {
			$textarea.val(body);
		}

		return processedJsonData.status;
	};

})(jQuery);

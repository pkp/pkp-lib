/**
 * @file js/controllers/grid/users/reviewer/form/ReviewerActionFormHandler.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerActionFormHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Handle reviewer action forms (unassign, reinstate, send reminder,
 *  etc.) that let the user pick an email template and preview/edit its
 *  compiled message body.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer =
			$.pkp.controllers.grid.users.reviewer || {};

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer.form =
			$.pkp.controllers.grid.users.reviewer.form || {};


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			ReviewerActionFormHandler = function($form, options) {

		this.parent($form, options);

		// Set the URL to retrieve templates from.
		if (options.templateUrl) {
			this.templateUrl_ = options.templateUrl;
		}

		// Attach form elements events.
		$form.find('#template').change(
				this.callbackWrapper(this.selectTemplateHandler_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.form.
					ReviewerActionFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to use to retrieve template bodies
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			ReviewerActionFormHandler.prototype.templateUrl_ = null;


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
			ReviewerActionFormHandler.prototype.selectTemplateHandler_ =
					function(sourceElement, event) {

		var $form = this.getHtmlElement();
		$.post(this.templateUrl_, $form.find('#template').serialize(),
				this.callbackWrapper(this.updateTemplate), 'json');
	};


	/**
	 * Internal callback to replace the message textarea with the contents of
	 * the template body.
	 *
	 * The server may return the compiled body either as a plain string or as
	 * an object with `body` and `variables` properties, so both shapes are
	 * supported here.
	 *
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 * @param {Object} jsonData The data returned from the server.
	 * @return {boolean} The response status.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			ReviewerActionFormHandler.prototype.updateTemplate =
					function(formElement, jsonData) {

		var $form = this.getHtmlElement(),
				processedJsonData = this.handleJson(jsonData),
				content, body, variables, $textarea, editor;

		if (processedJsonData === false) {
			return false;
		}

		content = processedJsonData.content;
		if (content !== null && typeof content === 'object') {
			body = content.body || '';
			variables = content.variables;
		} else {
			body = content || '';
		}

		$textarea = $form.find('textarea.richContent').first();
		editor = tinyMCE.EditorManager.get(
				/** @type {string} */ ($textarea.attr('id')));

		if (variables) {
			$textarea.attr('data-variables', JSON.stringify(variables));
		}

		if (body !== '') {
			if (editor) {
				editor.setContent(body);
			} else {
				$textarea.val(body);
			}
		}

		return processedJsonData.status;
	};

}(jQuery));

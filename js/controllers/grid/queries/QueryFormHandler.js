/**
 * @defgroup js_controllers_grid_queries
 */
/**
 * @file js/controllers/grid/queries/QueryFormHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReadQueryHandler
 * @ingroup js_controllers_grid_queries
 *
 * @brief Handler for a query form modal
 *
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.queries =
			$.pkp.controllers.grid.queries || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.CancelActionAjaxFormHandler
	 *
	 * @param {jQueryObject} $form The query form element
	 * @param {Object} options non-default Dialog options
	 *  to be passed into the dialog widget.
	 *
	 *  Options are:
	 *  - all options documented for the CancelActionAjaxFormHandler.
	 *  - templateUrl: The URL to retrieve templates from.
	 */
	$.pkp.controllers.grid.queries.QueryFormHandler =
			function($form, options) {
		this.parent($form, options);

		// Set the URL to retrieve templates from.
		if (options.templateUrl) {
			this.templateUrl_ = options.templateUrl;
		}

		// Attach form elements events.
		$form.find('#template').change(
				this.callbackWrapper(this.selectTemplateHandler_));
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.queries.
			QueryFormHandler, $.pkp.controllers.form.CancelActionAjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to use to retrieve template bodies
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.queries.
			QueryFormHandler.prototype.templateUrl_ = null;


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
	$.pkp.controllers.grid.queries.
			QueryFormHandler.prototype.selectTemplateHandler_ =
					function(sourceElement, event) {
		var $form = this.getHtmlElement(),
				template = $form.find('[name="template"]');
		$.post(this.templateUrl_, template.serialize(),
				this.callbackWrapper(this.updateTemplate), 'json');
	};


	//
	// Private methods
	//
	/**
	 * Internal callback to replace the textarea with the contents of the
	 * template body.
	 *
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 * @param {Object} jsonData The data returned from the server.
	 * @return {boolean} The response status.
	 */
	$.pkp.controllers.grid.queries.
			QueryFormHandler.prototype.updateTemplate =
					function(formElement, jsonData) {

		var $form = this.getHtmlElement(),
				processedJsonData = this.handleJson(jsonData),
				jsonDataContent =
				/** @type {{variables: Object, body: string}} */ (jsonData.content),
				$textarea = $form.find('textarea[name="comment"]'),
				editor =
				tinyMCE.EditorManager.get(/** @type {string} */ ($textarea.attr('id')));

		if (jsonDataContent.variables) {
			$textarea.attr('data-variables', JSON.stringify(jsonDataContent.variables));
		}
		editor.setContent(jsonDataContent.body);
		$form.find('[name="subject"]').val(jsonDataContent.subject);

		return processedJsonData.status;
	};

}(jQuery));

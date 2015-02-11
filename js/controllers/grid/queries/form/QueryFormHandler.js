/**
 * @file js/controllers/grid/queries/form/WizardModalHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryFormHandler
 * @ingroup js_controllers_grid_queries_form
 *
 * @brief A Handler for controlling the Query form
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.queries =
			$.pkp.controllers.grid.queries || { form: { } } ;
	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object} options non-default Dialog options
	 *  to be passed into the dialog widget.
	 *
	 *  Options are:
	 *  - all options documented for the AjaxModalHandler.
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		// Store the options.
		this.deleteUrl_ = options.deleteUrl;
		this.queryId_ = options.queryId;

	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.queries.form.QueryFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);

	//
	// Private properties
	//
	/**
	 * The URL to be called when a cancel event occurs.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.
			prototype.deleteUrl_ = '';

	/**
	 * The id of our query object
	 * @private
	 * @type {int}
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.
			prototype.queryId_ = '';

	/** @inheritDoc */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.prototype.cancelForm =
			function(cancelButton, event) {

		$.post(this.deleteUrl_, this.queryId_,
				$.pkp.classes.Helper.curry(this.formCancelSuccess, this,
						cancelButton, event), 'json');
		return /** @type {boolean} */ (
				this.parent('cancelForm'));
	};

	/**
	 * Callback triggered when the deletion of a file after clicking
	 * the cancel button was successful.
	 *
	 * @param {HTMLElement} htmlElement The form's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The original event.
	 * @param {Object} jsonData The JSON data returned by the server on
	 *  file deletion.
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.
			prototype.formCancelSuccess = function(htmlElement, event, jsonData) {

		var processedJsonData = this.handleJson(jsonData);
		if (processedJsonData !== false) {
			// Cancel the wizard.
			this.trigger('wizardCancel');
		}
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

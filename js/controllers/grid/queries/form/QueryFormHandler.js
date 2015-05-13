/**
 * @file js/controllers/grid/queries/form/QueryFormHandler.js
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
			$.pkp.controllers.grid.queries || { form: { } };



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
	 *  - deleteUrl: The URL to POST to in order to delete the incomplete query.
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		// Store the options.
		this.deleteUrl_ = options.deleteUrl;
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.queries.form.
			QueryFormHandler, $.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called when a cancel event occurs to delete the
	 * incomplete query.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.
			prototype.deleteUrl_ = null;


	/**
	 * True iff the form is complete (i.e. a normal "Save" action is in progress).
	 * @private
	 * @type {boolean}
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.
			prototype.isComplete_ = false;


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.prototype.
			containerCloseHandler = function(input, event) {

		// If the form wasn't completed, delete the created query.
		if (!this.isComplete_ && this.deleteUrl_ !== null) {
			$.post(this.deleteUrl_);
		}

		return /** @type {boolean} */ (
				this.parent('containerCloseHandler', input, event));
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.grid.queries.form.QueryFormHandler.prototype.
			submitForm = function(validator, formElement) {

		// Flag the form as complete.
		this.isComplete_ = true;
		this.parent('submitForm', validator, formElement);
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

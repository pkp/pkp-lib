/**
 * @defgroup js_controllers_grid_filter_form
 */
// Create the namespace.
jQuery.pkp.controllers.grid.filter =
			jQuery.pkp.controllers.grid.filter ||
			{ form: { } };

/**
 * @file js/controllers/grid/filter/form/FilterFormHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class FilterFormHandler.js
 * @ingroup js_controllers_grid_filter_form
 *
 * @brief Handle the filter configuration form.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQuery} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.filter.form.FilterFormHandler =
			function($form, options) {

		this.parent($form, options);

		if (options.noMoreTemplates === true || options.filterTemplates === true) {
			this.disableFormControls();
			$(options.pulldownSelector).change(
					this.callbackWrapper(this.selectOptionHandler_));
			// When a selection is made from the pulldown, the form will
			// be replaced in the DOM with a new one. To prevent the modal
			// from being closed, absorb this event.
			if (options.filterTemplates) {
				this.bind('pkpRemoveHandler', this.callbackWrapper(this.removeHandler_));
			}
		}

		this.editFilterUrlTemplate_ = options.editFilterUrlTemplate;

	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.filter.form.FilterFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL template for the edit filter form.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.filter.form.FilterFormHandler.prototype.editFilterUrlTemplate_ = null;


	//
	// Private helper methods
	//
	/**
	 * Respond to a filter dropdown selection
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.controllers.grid.filter.form.FilterFormHandler.prototype.selectOptionHandler_ =
			function(sourceElement, event) {

		$(sourceElement).hide();
		$.get(this.editFilterUrlTemplate_
				.replace('DUMMY_FILTER_TEMPLATE_ID', $(sourceElement).val()),
				this.callbackWrapper(this.getFilterForm_), 'json');
	};


	/**
	 * Respond to a handler removal event
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.controllers.grid.filter.form.FilterFormHandler.prototype.removeHandler_ =
			function(sourceElement, event) {

		this.unbind('pkpRemoveHandler');
	};


	/**
	 * Set the list of available items.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.grid.filter.form.FilterFormHandler.prototype.getFilterForm_ =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);

		// Replace the current form with the new one.
		this.remove();
		this.getHtmlElement().replaceWith($(jsonData.content));
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

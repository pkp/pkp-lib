/**
 * @defgroup js_pages_submission
 */
/**
 * @file js/pages/submission/SubmissionCategoriesFilterHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionCategoriesFilterHandler
 * @ingroup js_pages_submission
 *
 * @brief A handler for the categories filter for submissions
 */
(function($) {

	/** @type {Object} */
	$.pkp.pages.submission =
			$.pkp.pages.submission || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $widgetWrapper An HTML element that contains the
	 *   widget.
	 * @param {Object} options Handler options.
	 */
	$.pkp.pages.submission.SubmissionCategoriesFilterHandler =
			function($widgetWrapper, options) {

		this.parent($widgetWrapper, options);
		var self = this;

		$('input:checkbox', $widgetWrapper).on('click',
				self.callbackWrapper(self.assignCategory));

		$('#searchCategories', $widgetWrapper).on('keyup',
				self.callbackWrapper(self.searchCategories));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.submission.SubmissionCategoriesFilterHandler,
			$.pkp.classes.Handler);


	//
	// Public methods
	//

	/**
	 * Assign/Unassign the category item and move it to correct element
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 */
	$.pkp.pages.submission.SubmissionCategoriesFilterHandler.
			prototype.assignCategory = function(sourceElement, event) {
		if ($(sourceElement).is(':checked')) {
			$(sourceElement).parents('li').appendTo('.assigned_categories');
		} else {
			$(sourceElement).parents('li').appendTo('.unassigned_categories');
		}
	};

	/**
	 * Search categories by text
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 */
	$.pkp.pages.submission.SubmissionCategoriesFilterHandler.
			prototype.searchCategories = function(sourceElement, event) {
		var self = this,
				filter = self.formatText(
						$(sourceElement).val());
		$('.categories_list li').filter(function() {
			var category = self.formatText(
					$('label', this).text());
			$(this).toggle(category.indexOf(filter) > -1);
		});
	};

	/**
	 * Remove accents for text and converts to uppercase
	 *
	 * @param {string} text The text that will be formatted
	 * for the categories search
	 * @return {string} The text formatted without accents and in uppercase.
	 */
	$.pkp.pages.submission.SubmissionCategoriesFilterHandler.
			prototype.formatText = function(text) {
		return text.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toUpperCase();
	};

}(jQuery));

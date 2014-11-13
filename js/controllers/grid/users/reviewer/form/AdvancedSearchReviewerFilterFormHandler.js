/**
 * @file js/controllers/grid/users/reviewer/form/AdvancedSearchReviewerFilterFormHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AdvancedSearchReviewerFilterFormHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Form handler providing slider functionality for the advanced reviewer
 *  search filter.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.ClientFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options options to be passed
	 *  into the validator plug-in.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AdvancedSearchReviewerFilterFormHandler = function($form, options) {
		this.parent($form, options);
		this.initSlider_('#completeRange', 0, 100,
				'[id^="doneMin-"]', '[id^="doneMax-"]');
		this.initSlider_('#activeRange', 0, 100,
				'[id^="activeMin-"]', '[id^="activeMax-"]');
		this.initSlider_('#averageRange', 0, 365,
				'[id^="avgMin-"]', '[id^="avgMax-"]');
		this.initSlider_('#lastRange', 0, 365,
				'[id^="lastMin-"]', '[id^="lastMax-"]');
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.form.
					AdvancedSearchReviewerFilterFormHandler,
			$.pkp.controllers.form.ClientFormHandler);


	//
	// Private member functions
	//
	/**
	 * Initialize a slider control.
	 * @private
	 * @param {string} selector The slider's selector.
	 * @param {number} min The minimum value possible.
	 * @param {number} max The maximum value possible.
	 * @param {string} minFieldSelector The target "minimum" field's selector.
	 * @param {string} maxFieldSelector The target "maximum" field's selector.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AdvancedSearchReviewerFilterFormHandler.prototype.
			initSlider_ = function(selector, min, max,
					minFieldSelector, maxFieldSelector) {
		var $minField = $(minFieldSelector), $maxField = $(maxFieldSelector);
		$(selector).slider({
			range: true,
			min: min,
			max: max,
			values: [$minField.val(), $maxField.val()],
			slide: function(event, ui) {
				$minField.val(ui.values[0]);
				$maxField.val(ui.values[1]);
			}
		});
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

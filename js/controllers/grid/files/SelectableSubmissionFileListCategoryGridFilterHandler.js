/**
 * @file js/controllers/grid/files/SelectableSubmissionFileListCategoryGridFilterHandler.js
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SelectableSubmissionFileListCategoryGridFilterHandler
 * @ingroup js_controllers_grid_files
 *
 * @brief Extension to ClientFormHandler that accepts a checkbox click as a
 *  submit action.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.ClientFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 */
	$.pkp.controllers.grid.files.
			SelectableSubmissionFileListCategoryGridFilterHandler =
			function($form) {
		this.parent($form, {trackFormChanges: false});
		$form.find('#allStages').click(
				this.callbackWrapper(this.allStagesHandler_));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.files.
					SelectableSubmissionFileListCategoryGridFilterHandler,
			$.pkp.controllers.form.ClientFormHandler);


	//
	// Private methods
	//
	/**
	 * Click handler for "all stages" checkbox.
	 * @private
	 * @return {boolean} Always returns true.
	 */
	$.pkp.controllers.grid.files.
			SelectableSubmissionFileListCategoryGridFilterHandler.
			prototype.allStagesHandler_ = function() {
		this.getHtmlElement().submit();
		return true;
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

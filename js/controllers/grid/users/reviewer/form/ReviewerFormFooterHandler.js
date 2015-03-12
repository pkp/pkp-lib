/**
 * @file js/controllers/grid/users/reviewer/form/ReviewerFormFooterHandler.js
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerFormFooterHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Handler for the reviewer form footer
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $footerElement the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			ReviewerFormFooterHandler = function($footerElement, options) {

		this.parent($footerElement, options);

		$('#filesAccordion').accordion({
			collapsible: true,
			active: false,
			heightStyle: 'content'
		});

		this.bind('expandFileList', this.handleExpandFileList_);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.form.
					ReviewerFormFooterHandler,
			$.pkp.classes.Handler);


	//
	// Private methods.
	//
	/**
	 * Handle the "expandFileList" event, triggered by external widgets
	 * to expand the file list in this widget.
	 * @private
	 */
	$.pkp.controllers.grid.users.reviewer.form.ReviewerFormFooterHandler.
			prototype.handleExpandFileList_ = function() {

		// If the "limit files" accordion is not already open, open it.
		var $accordion = this.getHtmlElement().find('#filesAccordion');
		if ($accordion.accordion('option', 'active') === false) {
			$accordion.accordion('option', 'active', '0');
		}
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

/**
 * @file js/controllers/grid/files/review/AuthorReviewRevisionsGridHandler.js
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewRevisionsGridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Author revisions grid handler.
 */
(function($) {

	/**
	 * Define the namespace
	 */
	$.pkp.controllers.grid.files = $.pkp.controllers.grid.files ||
			{ review: {} };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQueryObject} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.files.review.AuthorReviewRevisionsGridHandler = function(
			$grid, options) {
		this.parent($grid, options);
		this.bindGlobal('refreshRevisionsGrid', function() {
			this.refreshGridHandler();
		});
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.files.review.
			AuthorReviewRevisionsGridHandler, $.pkp.controllers.grid.GridHandler);


}(jQuery));

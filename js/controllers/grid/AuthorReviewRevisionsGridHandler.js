/**
 * @file js/controllers/grid/AuthorReviewRevisionsGridHandler.js
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewRevisionsGridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Author revisions grid handler.
 */
(function($) {

	// Define the namespace.
	$.pkp.controllers.grid.AuthorReviewRevisionsGridHandler = $.pkp.controllers.grid.AuthorReviewRevisionsGridHandler || {};


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQueryObject} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.AuthorReviewRevisionsGridHandler = function($grid, options) {
		this.parent($grid, options);
		this.bindGlobal('refreshRevisionsGrid', function() {
			this.refreshGridHandler();
		});
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.AuthorReviewRevisionsGridHandler,
			$.pkp.controllers.grid.GridHandler);


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

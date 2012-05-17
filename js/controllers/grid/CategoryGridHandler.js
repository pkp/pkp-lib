/**
 * @file js/controllers/grid/CategoryGridHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class CategoryGridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Category grid handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQuery} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.CategoryGridHandler = function($grid, options) {
		this.parent($grid, options);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.CategoryGridHandler,
			$.pkp.controllers.grid.GridHandler);


	//
	// Public methods.
	//
	/**
	 * Get category id prefix.
	 * @return {string} Category id prefix.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryIdPrefix =
			function() {
		return this.getGridIdPrefix() + '-category-';
	};


	/**
	 * Get categories tbody element.
	 * @return {jQuery} Category's tbody element.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategories =
			function() {
		return $('.category_grid_body:not(.empty)',
				this.getHtmlElement());
	};


	/**
	 * Get the category row inside a tbody category element.
	 * @param {jQuery} $category Category tbody element.
	 * @return {jQuery} Category row.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryRow =
			function($category) {
		return $('tr.category', $category);
	};

	/**
	 * Get the category empty placeholder.
	 * @param {jQuery} $category A grid category element.
	 * @return {jQuery} The category empty placeholder.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.
			getCategoryEmptyPlaceholder = function($category) {
		var selector = '#' + $category.attr('id') + '-emptyPlaceholder';
		return $(selector, this.getHtmlElement());
	};


	/**
	 * Get the category data id by the passed category element.
	 * @param {jQuery} $category Category element.
	 * @return {string} Category data id.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryDataId =
			function($category) {
		var categoryId = $category.attr('id');
		var startExtractPosition = this.getCategoryIdPrefix().length;
		return categoryId.slice(startExtractPosition);
	};


	/**
	 * Get the category data id by the passed row element id.
	 * @param {string} gridRowId Category row element id.
	 * @return {string} Category data id.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryDataIdByRowId =
			function(gridRowId) {
		var categoryDataId = gridRowId.match('category-(.*)-row');
		return categoryDataId[1];
	};


	/**
	 * Append a category to the end of the list.
	 * @param {jQuery} $category Category to append.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.appendCategory =
			function($category) {
		var $gridBody = this.getHtmlElement().find(this.bodySelector_);
		$gridBody.append($category);
	};


	/**
	 * Re-sequence all category elements based on the passed sequence map.
	 * @param {array} sequenceMap A sequence array with the category
	 * element id as value.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.resequenceCategories =
			function(sequenceMap) {
		var categoryId, index;
		for (index in sequenceMap) {
			categoryId = sequenceMap[index];
			var $category = $('#' + categoryId);
			this.appendCategory($category);
		}

		this.updateEmptyPlaceholderPosition();
	};


	/**
	 * Move all empty category placeholders to their correct position,
	 * below of each correspondent category element.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.
			updateEmptyPlaceholderPosition = function() {
		var $categories = this.getCategories();
		var index, limit;
		for (index = 0, limit = $categories.length; index < limit; index++) {
			var $category = $($categories[index]);
			var $emptyPlaceholder = this.getCategoryEmptyPlaceholder($category);
			if ($emptyPlaceholder.length > 0) {
				$emptyPlaceholder.insertAfter($category);
			}
		}
	};


	//
	// Extended methods from GridHandler
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.appendRow =
			function($newRow) {
		var $rowGridBody = this.getRowCategory_($newRow);
		this.parent('appendRow', $newRow, $rowGridBody);
	};


	/**
	 * Overridden from GridHandler.
	 * @inheritDoc
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.refreshGridHandler =
			function(sourceElement, event, elementId) {

		// FIXME #7394# make possible to refresh only categories and/or
		// rows inside categories.
		// Retrieve the whole grid from the server.
		$.get(this.fetchGridUrl_, null,
				this.callbackWrapper(this.replaceGridResponseHandler_), 'json');

		// Let the calling context (page?) know that the grids are being redrawn.
		this.trigger('gridRefreshRequested');
	};


	//
	// Private helper methods.
	//
	/**
	 * Get the correct tbody for the passed row.
	 * @param {jQuery} $row Row to fetch tbody for.
	 * @return {jQuery} JQuery tbody object.
	 * @private
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getRowCategory_ =
			function($row) {
		var categoryDataId = this.getCategoryDataIdByRowId($row.attr('id'));
		var categoryIdPrefix = this.getCategoryIdPrefix();
		return $('#' + categoryIdPrefix + categoryDataId);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

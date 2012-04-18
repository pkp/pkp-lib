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
	 * @return {string} 
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryIdPrefix =
			function () {
		return this.getGridIdPrefix() + '-category-'; 
	};
	
	
	/**
	 * Get categories tbody element.
	 * @returns {jQuery}
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategories = 
			function () {
		var gridIdPrefix = this.getGridIdPrefix();
		return $('tbody[id^="' + gridIdPrefix + '-category-' + '"]:not(.empty)', this.getHtmlElement());
	};
	
	
	/**
	 * Get the category row inside a tbody category element.
	 * @param {jQuery} $category 
	 * @return {jQuery}
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryRow =
			function($category) {
		return $('tr.category', $category);
	};
	
	
	/**
	 * Get the category data id by the passed category element.
	 * @param {jQuery} $category
	 * @return {string}
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryDataId =
			function($category) {
		var categoryId = $category.attr('id');
		var startExtractPosition = this.getCategoryIdPrefix().length;
		return categoryId.slice(startExtractPosition);
	};
	
	
	/**
	 * Get the category data id by the passed row element id. 
	 * @param {string} gridRowId
	 * @return {string}
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getCategoryDataIdByRowId = 
			function(gridRowId) {
		var categoryDataId = gridRowId.match("category-(.*)-row");
		return categoryDataId[1];
	};
	
	
	/**
	 * Append a category to the end of the list.
	 * @param {jQuery} $category
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.appendCategory = 
			function($category) {
		var $gridBody = this.getHtmlElement().find(this.bodySelector_);
		$gridBody.append($category);
	};
	
	
	/**
	 * Re-sequence all category elements based on the passed sequence map.
	 * @param {array} sequenceMap A sequence array with the category element id as value.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.resequenceCategories =
			function (sequenceMap) {
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
	$.pkp.controllers.grid.CategoryGridHandler.prototype.updateEmptyPlaceholderPosition =
			function() {
		var $categories = this.getCategories();
		var index, limit;
		for (index = 0, limit = $categories.length; index < limit; index++) {
			var $category = $($categories[index]);
			var $emptyPlaceholder = $('#' + $category.attr('id') + '-emptyPlaceholder', this.getHtmlElement());
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
		$rowGridBody = this.getRowCategory_($newRow);
		this.parent('appendRow', $newRow, $rowGridBody);
	};
	
	
	/**
	 * Overriden from GridHandler. 
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.refreshGridHandler_ =
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
	 * Add grid features.
	 * FIXME: #7379# this method should only exists in GridHandler. All the features
	 * configuration must be set on php side, when we implement the features 
	 * classes there.
	 * @private
	 * @param {Array} options The options array.
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.initFeatures_ =
			function(options) {
		var $orderItemsFeature =
				/** @type {$.pkp.classes.features.OrderItemsFeature} */
				($.pkp.classes.Helper.objectFactory(
						'$.pkp.classes.features.OrderCategoryGridItemsFeature',
						[this, {
							'orderButton': $('a.order_items', this.getHtmlElement()),
							'finishControl': $('#' + this.getGridIdPrefix() + '-order-finish-controls'),
							'saveItemsSequenceUrl': options.saveItemsSequenceUrl
						}]));

		this.features_ = {'orderItems': $orderItemsFeature};
		this.features_.orderItems.init();
	};
	
	
	/**
	 * Get the correct tbody for the passed row.
	 * @param {jQuery} $row
	 * @return {jQuery}
	 */
	$.pkp.controllers.grid.CategoryGridHandler.prototype.getRowCategory_ =
			function($row) {
		var categoryDataId = this.getCategoryDataIdByRowId($row.attr('id'));
		var categoryIdPrefix = this.getCategoryIdPrefix();
		return $('#' + categoryIdPrefix + categoryDataId);		
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

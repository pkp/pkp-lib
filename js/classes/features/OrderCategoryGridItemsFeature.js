/**
 * @file js/classes/features/OrderCategoryGridItemsFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderCategoryGridItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Feature for ordering category grid items.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature =
			function (gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderCategoryGridItemsFeature,
			$.pkp.classes.features.OrderGridItemsFeature);
	

	//
	// Extended methods from OrderItemsFeature.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.init =
			function() {
		this.parent('init');
		this.toggleMoveItemRowAction(false);				
	};
	
	
	//
	// Extended methods from OrderItemsFeature.
	//
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.setupSortablePlugin =
			function() {
		var $categories = this.gridHandler_.getCategories();

		var index, limit;
		for (index = 0, limit = $categories.length; index < limit; index++) {
			var $category = $($categories[index]);
			this.applySortablePluginOnElements($category, 'tr.orderable', null);
		}
		
		this.applySortablePluginOnElements(this.getGridHtmlElement(), 'tbody.orderable', null);		
	};
	

	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.saveOrderHandler =
			function() {
		this.gridHandler_.updateEmptyPlaceholderPosition();		
		return this.parent('saveOrderHandler');
	};

	
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.cancelOrderHandler =
			function() {
		this.parent('cancelOrderHandler');
		var categorySequence = this.getCategorySequence_(this.itemsOrder_);
		this.gridHandler_.resequenceCategories(categorySequence);
		
		return false;
	};
	
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.toggleItemsDragMode =
			function() {
		this.parent('toggleItemsDragMode');
		
		var isOrdering = this.isOrdering_;
		var $categories = this.gridHandler_.getCategories();
		
		var index, limit;
		for (index = 0, limit = $categories.length; index < limit; index++) {
			var $category = $($categories[index]);
			this.toggleCategoryDragMode_($category);
		}
	};
	
	
	//
	// Overriden method from OrderGridItemsFeature
	//	
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.getItemsDataId =
			function() {
		var categoriesSeq = this.getCategorySequence_(this.itemsOrder_);
		
		var itemsDataId = new Array();
		var index, limit;
		for (index = 0, limit = categoriesSeq.length; index < limit; index++) {
			var $category = $('#' + categoriesSeq[index]);
			var categoryRowsDataId = this.getRowsDataId($category);
			var categoryDataId = this.gridHandler_.getCategoryDataId($category);
			itemsDataId.push({'categoryId':categoryDataId, 'rowsId':categoryRowsDataId });
		}
		
		return itemsDataId; 
	};
	
	
	//
	// Private helper methods.
	//
	/**
	 * Enable/disable category drag mode.
	 * @param {jQuery} $category 
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.toggleCategoryDragMode_ =
			function($category) {
		var isOrdering = this.isOrdering_;
		var $categoryRow = this.gridHandler_.getCategoryRow($category);
		var $categoryRowColumn = $('td:first', $categoryRow);
		var moveClasses = this.getMoveItemClasses();
		
		if (isOrdering) {
			$categoryRowColumn.addClass(moveClasses);
		} else {
			$categoryRowColumn.removeClass(moveClasses);
		}
		
		this.toggleRowsInCategory_($category);
	};
	
	
	/**
	 * Show/hide non orderable rows inside the passed category.
	 * @param {jQuery} $category
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.toggleRowsInCategory_ =
			function($category) {
		var $nonOrderableRows = $('.gridRow:not(.orderable)', $category);
		$nonOrderableRows.toggle(300);		
	};
	
	/**
	 * Get the categories sequence, based on the passed items order.
	 * @param {Array} Items order. 
	 * @return {Array} A sequence array with the category data id as values.
	 */
	$.pkp.classes.features.OrderCategoryGridItemsFeature.prototype.getCategorySequence_ =
			function(itemsOrder) {
		var index, limit;
		var categorySequence = new Array();
		for (index = 0, limit = itemsOrder.length; index < limit; index++) {
			var categoryDataId = this.gridHandler_.getCategoryDataIdByRowId(itemsOrder[index]);
			var categoryId = this.gridHandler_.getCategoryIdPrefix() + categoryDataId;
			if ($.inArray(categoryId, categorySequence) > -1) continue;
			categorySequence.push(categoryId);
		}
		
		return categorySequence;
	};
	
	

/** @param {jQuery} $ jQuery closure. */
})(jQuery);

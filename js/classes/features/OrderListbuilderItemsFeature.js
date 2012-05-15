/**
 * @file js/classes/features/OrderListbuilderItemsFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderListbuilderItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Feature for ordering grid items.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderListbuilderItemsFeature,
			$.pkp.classes.features.OrderItemsFeature);


	//
	// Extended methods from OrderItemsFeature.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.addFeatureHtml =
			function($gridElement, options) {
		this.parent('addFeatureHtml', $gridElement, options);

		$itemSequenceInput = $('<input type="hidden" name="newRowId[sequence]" class="itemSequence" />');
		var $gridRows = this.gridHandler_.getRows();
		$gridRows.append($itemSequenceInput);
	};

	/**
	 * Set up the sortable plugin.
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.setupSortablePlugin =
			function() {
		this.applySortablePluginOnElements(this.getGridHtmlElement(), 'tr.orderable', null);
	};


	//
	// Extended methods from ToggleableOrderItemsFeature.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.init =
			function() {
		this.parent('init');
		this.toggleItemsDragMode();
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.toggleState =
			function(isOrdering) {
		this.parent('toggleState', isOrdering);
		this.toggleContentHandlers_();
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.storeRowOrder =
			function(index, $row) {
		var seq = index + 1;
		var $orderableInput = $row.find('.itemSequence');
		$orderableInput.attr('value', seq);
		var $modifiedInput = $row.find('.isModified');
		$modifiedInput.attr('value', 1);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.saveOrderHandler =
			function() {
		this.parent('saveOrderHandler');
		this.toggleState(false);
		return false;
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.updateOrderCallback =
			function(contextElement, event, ui) {
		this.parent('updateOrderCallback');
		var $rows = this.gridHandler_.getRows();
		this.storeOrder($rows);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.clickOrderHandler =
			function() {
		var $selects = $('select:visible', this.gridHandler_.getHtmlElement());
		if ($selects.length > 0) {
			var index, limit;
			for (index = 0, limit = $selects.length; index < limit; index++) {
				this.gridHandler_.saveRow($($selects[index]).parents('.gridRow'));
			}
		}

		this.parent('clickOrderHandler');
	};


	//
	// Implemented Feature template hook methods.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.appendRow =
			function($newRow) {
		this.toggleItemsDragMode();
		var $rows = this.gridHandler_.getRows();
		this.storeOrder($rows);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.replaceRow =
			function($newContent) {
		this.toggleItemsDragMode();
		var $rows = this.gridHandler_.getRows();
		this.storeOrder($rows);
	};


	//
	// Private helper methods.
	//
	/**
	 * Enable/disable row content handlers.
	 * @private
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.toggleContentHandlers_ =
			function() {
		var $rows = this.gridHandler_.getRows();
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			var $row = $($rows[index]);
			if (this.isOrdering_) {
				$row.find('.gridCellDisplay').unbind('click');
			} else {
				this.gridHandler_.attachContentHandlers_($row);
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

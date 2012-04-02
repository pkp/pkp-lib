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
			$.pkp.classes.features.ToggleableOrderItemsFeature);


	//
	// Extended methods from ToggleableOrderItemsFeature.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.init =
			function() {
		this.parent('init');
		this.toggleItemsDragMode(false);
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
	$.pkp.classes.features.OrderListbuilderItemsFeature.prototype.storeOrder =
			function($rows) {
		this.parent('storeOrder', $rows);
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			$row = $($rows[index]);
			var seq = index + 1;
			var orderableInput = $row.find('.itemSequence');
			orderableInput.attr('value', seq);
			var modifiedInput = $row.find('.isModified');
			modifiedInput.attr('value', 1);
		}
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
		$rows = this.gridHandler_.getRows();
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

/**
 * @file js/classes/features/OrderMultipleListsItemsFeature.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderMultipleListsItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Feature for ordering grid items.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderMultipleListsItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderMultipleListsItemsFeature,
			$.pkp.classes.features.OrderListbuilderItemsFeature);


	//
	// Extended methods from Feature.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderMultipleListsItemsFeature.prototype.addFeatureHtml =
			function($gridElement, options) {
		this.parent('addFeatureHtml', $gridElement, options);

		var $listInput = $('<input type="hidden" name="newRowId[listId]" ' +
				'class="itemList" />');
		var $gridRows = this.gridHandler_.getRows();
		var index, limit;
		for (index = 0, limit = $gridRows.length; index < limit; index++) {
			var $row = $($gridRows[index]);
			var listId = this.gridHandler_.getListIdByRow($row);
			var $listInputClone = $listInput.clone();
			$listInputClone.attr('value', listId);
			$('td.first_column', $row).append($listInputClone);
		}
	};


	//
	// Extended methods from OrderListbuilderItemsFeature.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderMultipleListsItemsFeature.prototype.storeRowOrder =
			function(index, $row) {
		this.parent('storeRowOrder', index, $row);

		var $listInput = $row.find('.itemList');
		var listId = this.gridHandler_.getListIdByRow($row);
		$listInput.attr('value', listId);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderMultipleListsItemsFeature.prototype.
			setupSortablePlugin = function() {
		var $lists = this.gridHandler_.getLists().find('tbody');
		var extraParams = {connectWith: $lists};
		this.applySortPlgOnElements($lists, 'tr.orderable', extraParams);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderMultipleListsItemsFeature.prototype.
			dragStartCallback = function(contextElement, event, ui) {
		var $list = this.gridHandler_.getListByRow(ui.item);
		this.gridHandler_.toggleListNoItemsRow(
				$list, 1, '.ui-sortable-placeholder, .ui-sortable-helper');
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderMultipleListsItemsFeature.prototype.
			dragStopCallback = function(contextElement, event, ui) {
		var $list = this.gridHandler_.getListByRow(ui.item);
		this.gridHandler_.toggleListNoItemsRow($list, 0, null);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

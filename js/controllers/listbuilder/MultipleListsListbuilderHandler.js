/**
 * @file js/controllers/listbuilder/MultipleListsListbuilderHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MultipleListsListbuilderHandler
 * @ingroup js_controllers_listbuilder
 *
 * @brief Multiple lists listbuilder handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.listbuilder.ListbuilderHandler
	 *
	 * @param {jQuery} $listbuilder The listbuilder this handler is
	 *  attached to.
	 * @param {Object} options Listbuilder handler configuration.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler =
			function($listbuilder, options) {
		this.parent($listbuilder, options);						
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.listbuilder.MultipleListsListbuilderHandler,
			$.pkp.controllers.listbuilder.ListbuilderHandler);


	//
	// Private properties
	//
	/**
	 * The list elements of this listbuilder.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			$lists_ = null;
	
	
	//
	// Getters and setters.
	//	
	/**
	 * Get passed list rows.
	 * @param {jQuery} $list
	 * @return {jQuery}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getRowsByList = function($list) {
		return $list.find('.gridRow');
	};
	
	
	/**
	 * Get list elements.
	 * @param {jQuery}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getLists = function() {
		return this.$lists_;
	};
	
	
	/**
	 * Set list elements based on lists id options.
	 * @param {array}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			setLists = function(listsId) {
		var $lists = jQuery();
		if (!$.isArray(listsId)) throw Error('Lists id must be passed using an array object!');
		for(index in listsId) {
			$list = this.getListById(listsId[index]);
			if (this.$lists_) {
				this.$lists_ = this.$lists_.add($list);
			} else {
				this.$lists_ = $list;
			}			
		}
	};
	
	
	/**
	 * Get the list element by list id.
	 * @param {string} listId
	 * @return {jQuery}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListById = function(listId) {
		var listElementId = this.getGridIdPrefix() + '-table-' + listId;
		return $('#' + listElementId, this.getHtmlElement());
	};
	
	
	/**
	 * Get the list element of the passed row.
	 * @param {jQuery} $row
	 * @return {jQuery}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListByRow = function($row) {
		return $row.parents('table:first');
	};
	
	
	/**
	 * Get the passed row list id.
	 * @param {jQuery} $row
	 * @returns {string}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListIdByRow = function($row) {
		var $list = this.getListByRow($row);
		return this.getListId($list);
	};
	
	
	/**
	 * Get the passed list id.
	 * @param {jQuery} $list
	 * @return {string}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListId = function ($list) {
		var idPrefix = this.getGridIdPrefix() + '-table-';
		var listElementId = $list.attr('id');
		return listElementId.slice(idPrefix.length);
	};
	
	
	/**
	 * Get no items row inside the passed list.
	 * @param {jQuery} $list
	 * @return {jQuery}
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListNoItemsRow = function($list) {
		return $list.find('tr.empty');
	};
	
	
	//
	// Protected methods.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			initialize = function(options) {
		this.parent('initialize', options);
		this.setLists(options.listsId);
	};
	
	
	//
	// Public methods.
	//	
	/**
	 * Show/hide the no items row, based on the number of grid rows
	 * inside the passed list.
	 * @param {jQuery} $list
	 * @param {integer} limit The minimum number of elements inside the list to
	 * show the no items row.
	 * @param {string} $filterSelector optional Selector to filter the rows that
	 * this method will consider as list rows. If not passed, all grid rows inside
	 * the passed list will be considered.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			toggleListNoItemsRow = function($list, limit, $filterSelector) {
		var $noItemsRow = this.getListNoItemsRow($list);
		
		var $listRows = this.getRowsByList($list);
		if ($filterSelector) {
			$listRows = $listRows.not($filterSelector);
		}
		if ($listRows.length == limit) {
			$noItemsRow.detach();
			$list.append($noItemsRow);
			$noItemsRow.show();
		} else {
			$noItemsRow.detach();
			$list.append($noItemsRow);
			$noItemsRow.hide();
		}
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
	 * @param {Array} options Options array.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.
			prototype.initFeatures_ = function(options) {
		var $orderItemsFeature =
				/** @type {$.pkp.classes.features.OrderItemsFeature} */
				($.pkp.classes.Helper.objectFactory(
						'$.pkp.classes.features.OrderMultipleListsItemsFeature',
						[this, {}]));

		this.features_ = {'orderItems': $orderItemsFeature};
		this.features_.orderItems.init();
	};
	

/** @param {jQuery} $ jQuery closure. */
})(jQuery);

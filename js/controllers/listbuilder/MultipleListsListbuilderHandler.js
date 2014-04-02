/**
 * @file js/controllers/listbuilder/MultipleListsListbuilderHandler.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
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
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler,
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
	 * @param {jQuery} $list JQuery List containing rows.
	 * @return {jQuery} JQuery rows objects.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getRowsByList = function($list) {
		return $list.find('.gridRow');
	};


	/**
	 * Get list elements.
	 * @return {jQuery} The JQuery lists.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getLists = function() {
		return this.$lists_;
	};


	/**
	 * Set list elements based on lists id options.
	 * @param {array} listsId Array of IDs.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			setLists = function(listsId) {
		var $lists = jQuery();
		if (!$.isArray(listsId)) {
			throw Error('Lists id must be passed using an array object!');
		}

		for (var index in listsId) {
			var $list = this.getListById(listsId[index]);
			if (this.$lists_) {
				this.$lists_ = this.$lists_.add($list);
			} else {
				this.$lists_ = $list;
			}
		}
	};


	/**
	 * Get the list element by list id.
	 * @param {string} listId List ID.
	 * @return {jQuery} List element.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListById = function(listId) {
		var listElementId = this.getGridIdPrefix() + '-table-' + listId;
		return $('#' + listElementId, this.getHtmlElement());
	};


	/**
	 * Get the list element of the passed row.
	 * @param {jQuery} $row JQuery row object.
	 * @return {jQuery} List JQuery element.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListByRow = function($row) {
		return $row.parents('table:first');
	};


	/**
	 * Get the passed row list id.
	 * @param {jQuery} $row JQuery row object.
	 * @return {string} List ID.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListIdByRow = function($row) {
		var $list = this.getListByRow($row);
		return this.getListId($list);
	};


	/**
	 * Get the passed list id.
	 * @param {jQuery} $list JQuery list object.
	 * @return {string} List ID.
	 */
	$.pkp.controllers.listbuilder.MultipleListsListbuilderHandler.prototype.
			getListId = function($list) {
		var idPrefix = this.getGridIdPrefix() + '-table-';
		var listElementId = $list.attr('id');
		return listElementId.slice(idPrefix.length);
	};


	/**
	 * Get no items row inside the passed list.
	 * @param {jQuery} $list JQuery list object.
	 * @return {jQuery} JQuery "no items" row.
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
	 * @param {jQuery} $list JQuery elements to scan.
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


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

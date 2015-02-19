/**
 * @file js/classes/features/OrderItemsFeature.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Base feature class for ordering grid items.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @param {$.controllers.grid.GridHandler} gridHandler The handler of
	 *  the grid element that this feature is attached to.
	 * @param {object} options Configuration of this feature.
	 */
	$.pkp.classes.features.OrderItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);

		this.$orderButton_ = $('a.order_items:first',
				this.getGridHtmlElement()).not('table a');
		this.$finishControl_ = $('.order_finish_controls', this.getGridHtmlElement());

		if (this.$orderButton_.length === 0) {
			// No order button, it will always stay in ordering mode.
			this.isOrdering_ = true;
		}

		this.itemsOrder_ = [];
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderItemsFeature,
			$.pkp.classes.features.Feature);


	//
	// Private properties.
	//
	/**
	 * Item sequence.
	 * @private
	 * @type {array}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.itemsOrder_ = null;


	/**
	 * Flag to control if user is ordering items.
	 * @private
	 * @type {boolean}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.isOrdering_ = false;


	/**
	 * Initiate ordering state button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.$orderButton_ = null;


	/**
	 * Cancel ordering state button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.$cancelButton_ = null;


	/**
	 * Save ordering state button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.$saveButton_ = null;


	/**
	 * Ordering finish control.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.$finishControl_ = null;


	//
	// Getters and setters.
	//
	/**
	 * Get the order button.
	 * @return {jQuery} The order button JQuery object.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getOrderButton =
			function() {
		return this.$orderButton_;
	};


	/**
	 * Get the finish control.
	 * @return {jQuery} The JQuery "finish" control.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getFinishControl =
			function() {
		return this.$finishControl_;
	};


	/**
	 * Get save order button.
	 *
	 * @return {jQuery} The "save order" JQuery object.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getSaveOrderButton =
			function() {
		return this.getFinishControl().find('.saveButton');
	};


	/**
	 * Get cancel order link.
	 *
	 * @return {jQuery} The "cancel order" JQuery control.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getCancelOrderButton =
			function() {
		return this.getFinishControl().find('.cancelFormButton');
	};


	/**
	 * Get the move item row action element selector.
	 * @return {string} Return the element selector.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.
			getMoveItemRowActionSelector = function() {
		return '.orderable a.order_items';
	};


	/**
	 * Get the css classes used to stylize the ordering items.
	 * @return {String} CSS classes.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getMoveItemClasses =
			function() {
		return 'pkp_helpers_moveicon ordering';
	};


	//
	// Public template methods.
	//
	/**
	 * Called every time user start dragging an item.
	 * @param {JQuery} contextElement The element this event occurred for.
	 * @param {Event} event The drag/drop event.
	 * @param {Object} ui Object with data related to the event elements.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.dragStartCallback =
			function(contextElement, event, ui) {
		// The default implementation does nothing.
	};


	/**
	 * Called every time user stop dragging an item.
	 * @param {JQuery} contextElement The element this event occurred for.
	 * @param {Event} event The drag/drop event.
	 * @param {Object} ui Object with data related to the event elements.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.dragStopCallback =
			function(contextElement, event, ui) {
		// The default implementation does nothing.
	};


	/**
	 * Called every time sequence is changed.
	 * @param {JQuery} contextElement The element this event occurred for.
	 * @param {Event} event The drag/drop event.
	 * @param {Object} ui Object with data related to the event elements.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.updateOrderCallback =
			function(contextElement, event, ui) {
		// The default implementation does nothing.
	};


	//
	// Extended methods from Feature
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.init =
			function() {

		this.addOrderingClassToRows();
		this.toggleMoveItemRowAction(this.isOrdering_);

		this.toggleOrderLink_();
		if (this.isOrdering_) {
			this.setupSortablePlugin();
		}
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.addFeatureHtml =
			function($gridElement, options) {
		if (options.orderFinishControls !== undefined) {
			var $orderFinishControls = $(options.orderFinishControls);
			$gridElement.find('table').last().after($orderFinishControls);
			$orderFinishControls.hide();
		}
	};


	//
	// Protected template methods.
	//
	/**
	 * Add orderable class to grid rows.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.addOrderingClassToRows =
			function() {
		// Add ordering class to grid rows.
		var $gridRows = this.gridHandler_.getRows();
		$gridRows.addClass('orderable');
	};


	/**
	 * Setup the sortable plugin. Must be implemented in subclasses.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.setupSortablePlugin =
			function() {
		// Default implementation does nothing.
	};


	/**
	 * Called every time storeOrder is called. This is a chance to subclasses
	 * execute operations with each row that has their sequence being saved.
	 * @param {integer} index The current row index position inside the rows
	 * jQuery object.
	 * @param {jQuery} $row Row for which to store the sequence.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.storeRowOrder =
			function(index, $row) {
		// The default implementation does nothing.
	};


	//
	// Protected methods.
	//
	/**
	 * Initiate ordering button click event handler.
	 * @return {boolean} Always returns false.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.clickOrderHandler =
			function() {
		this.gridHandler_.hideAllVisibleRowActions();
		this.storeOrder(this.gridHandler_.getRows());
		this.toggleState(true);
		return false;
	};


	/**
	 * Save order handler.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.saveOrderHandler =
			function() {
		this.gridHandler_.updateControlRowsPosition();
		this.unbindOrderFinishControlsHandlers_();
		var $rows = this.gridHandler_.getRows();
		this.storeOrder($rows);
	};


	/**
	 * Cancel ordering action click event handler.
	 * @return {boolean} Always returns false.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.cancelOrderHandler =
			function() {
		this.gridHandler_.resequenceRows(this.itemsOrder_);
		this.toggleState(false);
		return false;
	};


	/**
	 * Execute all operations necessary to change the state of the
	 * ordering process (enabled or disabled).
	 * @param {boolean} isOrdering Is ordering process active?
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleState =
			function(isOrdering) {
		this.isOrdering_ = isOrdering;
		this.toggleGridLinkActions_();
		this.toggleOrderLink_();
		this.toggleFinishControl_();
		this.toggleItemsDragMode();
		this.setupSortablePlugin();
	};


	/**
	 * Set rows sequence store, using
	 * the sequence of the passed items.
	 *
	 * @param {jQuery} $rows The rows to be used to get the sequence information.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.storeOrder =
			function($rows) {
		this.itemsOrder_ = [];
		var index, limit;
		for (index = 0, limit = $rows.length; index < limit; index++) {
			var $row = $($rows[index]);
			var elementId = $row.attr('id');
			this.itemsOrder_.push(elementId);

			// Give a chance to subclasses do extra operations to store
			// the current row order.
			this.storeRowOrder(index, $row);
		}
	};


	/**
	 * Enable/disable the items drag mode.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleItemsDragMode =
			function() {
		var isOrdering = this.isOrdering_;
		var $rows = this.gridHandler_.getRows();
		var $orderableRows = $rows.filter('.orderable');
		var moveClasses = this.getMoveItemClasses();
		if (isOrdering) {
			$orderableRows.addClass(moveClasses);
		} else {
			$orderableRows.removeClass(moveClasses);
		}

		this.toggleMoveItemRowAction(isOrdering);
	};


	/**
	 * Apply (disabled or enabled) the sortable plugin on passed elements.
	 * @param {jQuery} $container The element that contain all the orderable items.
	 * @param {string} itemsSelector The jQuery selector for orderable items.
	 * @param {Object?} extraParams Optional set of extra parameters for sortable.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.applySortPlgOnElements =
			function($container, itemsSelector, extraParams) {
		var isOrdering = this.isOrdering_;
		var dragStartCallback = this.gridHandler_.callbackWrapper(
				this.dragStartCallback, this);
		var dragStopCallback = this.gridHandler_.callbackWrapper(
				this.dragStopCallback, this);
		var orderItemCallback = this.gridHandler_.callbackWrapper(
				this.updateOrderCallback, this);
		var config = {
			disabled: !isOrdering,
			items: itemsSelector,
			activate: dragStartCallback,
			deactivate: dragStopCallback,
			update: orderItemCallback,
			tolerance: 'pointer'};

		if (typeof extraParams === 'object') {
			config = $.extend(true, config, extraParams);
		}

		$container.sortable(config);
	};


	/**
	 * Get the data element id of all rows inside the passed
	 * container, in the current order.
	 * @param {jQuery} $rowsContainer The element that contains the rows
	 * that will be used to retrieve the id.
	 * @return {Array} A sequence array with data element ids as values.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.getRowsDataId =
			function($rowsContainer) {
		var index;
		var rowDataIds = [];
		for (index in this.itemsOrder_) {
			var $row = $('#' + this.itemsOrder_[index], $rowsContainer);
			if ($row.length < 1) {
				continue;
			}
			var rowDataId = this.gridHandler_.getRowDataId($row);
			rowDataIds.push(rowDataId);
		}

		return rowDataIds;
	};


	/**
	 * Show/hide the move item row action (position left).
	 * @param {boolean} enable New enable state.
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleMoveItemRowAction =
			function(enable) {
		var $grid = this.getGridHtmlElement();
		var $actionsContainer = $('div.row_actions', $grid);
		var allLinksButMoveItemSelector = 'a:not(' +
				this.getMoveItemRowActionSelector() + ')';
		var $actions = $actionsContainer.find(allLinksButMoveItemSelector);
		var $moveItemRowAction = $(this.getMoveItemRowActionSelector(), $grid);
		if (enable) {
			$actions.addClass('pkp_helpers_display_none');
			$moveItemRowAction.show();
			// Make sure row actions div is visible.
			this.gridHandler_.showRowActionsDiv();
		} else {
			$actions.removeClass('pkp_helpers_display_none');

			var $rowActionsContainer = $('.gridRow div.row_actions', $grid);
			var $rowActions = $rowActionsContainer.
					find(allLinksButMoveItemSelector);
			if ($rowActions.length === 0) {
				// No link action to show, hide row actions div.
				this.gridHandler_.hideRowActionsDiv();
			}
			$moveItemRowAction.hide();
		}
	};


	//
	// Hooks implementation.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.appendElement =
			function($element) {
		this.addOrderingClassToRows();
		this.toggleItemsDragMode();
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.replaceElement =
			function($content) {
		this.addOrderingClassToRows();
		this.toggleItemsDragMode();
	};


	//
	// Private helper methods.
	//
	/**
	 * Set the state of the grid link actions, based on current ordering state.
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleGridLinkActions_ =
			function() {
		var isOrdering = this.isOrdering_;

		// We want to enable/disable all link actions, except this
		// features controls.
		var $gridLinkActions = $('.pkp_controllers_linkAction',
				this.getGridHtmlElement()).not(this.getMoveItemRowActionSelector(),
				this.getOrderButton(), this.getFinishControl().find('*'));

		this.gridHandler_.changeLinkActionsState(!isOrdering, $gridLinkActions);
	};


	/**
	 * Enable/disable the order link action.
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleOrderLink_ =
			function() {
		if (this.isOrdering_) {
			this.$orderButton_.unbind('click');
			this.$orderButton_.addClass('ui-state-disabled');
		} else {
			var clickHandler = this.gridHandler_.callbackWrapper(
					this.clickOrderHandler, this);
			this.$orderButton_.click(clickHandler);
			this.$orderButton_.removeClass('ui-state-disabled');
		}
	};


	/**
	 * Show/hide the ordering process finish control, based
	 * on the current ordering state.
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.toggleFinishControl_ =
			function() {
		if (this.isOrdering_) {
			this.bindOrderFinishControlsHandlers_();
			this.getFinishControl().slideDown(300);
		} else {
			this.unbindOrderFinishControlsHandlers_();
			this.getFinishControl().slideUp(300);
		}
	};


	/**
	 * Bind event handlers to the controls that finish the
	 * ordering action (save and cancel).
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.
			bindOrderFinishControlsHandlers_ = function() {
		var $saveButton = this.getSaveOrderButton();
		var $cancelLink = this.getCancelOrderButton();

		var cancelLinkHandler = this.gridHandler_.callbackWrapper(
				this.cancelOrderHandler, this);
		var saveButtonHandler = this.gridHandler_.callbackWrapper(
				this.saveOrderHandler, this);

		$saveButton.click(saveButtonHandler);
		$cancelLink.click(cancelLinkHandler);
	};


	/**
	 * Unbind event handlers from the controls that finish the
	 * ordering action (save and cancel).
	 * @private
	 */
	$.pkp.classes.features.OrderItemsFeature.prototype.
			unbindOrderFinishControlsHandlers_ = function() {
		var $saveButton = this.getSaveOrderButton();
		var $cancelLink = this.getCancelOrderButton();
		$saveButton.unbind('click');
		$cancelLink.unbind('click');
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

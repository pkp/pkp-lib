/**
 * @file js/classes/features/ToggleableOrderItemsFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ToggleableOrderItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Toggleable ordering items feature class.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
		this.$orderButton_ = options.orderButton;
		this.$saveButton_ = options.finishControl.find('.saveButton');
		this.$cancelButton_ = options.finishControl.find('.cancelFormButton');
		this.$finishControl_ = options.finishControl;
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.ToggleableOrderItemsFeature,
			$.pkp.classes.features.OrderItemsFeature);


	//
	// Private properties.
	//
	/**
	 * Flag to control if user is ordering items.
	 * @private
	 * @type {boolean}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.isOrdering_ = false;

	/**
	 * Initiate ordering state button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.$orderButton_ = null;

	/**
	 * Cancel ordering state button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.$cancelButton_ = null;

	/**
	 * Save ordering state button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.$saveButton_ = null;

	/**
	 * Ordering finish control.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.$finishControl_ = null;


	//
	// Getters and setters.
	//
	/**
	 * Get the order button.
	 * @return {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.getOrderButton =
			function() {
		return this.$orderButton_;
	};

	/**
	 * Get the finish control.
	 * @return {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.getFinishControl =
			function() {
		return this.$finishControl_;
	};

	/**
	 * Get save order button.
	 * @private
	 *
	 * @return {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.getSaveOrderButton =
			function () {
		return this.$saveButton_;
	};

	/**
	 * Get cancel order link.
	 * @private
	 *
	 * @return {jQuery}
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.getCancelOrderButton =
			function () {
		return this.$cancelButton_;
	};


	//
	// Public methods
	//
	/**
	 * @inheritDoc
	 * @param {boolean} Initialize with ordering state enabled?
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.init =
			function() {
		this.toggleOrderLink_();
	};


	//
	// Protected methods.
	//
	/**
	 * Initiate ordering button click event handler.
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.clickOrderHandler =
			function() {
		this.gridHandler_.hideAllVisibleRowActions();
		this.storeOrder(this.gridHandler_.getRows());
		this.toggleState(true);
		return false;
	};

	/**
	 * Save order handler.
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.saveOrderHandler =
			function() {
		this.gridHandler_.updateControlRowsPosition();
		this.unbindOrderFinishControlsHandlers_();
		$rows = this.gridHandler_.getRows();
		this.storeOrder($rows);
	};

	/**
	 * Cancel ordering action click event handler.
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.cancelOrderHandler =
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
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.toggleState =
			function(isOrdering) {
		this.isOrdering_ = isOrdering;
		this.toggleGridLinkActions_();
		this.toggleOrderLink_();
		this.toggleFinishControl_();
		this.toggleItemsDragMode(isOrdering);
		this.setupSortablePlugin(isOrdering);
	};


	//
	// Private helper methods.
	//
	/**
	 * Set the state of the grid link actions, based on current ordering state.
	 * @returns
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.toggleGridLinkActions_ =
			function() {
		var isOrdering = this.isOrdering_;

		// We want to enable/disable all link actions, except this features controls.
		var $gridLinkActions = $('.pkp_controllers_linkAction:not('
			+ this.getMoveItemRowActionSelector() + ')', this.getGridHtmlElement()).not(
			this.getOrderButton()).not(
			this.getFinishControl().find('*'));

		this.gridHandler_.changeLinkActionsState(!isOrdering, $gridLinkActions);
	};

	/**
	 * Enable/disable the order link action.
	 * @private
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.toggleOrderLink_ =
			function() {
		if (this.isOrdering_) {
			this.$orderButton_.unbind('click');
			this.$orderButton_.addClass('ui-state-disabled');
		} else {
			var clickHandler = this.gridHandler_.callbackWrapper(this.clickOrderHandler, this);
			this.$orderButton_.click(clickHandler);
			this.$orderButton_.removeClass('ui-state-disabled');
		}
	};

	/**
	 * Show/hide the ordering process finish control, based
	 * on the current ordering state.
	 * @private
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.toggleFinishControl_ =
			function() {
		if (this.isOrdering_) {
			this.bindOrderFinishControlsHandlers_();
			this.getFinishControl().slideDown('300');
		} else {
			this.unbindOrderFinishControlsHandlers_();
			this.getFinishControl().slideUp('300');
		}
	};

	/**
	 * Bind event handlers to the controls that finish the
	 * ordering action (save and cancel).
	 * @returns
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.bindOrderFinishControlsHandlers_ =
			function() {
		$saveButton = this.getSaveOrderButton();
		$cancelLink = this.getCancelOrderButton();

		var cancelLinkHandler = this.gridHandler_.callbackWrapper(this.cancelOrderHandler, this);
		var saveButtonHandler = this.gridHandler_.callbackWrapper(this.saveOrderHandler, this);

		$saveButton.click(saveButtonHandler);
		$cancelLink.click(cancelLinkHandler);
	};

	/**
	 * Unbind event handlers from the controls that finish the
	 * ordering action (save and cancel).
	 * @returns
	 */
	$.pkp.classes.features.ToggleableOrderItemsFeature.prototype.unbindOrderFinishControlsHandlers_ =
			function() {
		$saveButton = this.getSaveOrderButton();
		$cancelLink = this.getCancelOrderButton();
		$saveButton.unbind('click');
		$cancelLink.unbind('click');
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

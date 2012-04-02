/**
 * @file js/classes/features/OrderGridItemsFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OrderGridItemsFeature
 * @ingroup js_classes_features
 *
 * @brief Feature for ordering grid items.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.OrderGridItemsFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.OrderGridItemsFeature,
			$.pkp.classes.features.ToggleableOrderItemsFeature);


	//
	// Extended methods from ToggleableOrderItemsFeature.
	//
	/**
	 * Save order handler.
	 */
	$.pkp.classes.features.OrderGridItemsFeature.prototype.saveOrderHandler =
			function() {
		this.parent('saveOrderHandler');
		var stringifiedData = JSON.stringify(this.getRowDataIds());
		var saveOrderCallback = this.gridHandler_.callbackWrapper(this.saveOrderResponseHandler_, this);
		$.post(this.options_.saveItemsSequenceUrl, {data: stringifiedData},
				saveOrderCallback, 'json');
		return false;

	};

	/**
	 * Save order response handler.
	 * @private
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 */
	$.pkp.classes.features.OrderGridItemsFeature.prototype.saveOrderResponseHandler_ =
			function(ajaxContext, jsonData) {
		jsonData = this.gridHandler_.handleJson(jsonData);
		this.toggleState(false);
	};
	

/** @param {jQuery} $ jQuery closure. */
})(jQuery);

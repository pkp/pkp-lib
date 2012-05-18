/**
 * @file js/classes/features/GridCategoryAccordionFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GridCategoryAccordionFeature
 * @ingroup js_classes_features
 *
 * @brief Feature that transform grid categories into accordions.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.GridCategoryAccordionFeature,
			$.pkp.classes.features.Feature);


	//
	// Getters and setters.
	//
	/**
	 * Get the css class for the extend accordion action.
	 * @return {string}
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			getExpandClass = function() {
		return 'expanded';
	};


	/**
	 * Get the css class for the collapse accordion action.
	 * @return {string}
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			getCollapseClass = function() {
		return 'collapsed';
	};


	/**
	 * Get category rows accordion link actions.
	 * @return {jQuery} Link actions.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			getAccordionLinks = function() {
		var $table = this.getGridHtmlElement().find('table');
		var collapseSelector = '.' + this.getCollapseClass();
		var expandSelector = '.' + this.getExpandClass();
		return $(collapseSelector + ',' + expandSelector, $table);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.init =
			function() {
		$('.grid_header_bar .expand_all', this.getGridHtmlElement()).
				click(this.callbackWrapper(this.expandAllClickHandler_, this));

		$collapseAllLink = $('.grid_header_bar .collapse_all',
				this.getGridHtmlElement());
		$collapseAllLink.click(this.callbackWrapper(
				this.collapseAllClickHandler_, this));

		var $accordionLinkActions = this.getAccordionLinks();
		$accordionLinkActions.click(
				this.callbackWrapper(this.accordionRowClickHandler_, this));

		$collapseAllLink.click();
	};


	//
	// Private helper methods.
	//
	/**
	 * Expand all link action click handler.
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			expandAllClickHandler_ = function(callingContext, opt_event) {
		$(callingContext).hide();
		$('.grid_header_bar .collapse_all').show();
		$('.category .' + this.getExpandClass(),
				this.getGridHtmlElement()).click();

		return false;
	};


	/**
	 * Collapse all link action click handler.
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			collapseAllClickHandler_ = function(callingContext, opt_event) {
		$(callingContext).hide();
		$('.grid_header_bar .expand_all').show();
		$('.category .' + this.getCollapseClass(),
				this.getGridHtmlElement()).click();

		return false;
	};


	/**
	 * Grid category row accordion link action click handler.
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			accordionRowClickHandler_ = function(callingContext, opt_event) {

		var $link = $(callingContext);
		$link.hide();

		var $category = $link.parents('.category_grid_body:first');
		var $categoryElements = $('.gridRow', $category);
		if ($categoryElements.length == 0) {
			$categoryElements = this.gridHandler_.
					getCategoryEmptyPlaceholder($category);
		}

		var $actionsContainer = $link.parent();
		if ($link.hasClass(this.getExpandClass())) {
			var $collapseLink = $('.' + this.getCollapseClass(),
					$actionsContainer);
			$collapseLink.show();
			$categoryElements.show();
		} else {
			var $expandLink = $('.' + this.getExpandClass(),
					$actionsContainer);
			$expandLink.show();
			$categoryElements.hide();
		}

		return false;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

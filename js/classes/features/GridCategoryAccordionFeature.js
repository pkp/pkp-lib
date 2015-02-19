/**
 * @file js/classes/features/GridCategoryAccordionFeature.js
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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
	 * @return {string} Extend link action css class.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			getExpandClass = function() {
		return 'expanded';
	};


	/**
	 * Get the css class for the collapse accordion action.
	 * @return {string} Collapse link action css class.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			getCollapseClass = function() {
		return 'collapsed';
	};


	/**
	 * Get category rows accordion link actions inside the passed
	 * element.
	 * @param {jQuery} $context The context element.
	 * @return {jQuery} Link actions.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			getAccordionLinks = function($context) {
		var collapseSelector = '.' + this.getCollapseClass();
		var expandSelector = '.' + this.getExpandClass();
		return $(collapseSelector + ',' + expandSelector, $context);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.init =
			function() {
		$('.grid_header_bar .expand_all', this.getGridHtmlElement()).
				click(this.callbackWrapper(this.expandAllClickHandler_, this));

		var $collapseAllLink = $('.grid_header_bar .collapse_all',
				this.getGridHtmlElement());
		$collapseAllLink.click(this.callbackWrapper(
				this.collapseAllClickHandler_, this));

		this.bindCategoryAccordionControls_(this.getGridHtmlElement());

		$collapseAllLink.click();

		// Hide all no items tbody.
		this.hideEmptyPlaceholders_();

	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.replaceElement =
			function($newContent) {
		this.bindCategoryAccordionControls_($newContent);
		// Make sure the category items are visible.
		$('.' + this.getExpandClass(), $newContent).click();
		this.hideEmptyPlaceholders_();
	};


	//
	// Private helper methods.
	//
	/**
	 * Add click handlers to the accordion controls inside the
	 * passed element.
	 * @param {jQuery} $element The context element.
	 * @private
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			bindCategoryAccordionControls_ = function($element) {
		var $accordionLinkActions = this.getAccordionLinks($element);
		$accordionLinkActions.click(
				this.callbackWrapper(this.accordionRowClickHandler_, this));
	};


	/**
	 * Hide all grid empty placeholders.
	 * @private
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			hideEmptyPlaceholders_ = function() {
		var $emptyPlaceholders = $('tbody.empty',
				this.getGridHtmlElement());
		$emptyPlaceholders.hide();
	};


	/**
	 * Expand all link action click handler.
	 * @private
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
	 * @private
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

		$('.row_controls :visible').closest('tr').prev().
				find('.row_actions > a:first').click();
		return false;
	};


	/**
	 * Grid category row accordion link action click handler.
	 * @private
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			accordionRowClickHandler_ = function(callingContext, opt_event) {

		var $link = $(callingContext);
		$link.hide();

		var $category = $link.parents('.category_grid_body:first');
		var $categoryElements = this.gridHandler_.
				getRowsInCategory($category);
		if ($categoryElements.length === 0) {
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

		this.updateGridActions_();

		return false;
	};


	/**
	 * Hide/show accordions grid actions depending on the
	 * state of the categories (all expanded or collapsed).
	 * This is called every time a accordionRowClickHandler
	 * is executed.
	 * @private
	 */
	$.pkp.classes.features.GridCategoryAccordionFeature.prototype.
			updateGridActions_ = function() {
		// Only execute if grid is visible.
		if (!this.getGridHtmlElement().is(':visible')) {
			return;
		}

		var $grid = this.getGridHtmlElement();
		var selectors = '.' + this.getExpandClass() +
				', .' + this.getCollapseClass();
		var $allRowActions = $(selectors, $grid).filter(':visible');

		var $expandActions = $('.' + this.getExpandClass(), $grid).
				filter(':visible');
		var $collapseActions = $('.' + this.getCollapseClass(), $grid).
				filter(':visible');

		var $gridCollapseAction = $('.grid_header_bar .collapse_all');
		var $gridExpandAction = $('.grid_header_bar .expand_all');

		if ($allRowActions.length == $expandActions.length &&
				$gridCollapseAction.is(':visible')) {
			// Show the expand all action.
			$gridCollapseAction.click();
		} else if ($allRowActions.length == $collapseActions.length &&
				$gridExpandAction.is(':visible')) {
			// Show the collapse all action.
			$gridExpandAction.click();
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

/**
 * @file js/classes/features/InfiniteScrollingFeature.js
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class InfiniteScrollingFeature
 * @ingroup js_classes_features
 *
 * @brief Feature that implements infinite scrolling on grids.
 * It doesn't support category grids.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 * @extends $.pkp.classes.features.GeneralPagingFeature
	 */
	$.pkp.classes.features.InfiniteScrollingFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.InfiniteScrollingFeature,
			$.pkp.classes.features.GeneralPagingFeature);


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.init =
			function() {
		this.getGridHtmlElement().find('div.pkp_loading').hide();
		this.addScrollHandler_();
		this.fixGridHeight_();
		this.addPagingDataToRows_();
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.addFeatureHtml =
			function($gridElement, options) {
		var castOptions = /** @type {{pagingMarkup: string?,
					loadingContainer: string?}} */ (options);
		$gridElement.find('div.scrollable').append(castOptions.loadingContainer)
			.after(castOptions.pagingMarkup);
	};


	//
	// Hooks implementation.
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.refreshGrid =
			function(opt_elementId) {
		var options = this.getOptions(), params, $firstRow, $lastRow, page, $gridRow,
				elementId;

		params = this.gridHandler.getFetchExtraParams();
		params[options.pageParamName] = options.currentPage;

		if (opt_elementId && opt_elementId !==
				$.pkp.controllers.grid.GridHandler.FETCH_ALL_ROWS_ID) {
			// We need to make sure we pass the right page for the element.
			elementId = /** @type {number} */ opt_elementId;
			$gridRow = this.gridHandler.getRowByDataId(elementId);
			if ($gridRow.length == 1) {
				params[options.pageParamName] = Number($gridRow.attr('data-paging'));
			}
		}

		params[options.itemsPerPageParamName] = options.currentItemsPerPage;

		this.setGridParams(params);

		return false;
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.
			replaceElementResponseHandler = function(handledJsonData) {
		var pagingInfo, options, castJsonData, rowMarkup;
		options = this.getOptions();
		castJsonData = /** @type {{pagingInfo: Object,
				deletedRowReplacement: string}} */
				handledJsonData;

		if (castJsonData.deletedRowReplacement != undefined) {
			rowMarkup = handledJsonData.deletedRowReplacement;
			this.gridHandler.insertOrReplaceElement(rowMarkup);
			this.updatePagingDataInAllRows_();
		}

		this.addScrollHandler_();

		if (castJsonData.pagingInfo != undefined) {
			pagingInfo = handledJsonData.pagingInfo;
			this.setOptions(pagingInfo);

			if (pagingInfo.pagingMarkup != undefined) {
				$('span.gridPagingScrolling', this.getGridHtmlElement()).
						replaceWith(pagingInfo.pagingMarkup);
			}
		}

		this.addPagingDataToRows_();

		this.toggleLoadingContainer_();

		return false;
	};


	//
	// Private helper methods.
	//
	/**
	 * Scroll handler to detect when it's time to request more rows.
	 *
	 * @private
	 *
	 * @param {HTMLElement} sourceElement
	 * @param {Event} event
	 * @return {boolean}
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.observeScroll_ =
			function(sourceElement, event) {
		var options = this.getOptions();
		if (options.itemsTotal == this.gridHandler.getRows().length) {
			return false;
		}
		if (sourceElement.scrollHeight - $(sourceElement).scrollTop() ==
				$(sourceElement).height()) {
			// Avoid multiple rows requests.
			$('div.scrollable', this.getGridHtmlElement()).unbind('scroll');

			// Show the loading icon.
			this.toggleLoadingContainer_(true);

			options.currentPage = Number($('tr.gridRow',
					this.getGridHtmlElement()).last().attr('data-paging')) + 1;
			this.getGridHtmlElement().trigger('dataChanged',
					[$.pkp.controllers.grid.GridHandler.FETCH_ALL_ROWS_ID]);
		}

		return false;
	};


	/**
	 * Fix the grid height to acomodate the number of initial visible rows.
	 *
	 * @private
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.fixGridHeight_ =
			function() {
		var $scrollableDivs = $('div.scrollable', this.getGridHtmlElement()),
				index, limit, $div, timer, length;

		if ($scrollableDivs.length > 0) {
			timer = setInterval(function() {
				if ($scrollableDivs.is(':visible')) {
					clearInterval(timer);
					length = $scrollableDivs.length;
					for (index = 0, limit = length; index < limit; index++) {
						$div = $($scrollableDivs[index]);
						if ($div.get(0).scrollHeight > $div.height()) {
							$div.css('max-height', $div.get(0).scrollHeight - 10);
						}
					}
				}
			},300);
		}
	};


	/**
	 * Add paging data to the respective rows.
	 *
	 * @private
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.addPagingDataToRows_ =
			function() {
		var $rows, options = this.getOptions();
		$rows = this.gridHandler.getRows().filter('tr:not([data-paging])');
		$rows.attr('data-paging', options.currentPage);
	};


	/**
	 * Update paging data in all grid rows.
	 *
	 * @private
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.
			updatePagingDataInAllRows_ = function() {
		var $rows, options = this.getOptions(), index, limit, page = 1,
				itemsCount = 1;
		$rows = this.gridHandler.getRows();
		$rows.removeAttr('data-paging');

		for (index = 0, limit = $rows.length; index < limit; index++) {
			$($rows[index]).attr('data-paging', page);
			itemsCount++;
			if (itemsCount > options.currentItemsPerPage) {
				itemsCount = 1;
				page++;
			}
		}
	};


	/**
	 * Add scroll handler to the grid element.
	 *
	 * @private
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.addScrollHandler_ =
			function() {
		$('div.scrollable', this.getGridHtmlElement()).
				scroll(this.gridHandler.callbackWrapper(this.observeScroll_, this));
	};


	/**
	 * Toggle the scrolling loading element.
	 *
	 * @private
	 *
	 * @param {boolean=} opt_show
	 */
	$.pkp.classes.features.InfiniteScrollingFeature.prototype.
			toggleLoadingContainer_ = function(opt_show) {
		var $loadingElement =
				this.getGridHtmlElement().find('div.scrollable div.pkp_loading'),
						$scrollableElement = this.getGridHtmlElement().find('div.scrollable'),
						scrollTop,
						loadingHeight = $loadingElement.height(),
						scrollTarget;

		if (opt_show) {
			$loadingElement.show();
			scrollTop = $scrollableElement.scrollTop();
			scrollTarget = /** @type {number} */ scrollTop + loadingHeight;
			$scrollableElement.scrollTop(scrollTarget);
		} else {
			$loadingElement.hide();
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

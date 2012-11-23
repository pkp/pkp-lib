/**
 * @file js/classes/features/PagingFeature.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PagingFeature
 * @ingroup js_classes_features
 *
 * @brief Feature that implements paging on grids.
 */
(function($) {


	/**
	 * @constructor
	 * @inheritDoc
	 * @extends $.pkp.classes.features.Feature
	 */
	$.pkp.classes.features.PagingFeature =
			function(gridHandler, options) {
		this.parent(gridHandler, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.features.PagingFeature,
			$.pkp.classes.features.Feature);


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.PagingFeature.prototype.init =
			function() {
		this.configPagingLinks_();
		this.configItemsPerPageElement_();
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.PagingFeature.prototype.addFeatureHtml =
			function($gridElement, options) {
		$gridElement.append(options.pagingMarkup);
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.PagingFeature.prototype.refreshGrid =
			function() {
		var options = this.getOptions(), params;

		params = this.gridHandler.getFetchExtraParams();

		params[options.pageParamName] = options.currentPage;
		params[options.itemsPerPageParamName] = options.currentItemsPerPage;

		this.gridHandler.setFetchExtraParams(params);

		return false;
	};


	/**
	 * @inheritDoc
	 */
	$.pkp.classes.features.PagingFeature.prototype.replaceElementResponseHandler =
			function(handledJsonData) {
		var rowMarkup, pagingInfo, options;

		if (handledJsonData.deletedRowReplacement != undefined) {
			rowMarkup = handledJsonData.deletedRowReplacement;
			this.gridHandler.insertOrReplaceElement(rowMarkup);
		}

		if (handledJsonData.pagingInfo != undefined) {
			pagingInfo = handledJsonData.pagingInfo;
			this.setOptions(pagingInfo);

			$('div.gridPaging', this.getGridHtmlElement()).
					replaceWith(pagingInfo.pagingMarkup);
			this.init();
		}

		if (handledJsonData.loadLastPage) {
			options = this.getOptions();
			options.currentPage = options.currentPage - 1;
			this.setOptions(options);

			this.getGridHtmlElement().trigger('dataChanged');
		}

		return false;
	};


	//
	// Private helper methods.
	//
	/**
	 * Configure paging links.
	 *
	 * @private
	 */
	$.pkp.classes.features.PagingFeature.prototype.configPagingLinks_ =
			function() {

		var options, $pagingDiv, $links, index, limit, $link, regex, match,
				clickPagesCallback;

		options = this.getOptions();
		$pagingDiv = $('div.gridPaging', this.getGridHtmlElement());

		if ($pagingDiv) {
			clickPagesCallback = this.callbackWrapper(
					function(sourceElement, event) {
						regex = new RegExp('[?&]' + options.pageParamName +
								'(?:=([^&]*))?', 'i');
						match = regex.exec($(event.target).attr('href'));
						if (match != null) {
							options.currentPage = match[1];
							this.getGridHtmlElement().trigger('dataChanged');
						}

						// Stop event handling.
						return false;
					}, this);

			$links = $pagingDiv.find('a').
					not('.showMoreItems').not('.showLessItems');
			for (index = 0, limit = $links.length; index < limit; index++) {
				$link = $($links[index]);
				$link.click(clickPagesCallback);
			}
		}
	};


	/**
	 * Configure items per page element.
	 *
	 * @private
	 */
	$.pkp.classes.features.PagingFeature.prototype.configItemsPerPageElement_ =
			function() {

		var options, $pagingDiv, index, limit, $select, itemsPerPageValues,
				changeItemsPerPageCallback;

		options = this.getOptions();
		$pagingDiv = $('div.gridPaging', this.getGridHtmlElement());

		if ($pagingDiv) {
			changeItemsPerPageCallback = this.callbackWrapper(
					function(sourceElement, event) {
						options.currentItemsPerPage = $('option',
								event.target).filter(':selected').attr('value');
						// Reset to first page.
						options.currentPage = 1;

						this.getGridHtmlElement().trigger('dataChanged');

						// Stop event handling.
						return false;
					}, this);

			$select = $pagingDiv.find('select.itemsPerPage');
			itemsPerPageValues = [10, 25, 50, 75, 100];
			if ($.inArray(options.defaultItemsPerPage,
					itemsPerPageValues) < 0) {
				itemsPerPageValues.push(options.defaultItemsPerPage);
			}

			itemsPerPageValues.sort(function(a, b) { return a - b; });

			if (options.itemsTotal <= itemsPerPageValues[0]) {
				$('div.gridItemsPerPage', $pagingDiv).hide();
			} else {
				limit = itemsPerPageValues.length - 1;
				for (index = 0; index <= limit; index++) {
					$select.append($('<option value="' + itemsPerPageValues[index] +
							'">' + itemsPerPageValues[index] + '</option>'));
				}
				$select.val(options.currentItemsPerPage);
				$select.change(changeItemsPerPageCallback);
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

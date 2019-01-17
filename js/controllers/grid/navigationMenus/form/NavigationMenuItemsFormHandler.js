/**
 * @file js/controllers/grid/navigationMenus/form/NavigationMenuItemsFormHandler.js
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuItemsFormHandler
 * @ingroup js_controllers_grid_navigationMenus_form
 *
 * @brief NavigationMenuItems form handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $formElement A wrapped HTML element that
	 *  represents the form interface element.
	 * @param {Object} options javascript form options.
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler =
			function($formElement, options) {

		this.parent($formElement, options);

		this.previewUrl_ = options.previewUrl;
		this.itemTypeDescriptions_ = options.itemTypeDescriptions;
		this.itemTypeConditionalWarnings_ = options.itemTypeConditionalWarnings;

		$('#previewButton', $formElement).click(this.callbackWrapper(
				this.showPreview_));

		$('#menuItemType', $formElement).change(this.callbackWrapper(this.setType));
		$('#menuItemType', $formElement).trigger('change');
	};


	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//


	/**
	 * The preview url.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.
			NavigationMenuItemsFormHandler.prototype.previewUrl_ = null;


	/**
	 * Descriptions for each item type.
	 * @private
	 * @type {?Object}
	 */
	$.pkp.controllers.grid.navigationMenus.form.
			NavigationMenuItemsFormHandler.prototype.itemTypeDescriptions_ = null;


	/**
	 * Warnings about the conditions of display for each item type.
	 * @private
	 * @type {?Object}
	 */
	$.pkp.controllers.grid.navigationMenus.form.
			NavigationMenuItemsFormHandler.prototype
			.itemTypeConditionalWarnings_ = null;


	/**
	 * Callback triggered on clicking the "preview"
	 * button to open a preview window.
	 *
	 * @param {HTMLElement} submitButton The submit button.
	 * @param {Event} event The event that triggered the
	 *  submit button.
	 * @return {boolean} true.
	 * @private
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.
			prototype.showPreview_ = function(submitButton, event) {

		var $formElement = this.getHtmlElement();
		$.post(this.previewUrl_,
				$formElement.serialize(),
				function(data) {
					var win = window.open('about:blank');
					win.document.open();
					win.document.write(data);
					win.document.close();
				}
		);

		return true;
	};


	/**
	 * Callback triggered when the type is set
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.
			prototype.setType = function() {
		var itemType = $('#menuItemType', this.getHtmlElement()).val(),
				$descriptionEl = $('#menuItemTypeSection [for="menuItemType"]');

		$('.NMI_TYPE_CUSTOM_EDIT', this.getHtmlElement()).hide();
		$('#' + itemType).fadeIn();

		if (typeof this.itemTypeDescriptions_[itemType] !== 'undefined') {
			$descriptionEl.text(this.itemTypeDescriptions_[itemType]);
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

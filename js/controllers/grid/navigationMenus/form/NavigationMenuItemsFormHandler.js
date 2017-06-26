/**
 * @file js/controllers/grid/navigationMenus/form/NavigationMenuItemsFormHandler.js
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
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
	 *  represents the approved proof form interface element.
	 * @param {Object} options Tabbed modal options.
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler =
			function ($form, options) {

				this.parent($form, options);

				this.fetchNavigationMenuItemsUrl_ = options.fetchNavigationMenuItemsUrl;
				this.navigationMenuItemId_ = options.navigationMenuItemId;
				this.parentNavigationMenuItemId_ = options.parentNavigationMenuItemId;

				// Save the preview URL for later
				this.previewUrl_ = options.previewUrl;

				// bind a handler to make sure we update the required state
				// of the comments field.
				$('#previewButton', $form).click(this.callbackWrapper(
						this.showPreview_));

				var that = this;
				$("#navigationMenuId").change(function () {
					if ($(this).val() == 0) {
						$('#possibleParentNavigationMemuItemsDiv').hide();
					} else {
						$('#possibleParentNavigationMemuItemsDiv').html("<span id='possibleParentNavigationMemuItems-loading-span' class='possibleParentNavigationMemuItems-loading-span-class'>loading...</span>");
						$('#possibleParentNavigationMemuItemsDiv').show();
						$.get(that.fetchNavigationMenuItemsUrl_, { navigationMenuIdParent: $(this).val(), navigationMenuItemId: that.navigationMenuItemId_, parentNavigationMenuItemId: that.parentNavigationMenuItemId_ }, that.callbackWrapper(that.setNavigationMenuItemsList_), 'json');
					}
				});

				$("#navigationMenuId").trigger("change");
			};

	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to be called to fetch a list of navigationMenuItems.
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.
			prototype.fetchNavigationMenuItemsUrl_ = '';

	/**
	 * The id of the navigationMenuItem
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.
			prototype.navigationMenuItemId_ = '';

	/**
	 * The id of the parent navigationMenuItem
	 * @private
	 * @type {string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.
			prototype.parentNavigationMenuItemId_ = '';

	/**
	 * The preview url.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.prototype.
			previewUrl_ = null;

	//
	// Private methods.
	//
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.
			prototype.setNavigationMenuItemsList_ = function (formElement, jsonData) {

				var processedJsonData = this.handleJson(jsonData);

				$('#possibleParentNavigationMemuItemsDiv').replaceWith(processedJsonData.content);
				$('#possibleParentNavigationMemuItemsDiv').show();
			};

	/**
	 * Callback triggered on clicking the "preview" button to open a preview window.
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
					with(win.document) {
						open();
						write(data);
						close();
					}
				});
		return true;
	};
/** @param {jQuery} $ jQuery closure. */
}(jQuery));

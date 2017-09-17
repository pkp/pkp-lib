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

				// Save the preview URL for later
				this.previewUrl_ = options.previewUrl;

				// bind a handler to make sure we update the required state
				// of the comments field.
				$('#previewButton', $form).click(this.callbackWrapper(
						this.showPreview_));

				$('#previewButton').hide();

				// custom url or path functionality change
				$('#useCustomUrl').change(function () {
					if ($(this).is(":checked")) {
						$('#targetUrl').show();
						$('#targetPath').hide();
						$('#previewButton').hide();
					} else {
						$('#targetUrl').hide();
						$('#targetPath').show();
						$('#previewButton').show();
					}					
				});

				// type change event
				$('#type').change(function () {
					if ($(this)[0].value == "NMI_TYPE_CUSTOM") { // add global variable somehow
						$('#customItemFields').show();
						if ($('#useCustomUrl').is(":checked")) {
							$('#previewButton').hide();
						} else {
							$('#previewButton').show();
						}
					} else {
						$('#customItemFields').hide();
						$('#previewButton').hide();
					}
				});

				$('#useCustomUrl').trigger("change");
				$('#type').trigger("change");
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
	$.pkp.controllers.grid.navigationMenus.form.NavigationMenuItemsFormHandler.prototype.
			previewUrl_ = null;

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

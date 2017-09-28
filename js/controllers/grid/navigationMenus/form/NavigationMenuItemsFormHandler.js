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
			function($formElement, options) {

		this.parent($formElement, options);

		// Save the preview URL for later
		this.previewUrl_ = options.previewUrl;

		// bind a handler to make sure we update the required state
		// of the comments field.
		$('#previewButton', $formElement).click(this.callbackWrapper(
				this.showPreview_));

		$('#previewButton').hide();

		// type change event
		$('#type').change(
				function() {
					// add global variable somehow
					if ($(this)[0].value == 'NMI_TYPE_REMOTE_URL') {
						$('#targetUrl').show();
						$('#customItemFields').hide();
						$('#previewButton').hide();
					} else if ($(this)[0].value == 'NMI_TYPE_CUSTOM') {
						$('#targetUrl').hide();
						$('#customItemFields').show();
						$('#previewButton').show();
					} else {
						$('#targetUrl').hide();
						$('#customItemFields').hide();
						$('#previewButton').hide();
					}
				}
		);

		$('#type').trigger('change');
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


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

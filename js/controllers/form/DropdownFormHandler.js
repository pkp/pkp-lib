/**
 * @file js/controllers/form/DropdownFormHandler.js
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class DropdownFormHandler
 * @ingroup js_controllers_form
 *
 * @brief Handler for a form allowing the user to select from an AJAX-provided
 *   list of options, triggering an event upon selection.
 *
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.FormHandler
	 *
	 * @param {jQuery} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.form.DropdownFormHandler =
			function($form, options) {

		this.parent($form, options);

		// Save the event name to trigger upon selection for later
		this.eventName_ = options.eventName;

		// Save the default key, to select upon the first list load.
		this.defaultKey_ = options.defaultKey;

		// Expose the selectMonograph event to the containing element.
		this.publishEvent(this.eventName_);

		// We're not interested in tracking changes to this subclass since
		// it usually loads content or redirects to another page.
		this.trackFormChanges_ = false;

		// Attach form elements events.
		$form.find('select', $form).change(
				this.callbackWrapper(this.selectOptionHandler_));

		// Load the list of submissions.
		$.get(options.getOptionsUrl,
				this.callbackWrapper(this.setOptionList_), 'json');
	};

	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.form.DropdownFormHandler,
			$.pkp.controllers.form.FormHandler);


	//
	// Private properties
	//
	/**
	 * The name of the event to trigger upon selection.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.form.DropdownFormHandler.prototype.eventName_ = null;


	/**
	 * The key for the default value to select upon option load.
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.form.DropdownFormHandler.prototype.defaultKey_ = null;


	//
	// Private helper methods
	//
	/**
	 * Respond to an "item selected" call by triggering a published event.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.controllers.form.DropdownFormHandler.prototype.selectOptionHandler_ =
			function(sourceElement, event) {

		// Trigger the published event.
		this.trigger(this.eventName_, $(sourceElement).val());
	};


	/**
	 * Set the list of available items.
	 *
	 * @param {Object} ajaxContext The AJAX request context.
	 * @param {Object} jsonData A parsed JSON response object.
	 * @private
	 */
	$.pkp.controllers.form.DropdownFormHandler.prototype.setOptionList_ =
			function(ajaxContext, jsonData) {

		jsonData = this.handleJson(jsonData);
		var $form = this.getHtmlElement();
		var $select = $form.find('select');

		// For each supplied option, add it to the select menu.
		for (var optionId in jsonData.content) {
			var $option = $('<option/>');
			$option.attr('value', optionId);
			if (this.defaultKey_ == optionId) {
				$option.attr('selected', 'selected');
				this.trigger(this.eventName_, optionId);
			}
			$option.text(jsonData.content[optionId]);
			$select.append($option);
		}
	};
})(jQuery);

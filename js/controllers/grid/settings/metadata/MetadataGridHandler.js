/**
 * @file js/controllers/grid/metadata/MetadataGridHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataGridHandler
 * @ingroup js_controllers_grid
 *
 * @brief Metadata grid handler.
 */
(function($) {

	// Define the namespace.
	$.pkp.controllers.grid.settings.metadata =
			$.pkp.controllers.grid.settings.metadata || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.GridHandler
	 *
	 * @param {jQueryObject} $grid The grid this handler is
	 *  attached to.
	 * @param {Object} options Grid handler configuration.
	 */
	$.pkp.controllers.grid.settings.metadata.MetadataGridHandler =
			function($grid, options) {

		$grid.find(':checkbox').change(
				this.callbackWrapper(this.checkboxHandler_));

		this.parent($grid, options);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.grid.settings.metadata
			.MetadataGridHandler, $.pkp.controllers.grid.GridHandler);


	//
	// Extended methods from GridHandler
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.controllers.grid.settings.metadata.MetadataGridHandler.
			prototype.initialize = function(options) {

		this.parent('initialize', options);

		// Initialize the controls with sensible readonly states
		$(this.getHtmlElement()).find(':checkbox')
				.change();
	};


	//
	// Private methods.
	//
	/**
	 * Callback that will be activated when an "enabled" checkbox is clicked
	 * under the "submission" column
	 *
	 * @private
	 *
	 * @param {Object} callingContext The calling element or object.
	 * @param {Event=} opt_event The triggering event (e.g. a click on
	 *  a button.
	 * @return {boolean} Should return false to stop event processing.
	 */
	$.pkp.controllers.grid.settings.metadata.MetadataGridHandler.prototype.
			checkboxHandler_ = function(callingContext, opt_event) {
		var $checkbox = $(callingContext), checked = $checkbox.is(':checked'),
				$grid = $(this.getHtmlElement()), name = $checkbox.prop('name');

		this.getRows().each(function() {
			var fieldName = $(this).prop('id').split('-').pop(),
					$enabled = $grid.find(
							':checkbox[name=' + fieldName + 'EnabledWorkflow]'),
					$enabledSubmission = $grid.find(
							':checkbox[name=' + fieldName + 'EnabledSubmission]'),
					$required = $grid.find(
							':checkbox[name=' + fieldName + 'Required]');

			if ($enabledSubmission.prop('checked') || $required.prop('checked')) {
				$enabled.prop('checked', true);
			}
			$enabled.prop('readonly',
					$enabledSubmission.prop('checked') || $required.prop('checked'));

			if ($required.prop('checked')) {
				$enabledSubmission.prop('checked', true);
			}
			$enabledSubmission.prop('readonly', $required.prop('checked'));
		});
		return false;
	};
/** @param {jQuery} $ jQuery closure. */
}(jQuery));

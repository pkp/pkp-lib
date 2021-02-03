/**
 * @defgroup js_controllers_grid_users_stageParticipant_form
 */
/**
 * @file js/controllers/AdvancedReviewerSearchHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdvancedReviewerSearchHandler
 * @ingroup js_controllers
 *
 * @brief Handle the advanced reviewer search tab in the add reviewer modal.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer =
			$.pkp.controllers.grid.users.reviewer || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $container the wrapped page element.
	 * @param {Object} options handler options.
	 */
	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler =
			function($container, options) {
		this.parent($container, options);

		$container.find('.button').button();

		pkp.eventBus.$on('selected:reviewer', function(reviewer) {
			$('#reviewerId').val(reviewer.id);
			$('[id^="selectedReviewerName"]').html(reviewer.fullName);
			$('#searchGridAndButton').hide();
			$('#regularReviewerForm').show();
		});

		$('#regularReviewerForm').hide();

		this.bind('refreshForm', this.handleRefresh_);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler,
			$.pkp.classes.Handler);


	//
	// Private helper methods.
	//
	/**
	 * Handle the form refresh event.
	 * @private
	 * @param {HTMLElement} sourceElement The element that issued the event.
	 * @param {Event} event The triggering event.
	 * @param {string} content HTML contents to replace element contents.
	 */
	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler.prototype.
			handleRefresh_ = function(sourceElement, event, content) {

		if (content) {
			this.replaceWith(content);
		}
	};


}(jQuery));

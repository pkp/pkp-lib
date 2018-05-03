/**
 * @defgroup js_controllers_grid_users_stageParticipant_form
 */
/**
 * @file js/controllers/AdvancedReviewerSearchHandler.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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

		$('#selectReviewerButton').click(
				this.callbackWrapper(this.selectReviewer));

		$('#regularReviewerForm').hide();

		this.bind('refreshForm', this.handleRefresh_);
		this.bindGlobal('reviewersSelected', this.updateReviewerSelection);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler,
			$.pkp.classes.Handler);


	//
	// Public properties
	//
	/**
	 * Currently selected reviewer
	 * @type {Object}
	 */
	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler.
			prototype.selectedReviewer = null;


	//
	// Public methods
	//
	/**
	 * Callback that is triggered when a reviewer option is selected (but not
	 * confirmed by pressing the button)
	 * @param {Object} sourceComponent Vue component that fired the event
	 * @param {Array} selectedReviewers
	 */
	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler.prototype.
			updateReviewerSelection = function(sourceComponent, selectedReviewers) {
		var id = '',
				name = '';

		if (!selectedReviewers.length) {
			this.selectedReviewer = null;
			id = name = '';
		} else {
			// Only supports a single reviewer select at a time fo rnow
			this.selectedReviewer = selectedReviewers[0];
			id = this.selectedReviewer.id;
			name = this.selectedReviewer.fullName;
		}

		$('#reviewerId', this.getHtmlElement()).val(id);
		$('[id^="selectedReviewerName"]', this.getHtmlElement()).html(name);
	};


	/**
	 * Callback that is triggered when the button to select a reviewer is clicked
	 *
	 * @param {HTMLElement} button The button element clicked.
	 */
	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler.prototype.
			selectReviewer = function(button) {

		if (this.selectedReviewer) {
			$('#searchGridAndButton').hide();
			$('#regularReviewerForm').show();
		}
	};


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


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

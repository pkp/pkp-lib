/**
 * @defgroup js_pages_authorDashboard
 */
/**
 * @file js/pages/authorDashboard/PKPAuthorDashboardHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthorDashboardHandler
 * @ingroup js_pages_authorDashboard
 *
 * @brief Handler for the author dashboard.
 *
 * FIXME: Needs to be split up into re-usable widgets, see #6471.
 */
(function($) {

	/** @type {Object} */
	$.pkp.pages.authorDashboard = $.pkp.pages.authorDashboard || {};



	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $dashboard The HTML element encapsulating
	 *  the author dashboard page.
	 * @param {{currentStage: number}} options Handler options.
	 *  currentStage: the current workflow stage, one of the
	 *      WORKFLOW_ID_* constants.
	 */
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler =
			function($dashboard, options) {

		this.parent($dashboard, options);

		// Transform the stage sections into jQueryUI accordions.
		$('.pkp_authorDashboard_stageContainer', $dashboard).accordion({
			heightStyle: 'content',
			collapsible: true
		});

		// Set the current stage to the configured stage.
		this.setWorkflowStage_(options.currentStage);

		// Forward data changed events triggered by the author actions
		// to the corresponding grids.
		this.forwardGridEvents_();
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler,
			$.pkp.classes.Handler);


	//
	// Public static properties
	//
	/**
	 * An object that assigns stage identifiers to the
	 * corresponding CSS section selectors.
	 * @type {Object.<number,string>}
	 * @const
	 * FIXME: Is there a less verbose way to define this object?
	 */
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.CSS_SELECTORS = { };
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.CSS_SELECTORS[
			$.pkp.cons.WORKFLOW_STAGE_ID_SUBMISSION] = '#submission';
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.CSS_SELECTORS[
			$.pkp.cons.WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] = '#externalReview';
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.CSS_SELECTORS[
			$.pkp.cons.WORKFLOW_STAGE_ID_EDITING] = '#copyediting';
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.CSS_SELECTORS[
			$.pkp.cons.WORKFLOW_STAGE_ID_PRODUCTION] = '#production';


	//
	// Private properties
	//
	/**
	 * The current workflow stage.
	 * @private
	 * @type {?number}
	 */
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.prototype.
			currentStage_ = null;


	//
	// Private methods
	//
	/**
	 * Set the current workflow stage of the author dashboard.
	 * @private
	 * @param {number} newStage The stage the dashboard should
	 *  be updated to.
	 */
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.prototype.
			setWorkflowStage_ = function(newStage) {

		// Save the stage id.
		this.currentStage_ = newStage;

		// Retrieve the dashboard element.
		var $dashboard = this.getHtmlElement(),
				// Retrieve the CSS selector of the current stage's dashboard section.
				cssSelectors = this.self('CSS_SELECTORS'),
				// Enable the current stage (and all prior stages) and disable
				// all later stages.
				disabled,
				stage,
				$deactivatedSections, $activatedSection;

		for (stage = $.pkp.cons.WORKFLOW_STAGE_ID_SUBMISSION;
				stage <= $.pkp.cons.WORKFLOW_STAGE_ID_PRODUCTION; stage++) {

			if (stage <= newStage) {
				disabled = false;
			} else {
				disabled = true;
			}
			$(cssSelectors[stage], $dashboard).accordion('option', 'disable', disabled);
		}

		// Minimize all sections not representing the current stage.
		$deactivatedSections = $('.pkp_authorDashboard_stageContainer',
				$dashboard).not(cssSelectors[newStage]);
		$deactivatedSections.accordion('option', 'active', false);

		// Open the current stage's section if it's not yet open.
		$activatedSection = $(cssSelectors[newStage] +
				'.pkp_authorDashboard_stageContainer', $dashboard);
		if ($activatedSection.accordion('option', 'active') !== 0) {
			$activatedSection.accordion('option', 'active', 0);
		}
	};


	/**
	 * Forward grid refresh events triggered by user file upload actions
	 * to the corresponding grids in the dashboard.
	 * @private
	 */
	$.pkp.pages.authorDashboard.PKPAuthorDashboardHandler.prototype.
			forwardGridEvents_ = function() {

		// Retrieve the dashboard context.
		var $dashboard = this.getHtmlElement();

		// Connect the submission details grid. Use a closure
		// to save a reference to the dashboard context.
		$('#addFile', $dashboard)
				.bind('dataChanged',
				function(dataChangedEvent) {
					$('[id^="component-grid-files-review-authorreviewrevisionsgrid"]:visible',
							$dashboard)
						.trigger(dataChangedEvent);
				});
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

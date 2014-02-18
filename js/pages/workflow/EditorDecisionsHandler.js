/**
 * @file js/pages/workflow/EditorDecisionsHandler.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionsHandler
 * @ingroup js_pages_workflow
 *
 * @brief Handler for the editor decisions actions.
 *
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $editorDecisions The HTML element encapsulating
	 *  the editor decisions link actions.
	 * @param {Object} options Handler options.
	 */
	$.pkp.pages.workflow.EditorDecisionsHandler =
			function($editorDecisions, options) {

		this.parent($editorDecisions, options);

		var $editorDecisionLinks = $('a', $editorDecisions);
		$editorDecisionLinks.button();
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.pages.workflow.EditorDecisionsHandler,
			$.pkp.classes.Handler);


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

/**
 * @defgroup js_controllers_modals_editorDecision_form
 */
/**
 * @file js/controllers/modals/editorDecision/form/EditorDecisionFormHandler.js
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorDecisionFormHandler
 * @ingroup js_controllers_modals_editorDecision_form
 *
 * @brief Handle editor decision forms.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.modals = $.pkp.controllers.modals ||
			{ editorDecision: {form: { } } };



	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.form.AjaxFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler =
			function($form, options) {

		this.parent($form, options);

		this.peerReviewUrl_ = options.peerReviewUrl;
		$('#importPeerReviews', $form).click(
				this.callbackWrapper(this.importPeerReviews));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler,
			$.pkp.controllers.form.AjaxFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL of the "fetch peer reviews" operation.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			peerReviewUrl_ = null;


	//
	// Public methods
	//
	/**
	 * Retrieve reviews from the server.
	 *
	 * @param {HTMLElement} button The "import reviews" button.
	 * @param {Event} event The click event.
	 * @return {boolean} Return false to abort normal click event.
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			prototype.importPeerReviews = function(button, event) {

		$.getJSON(this.peerReviewUrl_, this.callbackWrapper(this.insertPeerReviews));
		return false;
	};


	/**
	 * Insert the peer reviews that have been returned from the server
	 * into the form.
	 *
	 * @param {Object} ajaxOptions The options that were passed into
	 *  the AJAX call.
	 * @param {Object} jsonData The data returned from the server.
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			prototype.insertPeerReviews = function(ajaxOptions, jsonData) {

		var processedJsonData = this.handleJson(jsonData),
				$form = this.getHtmlElement(),
				$textArea, currentContent;

		if (processedJsonData !== false) {
			// Add the peer review text to the personal message to the author.
			$textArea = $('textarea[id^="personalMessage"]', $form);
			currentContent = $textArea.val();

			// make a reasonable effort to look for a signature separator.
			// if there is one, insert the peer reviews before it.
			if (!currentContent.match(/__________/)) {
				$textArea.val(currentContent + processedJsonData.content);
			} else {
				$textArea.val(currentContent.
						replace(/__________/, processedJsonData.content + '__________'));
			}
		}
	};


/** @param {jQuery} $ jQuery closure. */
}(jQuery));

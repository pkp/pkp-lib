/**
 * @defgroup js_controllers_modals_editorDecision_form
 */
/**
 * @file js/controllers/modals/editorDecision/form/EditorDecisionFormHandler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
	 * @param {{
	 *  peerReviewUrl: string?,
	 *  revisionsEmail: string?,
	 *  resubmitEmail: string?
	 *  }} options form options
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler =
			function($form, options) {

		this.parent($form, options);

		if (options.peerReviewUrl !== null) {
			this.peerReviewUrl_ = options.peerReviewUrl;
			$('#importPeerReviews', $form).click(
					this.callbackWrapper(this.importPeerReviews));
		}

		// Handle revisions, resubmit and decline decision forms
		if (options.revisionsEmail !== null) {
			this.revisionsEmail_ = options.revisionsEmail;
		}
		if (options.resubmitEmail !== null) {
			this.resubmitEmail_ = options.resubmitEmail;
		}
		$('#skipEmail-send, #skipEmail-skip, ' +
				'#skipDiscussion-send, #skipDiscussion-skip', $form).change(
				this.callbackWrapper(this.toggleEmailDisplay));
		$('input[name="decision"]', $form).change(
				this.callbackWrapper(this.toggleDecisionEmail));

		// Handle promotion forms
		this.setStep('email');
		var self = this;
		$('.promoteForm-step-btn', $form).click(function(e) {
			e.preventDefault();
			e.stopPropagation();
			var step = $(e.target).data('step');
			self.setStep(/** @type {string} */ (step));
		});
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


	/**
	 * The content of the revisions requested email.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			revisionsEmail_ = null;


	/**
	 * The content of the resubmit for review email.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			resubmitEmail_ = null;


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
				$textArea = $('textarea[id^="personalMessage"]', $form),
				editor = tinyMCE.get(/** @type {string} */ ($textArea.attr('id'))),
				currentContent = editor.getContent();

		if (processedJsonData !== false) {
			// Add the peer review text to the personal message to the author.
			editor.setContent(
					currentContent + processedJsonData.content + '<br>');
		}

		// Present any new notifications to the user.
		this.trigger('notifyUser', [this.getHtmlElement()]);
	};


	/**
	 * Show or hide the email depending on the `skipEmail` setting
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			prototype.toggleEmailDisplay = function() {
		var $emailDiv = $('#sendReviews-emailContent'),
				$self = this.getHtmlElement(),
				sendEmail = false,
				createDiscussion = false,
				$discussionToggles,
				$attachementDiv = $('#libraryFileAttachments');

		$('#skipEmail-send, #skipEmail-skip', $self).each(function() {
			if ($(this).attr('id') === 'skipEmail-send' && $(this).prop('checked')) {
				sendEmail = true;
			} else if ($(this).attr('id') === 'skipEmail-skip' &&
					$(this).prop('checked')) {
				sendEmail = false;
			}
		});

		$discussionToggles = $('#skipDiscussion-send, #skipDiscussion-skip', $self);
		if ($discussionToggles.length) {
			$discussionToggles.each(function() {
				if ($(this).attr('id') === 'skipDiscussion-send' &&
						$(this).prop('checked')) {
					createDiscussion = true;
				} else if ($(this).attr('id') === 'skipDiscussion-skip' &&
						$(this).prop('checked')) {
					createDiscussion = false;
				}
			});
		}

		if (!sendEmail && !createDiscussion) {
			$emailDiv.fadeOut();
			$attachementDiv.fadeOut();
		} else {
			$emailDiv.fadeIn();
			$attachementDiv.fadeIn();
		}
	};


	/**
	 * Update the email content depending on which decision was selected.
	 *
	 * Only used in the request revisions modal to choose between two decisions.
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			prototype.toggleDecisionEmail = function() {
		var emailContent = '',
				isEmailDivVisible = $('#skipEmail-send').prop('checked'),
				$emailDiv = $('#sendReviews-emailContent'),
				textareaId = $('textarea[id^="personalMessage"]').attr('id'),
				self = this;

		$('input[name="decision"]').each(function() {
			if ($(this).attr('id') === 'decisionRevisions' &&
					$(this).prop('checked')) {
				emailContent = self.revisionsEmail_;
			} else if ($(this).attr('id') === 'decisionResubmit' &&
					$(this).prop('checked')) {
				emailContent = self.resubmitEmail_;
			}
		});

		tinyMCE.get(/** @type {string} */ (textareaId)).setContent(emailContent);

		if (isEmailDivVisible) {
			$emailDiv.hide().fadeIn();
		}
	};


	/**
	 * Display the requested step of the form
	 *
	 * Only used on promotion forms.
	 *
	 * @param {string} step Name of the step to display
	 */
	$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler.
			prototype.setStep = function(step) {
		var emailStepContent =
				$('#promoteForm-step1, .promoteForm-step-btn[data-step="files"]'),
				filesStepContent = $('#promoteForm-step2, #promoteForm-complete-btn,' +
						' .promoteForm-step-btn[data-step="email"]');

		if (step === 'files') {
			filesStepContent.show();
			emailStepContent.hide();
		} else {
			emailStepContent.show();
			filesStepContent.hide();
		}
	};


}(jQuery));

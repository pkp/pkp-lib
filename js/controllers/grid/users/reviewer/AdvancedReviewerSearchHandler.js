/**
 * @defgroup js_controllers_grid_users_stageParticipant_form
 */
/**
 * @file js/controllers/AdvancedReviewerSearchHandler.js
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdvancedReviewerSearchHandler
 * @ingroup js_controllers
 *
 * @brief Handle the advanced reviewer search tab in the add reviewer modal.
 */
(function($) {

	/** @type {Object} */
	$.pkp.controllers.grid.users.reviewer = $.pkp.controllers.grid.users.reviewer || {};

	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQueryObject} $container the wrapped page element.
	 * @param {Object} options handler options.
	 */
	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler = function($container, options) {
		this.parent($container, options);

		$container.find('.button').button();
        var self = this;
		
        pkp.eventBus.$on('selected:reviewer', function (reviewer) {
            self.handleReviewerAssign_($container, options, reviewer);
        });

        $('#regularReviewerForm').hide();

        this.bind('refreshForm', this.handleRefresh_);

		if ($container.find('input#reviewerId').val()) {
            this.initializeTinyMCE();
            this.handleReviewerAssign_($container, options, {
                id: $container.find('input#reviewerId').val(),
                fullName: options.reviewerName
            });
        }
	};

	$.pkp.classes.Helper.inherits(
		$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler,
		$.pkp.classes.Handler
	);


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

	$.pkp.controllers.grid.users.reviewer.AdvancedReviewerSearchHandler.prototype.handleReviewerAssign_ = function ($container, options, reviewer) {
		$('#reviewerId').val(reviewer.id);
		$('[id^="selectedReviewerName"]').text(reviewer.fullName);
		$('#searchGridAndButton').hide();
		$('#regularReviewerForm').show();

		// Set the email message for reviewers depending
		// on previous completed assignments
		var $textarea = $('#reviewerFormFooter [name="personalMessage"]'),
			$templateInput,
			$templateOption,
			editorId,
			editor,
			templateKey,
			templateContent,
			applyTemplateToEditor;

		if ($textarea.val()) {
			return; // The message is already set; shouldn't happen
		}

		// Only 1 template available
		$templateInput = $('#reviewerFormFooter input[name="template"]');

		// Multiple available templates
		$templateOption = $('#reviewerFormFooter select[name="template"]');

		if (options.lastRoundReviewerIds.includes(reviewer.id)) {
			templateKey = 'REVIEW_REQUEST_SUBSEQUENT';
			$templateOption.find('[value="REVIEW_REQUEST"]').remove();
		} else {
			templateKey = 'REVIEW_REQUEST';
			$templateOption.find('[value="REVIEW_REQUEST_SUBSEQUENT"]').remove();
		}

		templateContent = options.reviewerMessages[templateKey];
		editorId = /** @type {string} */ ($textarea.attr('id'));

		// Always seed the underlying <textarea> so the legacy form's
		// serialize-on-submit carries a valid email body even if the
		// TinyMCE editor for `personalMessage` hasn't finished
		// initializing by the time the moderator clicks Add Reviewer.
		// Without this, a fast click (or the form being injected into
		// a side-modal whose ancestor was hidden until just now —
		// some browsers defer iframe paint there) would race
		// `editor.setContent()` against an uninitialised editor, the
		// setContent would no-op, and the form would POST an empty
		// body — which then explodes server-side in
		// `Mailer::renderView` (lib/pkp/classes/mail/Mailer.php) with
		// "View must be ... null is given".
		$textarea.val(templateContent);
		$templateInput.val(templateKey);
		// Select the right template option to correspond
		// the one, which is set in TinyMCE
		$templateOption.find('[value="' + templateKey + '"]').prop('selected', true);

		applyTemplateToEditor = function (ed) {
			ed.setContent(templateContent);
			ed.on('activate', function () {
				if (!ed.getContent().length) {
					ed.setContent(templateContent);
				}
			});
		};

		// Mirror the body into the TinyMCE editor as well, so the
		// moderator sees the template in the WYSIWYG (and any edits
		// they make replace the textarea content via `editor.save()`
		// at submit time). If the editor is already initialised, push
		// the content immediately; otherwise wait for the
		// EditorManager's `AddEditor` + per-editor `init` events so
		// the same content appears as soon as TinyMCE is ready.
		editor = window.tinyMCE.EditorManager.get(editorId);
		if (editor && editor.initialized) {
			applyTemplateToEditor(editor);
		} else if (editor) {
			editor.on('init', function () {
				applyTemplateToEditor(editor);
			});
		} else {
			var onAddEditor = function (event) {
				if (event.editor && event.editor.id === editorId) {
					window.tinyMCE.EditorManager.off('AddEditor', onAddEditor);
					if (event.editor.initialized) {
						applyTemplateToEditor(event.editor);
					} else {
						event.editor.on('init', function () {
							applyTemplateToEditor(event.editor);
						});
					}
				}
			};
			window.tinyMCE.EditorManager.on('AddEditor', onAddEditor);
		}
	};


}(jQuery));

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
			editor,
			templateKey,
			templateContent;

		if ($textarea.val()) {
			return; // The message is already set; shouldn't happen
		}
		
		// Only 1 template available
		$templateInput = $('#reviewerFormFooter input[name="template"]');
		
		// Multiple available templates
		$templateOption = $('#reviewerFormFooter select[name="template"]');
		
		editor = window.tinyMCE.EditorManager.get($textarea.attr('id'));
		templateKey = '';

		if (options.lastRoundReviewerIds.includes(reviewer.id)) {
			templateKey = 'REVIEW_REQUEST_SUBSEQUENT';
			$templateOption.find('[value="REVIEW_REQUEST"]').remove();
		} else {
			templateKey = 'REVIEW_REQUEST';
			$templateOption.find('[value="REVIEW_REQUEST_SUBSEQUENT"]').remove();
		}

		templateContent = options.reviewerMessages[templateKey];
		editor.setContent(templateContent);
		$templateInput.val(templateKey);

		editor.on('activate', function () {
			if (!editor.getContent().length) {
				editor.setContent(templateContent);
			}
		});

		// Select the right template option to correspond
		// the one, which is set in TinyMCE
		$templateOption.find('[value="' + templateKey + '"]').prop('selected', true);
	};


}(jQuery));

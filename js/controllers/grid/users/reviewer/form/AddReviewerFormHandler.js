/**
 * @file js/controllers/grid/users/reviewer/form/AddReviewerFormHandler.js
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AddReviewerFormHandler
 * @ingroup js_controllers_grid_users_reviewer_form
 *
 * @brief Handle the Add Reviewer form (and template for message body).
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.grid.users.reviewer.form.EditReviewFormHandler
	 *
	 * @param {jQueryObject} $form the wrapped HTML form element.
	 * @param {Object} options form options.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler = function($form, options) {

		this.parent($form, options);

		// Set the URL to retrieve templates from.
		if (options.templateUrl) {
			this.templateUrl_ = options.templateUrl;
		}

		// Attach locale select events
		$form.find('#templateLocale').change(
				this.callbackWrapper(this.selectLocaleHandler_));
		// Attach form elements events.
		$form.find('.template').change(
				this.callbackWrapper(this.selectTemplateHandler_));

		// Change the NAME placeholder in the mail editor
		$('[name="firstName"]').keyup(
				this.callbackWrapper(this.addReviewerNameToMailEditor));
		$('[name="lastName"]').keyup(
				this.callbackWrapper(this.addReviewerNameToMailEditor));
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.grid.users.reviewer.form.
					AddReviewerFormHandler,
			$.pkp.controllers.grid.users.reviewer.form.
					EditReviewFormHandler);


	//
	// Private properties
	//
	/**
	 * The URL to use to retrieve template bodies
	 * @private
	 * @type {string?}
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.templateUrl_ = null;


	//
	// Protected methods
	//
	/**
	 * Show the "no files" warning.
	 * @protected
	 */
	$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler.
			prototype.showWarning = function() {
		// Call the parent showWarning to show the warning
		this.parent('showWarning');

		// Ask the reviewer form footer handler to expand the file
		// list extras-on-demand if it isn't already expanded.
		this.getHtmlElement().find('#reviewerFormFooter')
				.trigger('expandFileList');
	};


	//
	// Private methods
	//
	/**
	 * Respond to an "item selected" call by triggering a published event.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.selectTemplateHandler_ =
					function(sourceElement, event) {

		var $form = this.getHtmlElement();
		var localeSelected = $('#templateLocale').val();
		var templateID = '#template_'+localeSelected;
		var data = $form.find(templateID+', #templateLocale').serialize();
		$.post(this.templateUrl_, data,
				this.callbackWrapper(this.updateTemplate), 'json');
	};

	/**
	 * Respond to an "locale change" call by triggering a published event.
	 *
	 * @param {HTMLElement} sourceElement The element that
	 *  issued the event.
	 * @param {Event} event The triggering event.
	 * @private
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.selectLocaleHandler_ =
					function(sourceElement, event) {
		var localeSelected = sourceElement.value;
		$('.template').hide();
		$('#template_'+localeSelected).removeClass("pkp_helpers_display_none").show();
		this.selectTemplateHandler_(sourceElement, event);
	};
		

	/**
	 * Internal callback to replace the textarea with the contents of the
	 * template body.
	 *
	 * @param {HTMLElement} formElement The wrapped HTML form.
	 * @param {Object} jsonData The data returned from the server.
	 * @return {boolean} The response status.
	 */
	$.pkp.controllers.grid.users.reviewer.form.
			AddReviewerFormHandler.prototype.updateTemplate =
					function(formElement, jsonData) {

		var $form = this.getHtmlElement(),
				processedJsonData = this.handleJson(jsonData),
				jsonDataContent = (jsonData.content),
				$textarea = $form.find('textarea[name="personalMessage"]'),
				editor = tinyMCE.EditorManager.get($textarea.attr('id'));

				$textarea.attr('data-variables', JSON.stringify(jsonDataContent.variables));
				editor.setContent(jsonDataContent.body);
				this.addReviewerNameToMailEditor();
		return processedJsonData.status;
	};


	/**
	 * Add reviewerName to Email template editor
	 * @protected
	 */
	$.pkp.controllers.grid.users.reviewer.form.AddReviewerFormHandler.
			prototype.addReviewerNameToMailEditor = function() {
			var reviewerName = $('[id^="selectedReviewerName"]').html();
			$('[name^="personalMessage"]').val()
					.replace('<span class="" data-symbolic="reviewerName" contenteditable="false" data-mce-selected="1">{$reviewerName}</span>', reviewerName);
	
			$("iframe[id^='personalMessage']")
					.contents()
					.find('[data-symbolic="reviewerName"]')
					.each(function() {
						$(this).html(reviewerName);
						$(this).attr('class', '');
						$(this).removeAttr('contenteditable');
					});
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

/**
 * @defgroup js_controllers
 */
// Create the modal namespace.
jQuery.pkp.controllers = jQuery.pkp.controllers || { };

/**
 * @file js/controllers/WizardHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WizardHandler
 * @ingroup js_controllers
 *
 * @brief Basic wizard handler.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.Handler
	 *
	 * @param {jQuery} $wizard A wrapped HTML element that
	 *  represents the wizard.
	 * @param {Object} options Wizard options.
	 */
	$.pkp.controllers.WizardHandler = function($wizard, options) {
		this.parent($wizard, options);

		// Disable all but the first step.
		var disabledSteps = [];
		for (var i = 1; i < this.getNumberOfSteps(); i++) {
			disabledSteps.push(i);
		}

		// Attach the tabsshow handler.
		this.bind('tabsshow', this.tabsshow);

		// Render the wizard as jQueryUI tabs.
		$wizard.tabs({
			// Enable AJAX-driven tabs with JSON messages.
			ajaxOptions: {
				dataFilter: function(jsonData) {
					var data = $.parseJSON(jsonData);
					if (data.status === true) {
						return data.content;
					} else {
						alert(data.content);
					}
				}
			},
			disabled: disabledSteps,
			selected: 0
		});

		this.addWizardButtons_($wizard, options);

		// Bind the wizard events to handlers.
		this.bindWizardEvents();
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.WizardHandler, $.pkp.classes.Handler);


	//
	// Private properties
	//
	/**
	 * The current wizard step.
	 * @private
	 * @type {number}
	 */
	$.pkp.controllers.WizardHandler.prototype.currentStep_ = 0;


	/**
	 * The current wizard tab.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.WizardHandler.prototype.$currentTab_ = null;


	/**
	 * The continue button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.WizardHandler.prototype.continueButton_ = null;


	/**
	 * The continue button.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.WizardHandler.prototype.finishButtonText_ = null;


	//
	// Public methods
	//
	/**
	 * Event handler that is called when a tab is shown.
	 *
	 * @param {HTMLElement} tabsElement The tab element that triggered
	 *  the event.
	 * @param {Event} event The triggered event.
	 * @param {Object} ui The tabs ui data.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.controllers.WizardHandler.prototype.tabsshow =
			function(tabsElement, event, ui) {

		// Save a reference to the current tab.
		this.$currentTab_ = ui.panel;
		return false;
	};


	/**
	 * Handle the user's request to advance the wizard.
	 *
	 * NB: Please do not override this method. This is an internal event
	 * handler. Override the wizardAdvanceRequested() and wizardAdvance()
	 * event handlers instead if you want to provide custom behavior.
	 *
	 * @param {HTMLElement} buttonElement The button that triggered the event.
	 * @param {Event} event The triggered event.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.controllers.WizardHandler.prototype.continueRequest =
			function(buttonElement, event) {

		// Trigger the "advance requested" event on the current
		// tab's first child to give it a chance to veto the advance
		// request.
		var advanceRequestedEvent = new $.Event('wizardAdvanceRequested');
		this.$currentTab_.children().first().trigger(advanceRequestedEvent);

		// Trigger the wizardAdvance/wizardClose event if the
		// advanceRequestEvent handler didn't prevent it.
		if (!advanceRequestedEvent.isDefaultPrevented()) {
			var currentStep = this.getCurrentStep(),
					lastStep = this.getNumberOfSteps() - 1;
			if (currentStep < lastStep) {
				this.getHtmlElement().trigger('wizardAdvance');
			} else {
				this.getHtmlElement().trigger('wizardClose');
			}
		}
		return false;
	};


	/**
	 * Handle the user's request to cancel the wizard.
	 *
	 * NB: Please do not override this method. This is an internal event
	 * handler. Override the wizardCancel() event handler instead if you
	 * want to provide custom behavior.
	 *
	 * @param {HTMLElement} buttonElement The button that triggered the event.
	 * @param {Event} event The triggered event.
	 * @return {boolean} Should return false to stop event propagation.
	 */
	$.pkp.controllers.WizardHandler.prototype.cancelRequest =
			function(buttonElement, event) {

		// Trigger the "cancel requested" event on the current
		// tab's first child to give it a chance to veto the cancel
		// request.
		var cancelRequestedEvent = new $.Event('wizardCancelRequested');
		this.$currentTab_.children().first().trigger(cancelRequestedEvent);

		// Trigger the wizardCancel event if the
		// cancelRequestEvent handler didn't prevent it.
		if (!cancelRequestedEvent.isDefaultPrevented()) {
			this.getHtmlElement().trigger('wizardCancel');
		}
		return false;
	};


	/**
	 * Handle the wizard "enable advance" event.
	 *
	 * Widgets within the wizard should trigger this event as soon
	 * as all pre-conditions are given (e.g. valid data) to allow
	 * the user to advance.
	 *
	 * You usually don't need to override this method. It simply
	 * activates the continue button which is disabled by default.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.enableAdvance =
			function(wizardElement, event) {

		// The default implementation activates the continue button.
		this.getContinueButton().button('enable');
	};


	/**
	 * Handle the wizard "advance requested" event.
	 *
	 * Please override this method to make validation checks or submit
	 * data to the server before you let the wizard advance to the next
	 * step. You can execute event.preventDefault() if you don't want
	 * the wizard to advance because you encountered errors during
	 * validation.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.wizardAdvanceRequested =
			function(wizardElement, event) {

		// The default implementation does nothing which means that
		// the wizard will advance to the next step without validation
		// check.
	};


	/**
	 * Handle the wizard "cancel" event.
	 *
	 * You can override this method to perform custom clean-up before
	 * the wizard closes.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.wizardCancel =
			function(wizardElement, event) {

		// The default implementation simply closes the wizard.
		this.getHtmlElement().trigger('wizardClose');
	};


	/**
	 * Handle the "wizard advance" event. The default implementation
	 * advances the wizard to the next step and disables the previous step.
	 *
	 * In most cases you probably don't want to override this method unless
	 * you want to provide a different navigation experience. Form validation
	 * and submission or similar tasks should be done in the
	 * wizardAdvanceRequested() event handler.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.wizardAdvance =
			function(wizardElement, event) {

		// The wizard can only be advanced one step at a time.
		// The step cannot be greater than the number of wizard
		// tabs and not less than 1.
		var currentStep = this.getCurrentStep(),
				lastStep = this.getNumberOfSteps() - 1,
				targetStep = currentStep + 1;

		// Do not advance beyond the last step.
		if (targetStep > lastStep) {
			throw Error('Trying to set an invalid wizard step!');
		}

		// Enable the target step.
		var $wizard = this.getHtmlElement();
		$wizard.tabs('enable', targetStep);

		// Advance to the target step.
		this.setCurrentStep(targetStep);
		$wizard.tabs('select', targetStep);

		// Disable the previous step.
		$wizard.tabs('disable', currentStep);

		// If this is the last step then change the text on the
		// continue button to finish.
		if (targetStep === lastStep) {
			var $continueButton = this.getContinueButton();
			$continueButton.text(this.getFinishButtonText());
		}
	};


	//
	// Protected methods
	//
	/**
	 * Bind wizard events to default event handlers.
	 * @protected
	 */
	$.pkp.controllers.WizardHandler.prototype.bindWizardEvents = function() {
		this.bind('enableAdvance', this.enableAdvance);
		this.bind('wizardAdvanceRequested', this.wizardAdvanceRequested);
		this.bind('wizardAdvance', this.wizardAdvance);
		this.bind('wizardCancel', this.wizardCancel);
	};


	/**
	 * Get the current wizard step.
	 * @protected
	 * @return {number} The current wizard step.
	 */
	$.pkp.controllers.WizardHandler.prototype.getCurrentStep = function() {
		return this.currentStep_;
	};


	/**
	 * Set the current wizard step.
	 * @protected
	 * @param {number} currentStep The current wizard step.
	 */
	$.pkp.controllers.WizardHandler.prototype.setCurrentStep =
			function(currentStep) {
		this.currentStep_ = currentStep;
	};


	/**
	 * Get the continue button.
	 * @protected
	 * @return {jQuery} The continue button.
	 */
	$.pkp.controllers.WizardHandler.prototype.getContinueButton = function() {
		return this.continueButton_;
	};


	/**
	 * Get the finish button text.
	 * @protected
	 * @return {?string} The text to display on the continue button
	 *  in the last wizard step.
	 */
	$.pkp.controllers.WizardHandler.prototype.getFinishButtonText = function() {
		return this.finishButtonText_;
	};


	/**
	 * Count the wizard steps.
	 * @return {number} The current number of wizard steps.
	 */
	$.pkp.controllers.WizardHandler.prototype.getNumberOfSteps = function() {
		var $wizard = this.getHtmlElement();
		return $wizard.find('ul').first().children().length;
	};


	//
	// Private methods
	//
	/**
	 * Add wizard buttons to the wizard.
	 *
	 * @private
	 * @param {jQuery} $wizard The wizard element.
	 * @param {Object} options The wizard options.
	 */
	$.pkp.controllers.WizardHandler.prototype.addWizardButtons_ =
			function($wizard, options) {

		// Add space before wizard buttons.
		var $wizardButtons = $('<div id="#wizardButtons"><br /></div>');

		if (options.cancelButtonText) {
			// Add cancel button.
			var $cancelButton = $(['<a id="cancelButton" href="#">',
				options.cancelButtonText, '</a>'].join(''));
			$wizardButtons.append($cancelButton);

			// Attach the cancel request handler.
			$cancelButton.bind('click',
					this.callbackWrapper(this.cancelRequest));
		}

		if (options.continueButtonText) {
			// Add continue/finish button.
			var $continueButton = $(['<button id="continueButton"',
				'class="button align_right">', options.continueButtonText,
				'</button>'].join(''));
			$wizardButtons.append($continueButton);

			$continueButton.
					// The continue-button is disabled by default.
					button({disabled: true}).
					// Attach the continue request handler.
					bind('click',
							this.callbackWrapper(this.continueRequest));
			this.continueButton_ = $continueButton;
			if (options.finishButtonText) {
				this.finishButtonText_ = options.finishButtonText;
			}
		}

		// Insert wizard buttons.
		$wizard.after($wizardButtons);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

/**
 * @file js/controllers/WizardHandler.js
 *
 * Copyright (c) 2000-2011 John Willinsky
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
	 * @extends $.pkp.controllers.TabbedHandler
	 *
	 * @param {jQuery} $wizard A wrapped HTML element that
	 *  represents the wizard.
	 * @param {Object} options Wizard options.
	 */
	$.pkp.controllers.WizardHandler = function($wizard, options) {
		this.parent($wizard, options);

		// Start the wizard.
		this.startWizard();

		// Add the wizard buttons
		this.addWizardButtons_($wizard, options);

		// Bind the wizard events to handlers.
		this.bindWizardEvents();

		// Assume that we usually have forms in the wizard
		// tabs and bind to form events.
		this.bind('formValid', this.formValid);
		this.bind('formInvalid', this.formInvalid);
		this.bind('formSubmitted', this.formSubmitted);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.controllers.WizardHandler, $.pkp.controllers.TabbedHandler);


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
	 * The continue button.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.controllers.WizardHandler.prototype.$continueButton_ = null;


	/**
	 * The continue button label.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.WizardHandler.prototype.continueButtonText_ = null;


	/**
	 * The finish button label.
	 * @private
	 * @type {?string}
	 */
	$.pkp.controllers.WizardHandler.prototype.finishButtonText_ = null;


	//
	// Public methods
	//
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
		// tab's children to give it a chance to veto the advance
		// request.
		var advanceRequestedEvent = new $.Event('wizardAdvanceRequested');
		this.getCurrentTab().children().first().trigger(advanceRequestedEvent);

		// Advance the wizard if the advanceRequestEvent handler didn't
		// prevent it.
		if (!advanceRequestedEvent.isDefaultPrevented()) {
			this.advanceOrClose_();
		}
		return false;
	};


	/**
	 * Handle "form valid" events that may be triggered by forms in the
	 * wizard tab.
	 *
	 * @param {HTMLElement} formElement The form that triggered the event.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.formValid =
			function(formElement, event) {

		// The default implementation enables the continue button
		// as soon as the form validates.
		this.getContinueButton().button('enable');
	};


	/**
	 * Handle "form invalid" events that may be triggered by forms in the
	 * wizard tab.
	 *
	 * @param {HTMLElement} formElement The form that triggered the event.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.formInvalid =
			function(formElement, event) {

		// The default implementation disables the continue button
		// as if the form no longer validates.
		this.getContinueButton().button('disable');
	};


	/**
	 * Handle "form submitted" events that may be triggered by forms in the
	 * wizard tab.
	 *
	 * @param {HTMLElement} formElement The form that triggered the event.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.formSubmitted =
			function(formElement, event) {

		// The default implementation advances the wizard.
		this.advanceOrClose_();
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
		// tab's children to give it a chance to veto the cancel
		// request.
		var cancelRequestedEvent = new $.Event('wizardCancelRequested');
		this.getCurrentTab().children().first().trigger(cancelRequestedEvent);

		// Trigger the wizardCancel event if the
		// cancelRequestEvent handler didn't prevent it.
		if (!cancelRequestedEvent.isDefaultPrevented()) {
			this.trigger('wizardCancel');
		}
		return false;
	};


	/**
	 * Handle the wizard "cancel requested" event.
	 *
	 * Please override this method to clean up before the wizard is
	 * being canceled. You can execute event.preventDefault() if you
	 * don't want the wizard to cancel.
	 *
	 * NB: This is a fallback handler that will be called if no other
	 * event handler calls the event.stopPropagation() method.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.wizardCancelRequested =
			function(wizardElement, event) {

		// The default implementation does nothing which means that
		// the wizard will cancel immediately.
	};


	/**
	 * Handle the wizard "advance requested" event.
	 *
	 * Please override this method to make custom validation checks or
	 * place server requests before you let the wizard advance to the next
	 * step. You can execute event.preventDefault() if you don't want
	 * the wizard to advance because you encountered errors during
	 * validation.
	 *
	 * NB: This is a fallback handler that will be called if no other
	 * event handler calls the event.stopPropagation() method.
	 *
	 * @param {HTMLElement} wizardElement The wizard's HTMLElement on
	 *  which the event was triggered.
	 * @param {Event} event The triggered event.
	 */
	$.pkp.controllers.WizardHandler.prototype.wizardAdvanceRequested =
			function(wizardElement, event) {

		// If we find a form then submit it.
		var $form = this.getForm_();
		if ($form) {
			// Try to submit the form.
			$form.submit();

			// Prevent default event handling so that the form
			// can do its validation checks first.
			event.preventDefault();
		}
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
			$continueButton.button('option', 'label', this.getFinishButtonText());
		}
	};


	//
	// Protected methods
	//
	/**
	 * (Re-)Start the wizard.
	 * @protected
	 */
	$.pkp.controllers.WizardHandler.prototype.startWizard = function() {

		// Retrieve the wizard element.
		var $wizard = this.getHtmlElement();

		// Do we re-start the wizard?
		if (this.getCurrentStep() !== 0) {
			// Open the first step to restart the wizard.
			this.setCurrentStep(0);

			// Make sure that the first step is enabled, otherwise
			// we cannot select it.
			$wizard.tabs('enable', 0);

			// Go to the first step.
			$wizard.tabs('select', 0);

			// Reset the continue button label.
			var $continueButton = this.getContinueButton();
			$continueButton.button('option', 'label', this.getContinueButtonText());
		}

		// Disable all but the first step.
		var disabledSteps = [];
		for (var i = 1; i < this.getNumberOfSteps(); i++) {
			disabledSteps.push(i);
		}
		$wizard.tabs('option', 'disabled', disabledSteps);
	};


	/**
	 * Bind wizard events to default event handlers.
	 * @protected
	 */
	$.pkp.controllers.WizardHandler.prototype.bindWizardEvents = function() {
		this.bind('wizardCancelRequested', this.wizardCancelRequested);
		this.bind('wizardAdvanceRequested', this.wizardAdvanceRequested);
		this.bind('wizardAdvance', this.wizardAdvance);
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
		return this.$continueButton_;
	};


	/**
	 * Get the continue button label.
	 * @protected
	 * @return {?string} The text to display on the continue button.
	 */
	$.pkp.controllers.WizardHandler.prototype.getContinueButtonText = function() {
		return this.continueButtonText_;
	};


	/**
	 * Get the finish button label.
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
	 * Return the current form (if any).
	 *
	 * @private
	 * @return {?jQuery} The form (if any).
	 */
	$.pkp.controllers.WizardHandler.prototype.getForm_ = function() {
		// If we find a form in the current tab then return it.
		var $tabContent = this.getCurrentTab().children().first();
		if ($tabContent.is('form')) {
			return $tabContent;
		}

		return null;
	};


	/**
	 * Continue to the next step or, if this is the last step,
	 * then close the wizard.
	 *
	 * @private
	 */
	$.pkp.controllers.WizardHandler.prototype.advanceOrClose_ =
			function() {
		var currentStep = this.getCurrentStep(),
				lastStep = this.getNumberOfSteps() - 1;

		if (currentStep < lastStep) {
			this.trigger('wizardAdvance');
		} else {
			this.trigger('wizardClose');
		}
	};


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
				'class="button pkp_helpers_align_right">', options.continueButtonText,
				'</button>'].join('')).button();
			$wizardButtons.append($continueButton);

			$continueButton.
					// Attach the continue request handler.
					bind('click',
							this.callbackWrapper(this.continueRequest));
			this.$continueButton_ = $continueButton;

			// Remember the button labels.
			this.continueButtonText_ = options.continueButtonText;
			if (options.finishButtonText) {
				this.finishButtonText_ = options.finishButtonText;
			} else {
				this.finishButtonText_ = options.continueButtonText;
			}
		}

		// Insert wizard buttons.
		$wizard.after($wizardButtons);
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

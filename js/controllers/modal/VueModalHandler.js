/**
 * @file js/controllers/modal/VueModalHandler.js
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VueModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A modal that opens native Vue.js component.
 * It needs to be added in ModalManager to be available.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.ModalHandler
	 *
	 * @param {jQueryObject} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object} options non-default Dialog options
	 *  to be passed into the dialog widget.
	 *
	 *  Options are:
	 *  - component name, needs to be added in ModalManager.vue.
	 *  - props - props passed to the component.
	 */
	$.pkp.controllers.modal.VueModalHandler = function($handledElement, options) {
		this.parent($handledElement, options);

	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.VueModalHandler,
			$.pkp.controllers.modal.ModalHandler);


	//
	// Protected methods
	//
	/** @inheritDoc */
	$.pkp.controllers.modal.VueModalHandler.prototype.checkOptions =
			function(options) {
		// Check the mandatory options of the ModalHandler handler.
		if (!this.parent('checkOptions', options)) {
			return false;
		}

		// Check for our own mandatory options.
		return typeof options.component === 'string';
	};


	/** @inheritDoc */
	$.pkp.controllers.modal.VueModalHandler.prototype.mergeOptions =
			function(options) {

		// Call parent.
		return /** @type {Object} */ (this.parent('mergeOptions', options));
	};


	/**
	 * Open the modal
	 * @param {jQueryObject} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @protected
	 */
	$.pkp.controllers.modal.VueModalHandler.prototype.modalOpen =
			function($handledElement) {
		this.parent('modalOpen', $handledElement);
		// Retrieve remote modal content.
		pkp.eventBus.$emit('open-modal-vue', {
			component: this.options.component,
			modalId: this.uniqueModalId,
			// passing modalHandler to be able to bridge events
			options: Object.assign({}, this.options, {modalHandler: this})
		});
	};



}(jQuery));

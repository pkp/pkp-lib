/**
 * @file js/controllers/modal/TabbedModalHandler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class TabbedModalHandler
 * @ingroup js_controllers_modal
 *
 * @brief A modal that contains a tabbed modal and handles its events.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.controllers.modal.AjaxModalHandler
	 *
	 * @param {jQuery} $handledElement The clickable element
	 *  the modal will be attached to.
	 * @param {Object} options non-default Dialog options
	 *  to be passed into the dialog widget.
	 *
	 *  Options are:
	 *  - all options documented for the AjaxModalHandler.
	 */
	$.pkp.controllers.modal.TabbedModalHandler =
			function($handledElement, options) {

		this.parent($handledElement, options);

		// Subscribe to tabbed modal events.
		this.bind('modalClose', this.modalClose);
	};
	$.pkp.classes.Helper.inherits($.pkp.controllers.modal.TabbedModalHandler,
			$.pkp.controllers.modal.AjaxModalHandler);


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

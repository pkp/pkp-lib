/**
 * @file js/classes/linkAction/PostAndRedirectRequest.js
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PostAndRedirectRequest
 * @ingroup js_classes_linkAction
 *
 * @brief An action request that will post data and follow the given URL.
 * It use a form to post the data with the action parameter set to the
 * given URL.
 */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.linkAction.LinkActionRequest
	 *
	 * @param {jQuery} $linkActionElement The element the link
	 *  action was attached to.
	 * @param {Object} options Configuration of the link action
	 *  request.
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest =
			function($linkActionElement, options) {

		// Set the href of the link.
		var $link = $('a', $linkActionElement);
		if ($link.is('a')) {
			$link.attr('href', options.url);
		} 
		
		var $formElement = $('form', $linkActionElement);
		var responseHandler = $.pkp.classes.Helper.curry(
				this.responseHandler_, this);
		$formElement.bind('submit', responseHandler);
		$formElement.attr('action', options.url);
		$formElement.append('<input type="hidden" name="linkActionPostData" value="' + options.postData + '" />');
				
		this.$formElement_ = $formElement;

		this.parent($linkActionElement, options);
	};
	$.pkp.classes.Helper.inherits(
			$.pkp.classes.linkAction.PostAndRedirectRequest,
			$.pkp.classes.linkAction.LinkActionRequest);

	
	//
	// Protected properties
	//
	/**
	 * The element the link action was attached to.
	 * @protected
	 * @type {Object}
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.
			$formElement_ = null;
	
	
	//
	// Getters and Setters.
	//
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.getFormElement =
			function() {
		return this.$formElement_;
	}

	//
	// Public methods
	//
	/**
	 * @inheritDoc
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.activate =
			function(element, event) {

		var $formElement = this.getFormElement();
		$formElement.trigger('submit');

		return this.parent('activate', element, event);
	};
	
	
	//
	// Private helper methods.
	//
	/**
	 * The form submit event handler.
	 */
	$.pkp.classes.linkAction.PostAndRedirectRequest.prototype.responseHandler_ =
			function(event) {
		event.stopPropagation();
		this.finish();
	}


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

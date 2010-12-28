/**
 * @file js/classes/Handler.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Handler
 * @ingroup js_classes
 *
 * @brief Base class for handlers bound to a DOM HTML element.
 */
(function($) {


	/**
	 * @constructor
	 * @param {jQuery} $element A DOM element to which
	 *  this handler is bound.
	 */
	$.pkp.classes.Handler = function($element) {
		// Check whether a single element was passed in.
		if ($element.length > 1) {
			throw Error('jQuery selector contained more than one handler!');
		}

		// Save a pointer to the bound element in the handler.
		this.$htmlElement_ = $element;

		// Check whether a handler has already been bound
		// to the element.
		if (this.data('handler') !== undefined) {
			throw Error(['The handler "', this.getObjectName(),
						'" has already been bound to the selected element!'].join(''));
		}

		// Initialize object properties.
		this.eventBindings_ = { };
		this.dataItems_ = { };

		// Bind the handler to the DOM element.
		this.data('handler', this);
	};


	//
	// Private instance variables
	//
	/**
	 * The HTML element this handler is bound to.
	 * @private
	 * @type {jQuery}
	 */
	$.pkp.classes.Handler.prototype.$htmlElement_ = null;


	/**
	 * A list of event bindings for this handler.
	 * @private
	 * @type {Object.<string, Array>}
	 */
	$.pkp.classes.Handler.prototype.eventBindings_ = null;


	/**
	 * A list of data items bound to the DOM element
	 * managed by this handler.
	 * @private
	 * @type {Object.<string, boolean>}
	 */
	$.pkp.classes.Handler.prototype.dataItems_ = null;


	//
	// Protected static methods
	//
	/**
	 * Retrieve the bound handler from the jQuery element.
	 * @protected
	 * @param {jQuery} $element The element to which the
	 *  handler was attached.
	 * @return {Object} The retrieved handler.
	 */
	$.pkp.classes.Handler.getHandler = function($element) {
		// Retrieve the handler. We cannot do this with our own
		// data() method because this method cannot be called
		// in the context of the handler if it's purpose is to
		// retrieve the handler. This should be the only place
		// at all where we have to do access element data
		// directly.
		var handler = $element.data('pkp.handler');

		// Check whether the handler exists.
		if (!(handler instanceof $.pkp.classes.Handler)) {
			throw Error('There is no handler bound to this element!');
		}

		return handler;
	};


	//
	// Protected methods
	//
	/**
	 * Returns the HTML element this handler is bound to.
	 *
	 * @protected
	 * @return {jQuery} The element this handler is bound to.
	 */
	$.pkp.classes.Handler.prototype.getHtmlElement = function() {
		$.pkp.classes.Handler.checkContext_(this);

		// Return the HTML element.
		return this.$htmlElement_;
	};


	/**
	 * Bind an event to a handler operation.
	 *
	 * This will be done with a generic event handler
	 * to make sure that we get a chance to re-set
	 * 'this' to the handler before we call the actual
	 * handler method.
	 *
	 * @protected
	 * @param {string} eventName The name of the event
	 *  to be bound. See jQuery.bind() for event names.
	 * @param {Function} handler The event handler to
	 *  be called when the even is triggered.
	 */
	$.pkp.classes.Handler.prototype.bind = function(eventName, handler) {
		$.pkp.classes.Handler.checkContext_(this);

		// Store the event binding internally
		this.eventBindings_[eventName] = this.eventBindings_[eventName] || [];
		this.eventBindings_[eventName].push(handler);

		// Determine the event namespace.
		var eventNamespace = '.pkpHandler';
		if (eventName === 'pkpRemoveHandler') {
			// We have a special namespace for the remove event
			// because it needs to be triggered when all other
			// events have already been removed.
			eventNamespace = '.pkpHandlerRemove';
		}
		// Bind the generic event handler to the event within our namespace.
		this.getHtmlElement().bind(eventName + eventNamespace, this.handleEvent);
	};


	/**
	 * Add or retrieve a data item to/from the DOM element
	 * this handler is managing.
	 *
	 * Always use this method if you want to store data
	 * items. It makes sure that your items will be properly
	 * namespaced and it also guarantees correct garbage
	 * collection of your items once the handler is removed.
	 *
	 * @protected
	 * @param {string} key The name of the item to be stored
	 *  or retrieved.
	 * @param {*=} value The data item to be stored. If no item
	 *  is given then the existing value for the given key
	 *  will be returned.
	 * @return {*} The cached data item.
	 */
	$.pkp.classes.Handler.prototype.data = function(key, value) {
		$.pkp.classes.Handler.checkContext_(this);

		// Namespace the key.
		key = 'pkp.' + key;

		if (value !== undefined) {
			// Add the key to the list of data items
			// that need to be garbage collected.
			this.dataItems_[key] = true;
		}

		// Add/retrieve the data to/from the
		// element's data cache.
		return this.getHtmlElement().data(key, value);
	};


	/**
	 * Create a closure that calls the callback in the
	 * context of the handler object.
	 *
	 * NB: Always make sure that the callback is properly
	 * unbound and freed for garbage collection. Otherwise
	 * you might create a memory leak. If you want to bind
	 * an event to the HTMLElement handled by this handler
	 * then always use the above bind() method instead which
	 * is safer.
	 *
	 * @protected
	 * @param {Function} callback The callback to be wrapped.
	 * @return {Function} The wrapped callback.
	 */
	$.pkp.classes.Handler.prototype.callbackWrapper = function(callback) {
		$.pkp.classes.Handler.checkContext_(this);

		// Create a closure that calls the event handler
		// in the right context.
		var handlerContext = this;
		return function() {
			var args = $.makeArray(arguments);
			args.unshift(this);
			return callback.apply(handlerContext, args);
		};
	};


	//
	// Public methods
	//
	/**
	 * A generic event dispatcher that will be bound to
	 * all handler events. See bind() above.
	 *
	 * @this {HTMLElement}
	 * @param {Event} event The jQuery event object.
	 * @return {boolean} Return value to be passed back
	 *  to jQuery.
	 */
	$.pkp.classes.Handler.prototype.handleEvent = function(event) {
		// This handler is always called out of the
		// handler context.
		var $callingElement = $(this);

		// Identify the targeted handler.
		var handler = $.pkp.classes.Handler.getHandler($callingElement);

		// Make sure that we really got the right element.
		if ($callingElement[0] !== handler.getHtmlElement.call(handler)[0]) {
			throw Error(['An invalid handler is bound to the calling ',
				'element of an event!'].join(''));
		}

		// Retrieve the event handlers for the given event type.
		var boundEvents = handler.eventBindings_[event.type];
		if (boundEvents === undefined) {
			// We have no handler for this event but we also
			// don't allow bubbling of events outside of the
			// GUI widget!
			return false;
		}

		// Call all event handlers.
		var args = $.makeArray(arguments), returnValue = true;
		args.unshift(this);
		for (var i = 0, l = boundEvents.length; i < l; i++) {
			// Invoke the event handler in the context
			// of the handler object.
			if (boundEvents[i].apply(handler, args) === false) {
				// False overrides true.
				returnValue = false;
			}

			// Stop immediately if one of the handlers requests this.
			if (event.isImmediatePropagationStopped()) {
				break;
			}
		}

		// We do not allow bubbling of events outside of the GUI widget!
		event.stopPropagation();

		// Return the event handler status.
		return returnValue;
	};


	/**
	 * Completely remove all traces of the handler from the
	 * HTML element to which it is bound and leave the element in
	 * it's previous state.
	 *
	 * Subclasses should override this method if necessary but
	 * should always this implementation.
	 */
	$.pkp.classes.Handler.prototype.remove = function() {
		$.pkp.classes.Handler.checkContext_(this);

		// Remove all event handlers in our namespace.
		var $element = this.getHtmlElement();
		$element.unbind('.pkpHandler');

		// Remove all our data items except for the
		// handler itself.
		for (var key in this.dataItems_) {
			if (key === 'pkp.handler') {
				continue;
			}
			$element.removeData(key);
		}

		// Trigger the remove event, then delete it.
		$element.trigger('pkpRemoveHandler');
		$element.unbind('.pkpHandlerRemove');

		// Delete the handler.
		$element.removeData('pkp.handler');
	};


	//
	// Private static methods
	//
	/**
	 * Check the context of a method invocation.
	 *
	 * @private
	 * @param {Object} context The function context
	 *  to be tested.
	 */
	$.pkp.classes.Handler.checkContext_ = function(context) {
		if (!(context instanceof $.pkp.classes.Handler)) {
			throw Error('Trying to call handler method in non-handler context!');
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

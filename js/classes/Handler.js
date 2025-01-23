/**
 * @file js/classes/Handler.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Handler
 * @ingroup js_classes
 *
 * @brief Base class for handlers bound to a DOM HTML element.
 */
/*global _, pkp */
(function($) {


	/**
	 * @constructor
	 *
	 * @extends $.pkp.classes.ObjectProxy
	 *
	 * @param {jQueryObject} $element A DOM element to which
	 *  this handler is bound.
	 * @param {Object} options Handler options.
	 */
	$.pkp.classes.Handler = function($element, options) {
		var $parents, self, i;

		// Check whether a single element was passed in.
		if ($element.length > 1) {
			throw new Error('jQuery selector contained more than one handler!');
		}

		// Save a pointer to the bound element in the handler.
		this.$htmlElement_ = $element;

		// Check whether a handler has already been bound
		// to the element.
		if (this.data('handler') !== undefined) {
			throw new Error(['The handler "', this.getObjectName(),
						'" has already been bound to the selected element!'].join(''));
		}

		// Initialize object properties.
		this.eventBindings_ = { };
		this.dataItems_ = { };
		this.publishedEvents_ = { };
		this.handlerChildren_ = [];
		this.globalEventListeners_ = { };

		// Register this handler with a parent handler if one is found. This
		// allows global events to be de-registered when a parent handler is
		// refreshed.
		$parents = this.$htmlElement_.parents();
		self = this;
		$parents.each(function(i) {
			if ($.pkp.classes.Handler.hasHandler($($parents[i]))) {
				$.pkp.classes.Handler.getHandler($($parents[i]))
						.handlerChildren_.push(self);
				return; // only attach to the closest parent handler
			}
		});

		if (options.eventBridge) {
			// Configure the event bridge.
			this.eventBridge_ = options.eventBridge;
		}

		// The "publishChangeEvents" option can be used to specify
		// a list of event names that will also be published upon
		// content change.
		if (options.publishChangeEvents) {
			this.publishChangeEvents_ = options.publishChangeEvents;
			for (i = 0; i < this.publishChangeEvents_.length; i++) {
				this.publishEvent(this.publishChangeEvents_[i]);
			}
		} else {
			this.publishChangeEvents_ = [];
		}

		// Bind the handler to the DOM element.
		this.data('handler', this);
	};


	//
	// Private properties
	//
	/**
	 * Optional list of publication events.
	 * @private
	 * @type {Array}
	 */
	$.pkp.classes.Handler.prototype.publishChangeEvents_ = null;


	/**
	 * The HTML element this handler is bound to.
	 * @private
	 * @type {jQueryObject}
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


	/**
	 * A list of published events.
	 * @private
	 * @type {Object.<string, boolean>}
	 */
	$.pkp.classes.Handler.prototype.publishedEvents_ = null;


	/**
	 * An HTML element id to which we'll forward all handler events.
	 * @private
	 * @type {?string}
	 */
	$.pkp.classes.Handler.prototype.eventBridge_ = null;


	/**
	 * Global event bindings. These are tracked so they can be deregistered when
	 * the handler is destroyed.
	 * @private
	 * @type {Object}
	 */
	$.pkp.classes.Handler.prototype.globalEventListeners_ = null;


	//
	// Public static methods
	//
	/**
	 * Retrieve the bound handler from the jQuery element.
	 * @param {jQueryObject} $element The element to which the
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
			throw new Error('There is no handler bound to this element!');
		}

		return handler;
	};


	/**
	 * Check if a jQuery element has a handler bound to it
	 *
	 * @param {jQueryObject} $element The element to check for a handler
	 * @return {boolean}
	 */
	$.pkp.classes.Handler.hasHandler = function($element) {
		return $element.data('pkp.handler') instanceof $.pkp.classes.Handler;
	};


	//
	// Public methods
	//
	/**
	 * Returns the HTML element this handler is bound to.
	 *
	 * @return {jQueryObject} The element this handler is bound to.
	 */
	$.pkp.classes.Handler.prototype.getHtmlElement = function() {
		$.pkp.classes.Handler.checkContext_(this);

		// Return the HTML element.
		return this.$htmlElement_;
	};


	/**
	 * Publish change events. (See options.publishChangeEvents.)
	 */
	$.pkp.classes.Handler.prototype.publishChangeEvents = function() {
		var i;
		for (i = 0; i < this.publishChangeEvents_.length; i++) {
			this.trigger(this.publishChangeEvents_[i]);
		}
	};


	/**
	 * A generic event dispatcher that will be bound to
	 * all handler events. See bind() above.
	 *
	 * @this {HTMLElement}
	 * @param {jQuery.Event} event The jQuery event object.
	 * @return {boolean} Return value to be passed back
	 *  to jQuery.
	 */
	$.pkp.classes.Handler.prototype.handleEvent = function(event) {
		var $callingElement, handler, boundEvents, args, returnValue, i, l;

		// This handler is always called out of the
		// handler context.
		$callingElement = $(this);

		// Identify the targeted handler.
		handler = $.pkp.classes.Handler.getHandler($callingElement);

		// Make sure that we really got the right element.
		if ($callingElement[0] !== handler.getHtmlElement.call(handler)[0]) {
			throw new Error(['An invalid handler is bound to the calling ',
				'element of an event!'].join(''));
		}

		// Retrieve the event handlers for the given event type.
		boundEvents = handler.eventBindings_[event.type];
		if (boundEvents === undefined) {
			// We have no handler for this event but we also
			// don't allow bubbling of events outside of the
			// GUI widget!
			return false;
		}

		// Call all event handlers.
		args = $.makeArray(arguments);
		returnValue = true;
		args.unshift(this);
		for (i = 0, l = boundEvents.length; i < l; i++) {
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
	 * @param {Function} callback The callback to be wrapped.
	 * @param {Object=} opt_context Specifies the object which
	 *  |this| should point to when the function is run.
	 *  If the value is not given, the context will default
	 *  to the handler object.
	 * @return {Function} The wrapped callback.
	 */
	$.pkp.classes.Handler.prototype.callbackWrapper =
			function(callback, opt_context) {

		$.pkp.classes.Handler.checkContext_(this);

		// Create a closure that calls the event handler
		// in the right context.
		if (!opt_context) {
			opt_context = this;
		}
		return function() {
			var args;
			args = $.makeArray(arguments);
			args.unshift(this);
			return callback.apply(opt_context, args);
		};
	};


	/**
	 * This callback can be used to handle simple remote server requests.
	 *
	 * @param {Object} ajaxOptions AJAX options.
	 * @param {Object} jsonData A JSON object.
	 * @return {Object|boolean} The parsed JSON data if no error occurred,
	 *  otherwise false.
	 */
	$.pkp.classes.Handler.prototype.remoteResponse =
			function(ajaxOptions, jsonData) {

		return this.handleJson(jsonData);
	};


	/**
	 * Completely remove all traces of the handler from the
	 * HTML element to which it is bound and leave the element in
	 * its previous state.
	 *
	 * Subclasses should override this method if necessary but
	 * should always call this implementation.
	 */
	$.pkp.classes.Handler.prototype.remove = function() {
		$.pkp.classes.Handler.checkContext_(this);
		var $element, key;

		// Remove all event handlers in our namespace.
		$element = this.getHtmlElement();
		$element.unbind('.pkpHandler');
		// form-success which is registered in ModalHandler was not
		// removed when Modal closed. This ensures that binding is removed.
		this.unbindGlobalAll();

		// Remove all our data items except for the
		// handler itself.
		for (key in this.dataItems_) {
			if (key !== 'pkp.handler') {
				$element.removeData(key);
			}
		}

		// Trigger the remove event, then delete it.
		$element.trigger('pkpRemoveHandler');
		$element.unbind('.pkpHandlerRemove');

		// Delete the handler.
		$element.removeData('pkp.handler');
	};


	/**
	 * This function should be used to pre-process a JSON response
	 * from the server.
	 *
	 * @param {Object} jsonData The returned server response data.
	 * @return {Object|boolean} The returned server response data or
	 *  false if an error occurred.
	 */
	$.pkp.classes.Handler.prototype.handleJson = function(jsonData) {
		var key, eventData;

		if (!jsonData) {
			throw new Error('Server error: Server returned no or invalid data!');
		}

		if (jsonData.status === true) {
			// Trigger events passed from the server
			for (key in jsonData.events) {
				eventData = jsonData.events[key].hasOwnProperty('data') ?
						jsonData.events[key].data : null;
				if (eventData !== null && eventData.isGlobalEvent) {
					eventData.handler = this;
					pkp.eventBus.$emit(jsonData.events[key].name, eventData);
				} else {
					this.trigger(jsonData.events[key].name, eventData);
				}
			}
			return jsonData;
		} else {
			// If we got an error message then display it.
			if (jsonData.content) {
				alert(jsonData.content);
			}
			return false;
		}
	};


	//
	// Protected methods
	//
	/**
	 * Sets the HTML element this handler is bound to.
	 *
	 * @protected
	 * @param {jQueryObject} $htmlElement The element this handler should be bound
	 *   to.
	 * @return {jQueryObject} Passes through the supplied parameter.
	 */
	$.pkp.classes.Handler.prototype.setHtmlElement = function($htmlElement) {
		$.pkp.classes.Handler.checkContext_(this);

		// Return the HTML element.
		this.$htmlElement_ = $htmlElement;
		return $htmlElement;
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

		if (!this.eventBindings_[eventName]) {
			// Initialize the event store for this event.
			this.eventBindings_[eventName] = [];

			// Determine the event namespace.
			var eventNamespace;
			eventNamespace = '.pkpHandler';
			if (eventName === 'pkpRemoveHandler') {
				// We have a special namespace for the remove event
				// because it needs to be triggered when all other
				// events have already been removed.
				eventNamespace = '.pkpHandlerRemove';
			}

			// Bind the generic event handler to the event within our namespace.
			this.getHtmlElement().bind(eventName + eventNamespace, this.handleEvent);
		}

		// Store the event binding internally
		this.eventBindings_[eventName].push(handler);
	};


	/**
	 * Unbind an event from a handler operation.
	 *
	 * @protected
	 * @param {string} eventName The name of the event
	 *  to be bound. See jQuery.bind() for event names.
	 * @param {Function} handler The event handler to
	 *  be called when the even is triggered.
	 * @return {boolean} True, if a handler was found and
	 *  removed, otherwise false.
	 */
	$.pkp.classes.Handler.prototype.unbind = function(eventName, handler) {
		$.pkp.classes.Handler.checkContext_(this);

		// Remove the event from the internal event cache.
		if (!this.eventBindings_[eventName]) {
			return false;
		}

		var i, length;
		for (i = 0, length = this.eventBindings_[eventName].length; i < length; i++) {
			if (this.eventBindings_[eventName][i] === handler) {
				this.eventBindings_[eventName].splice([i], 1);
				break;
			}
		}

		if (this.eventBindings_[eventName].length === 0) {
			// If this was the last event then unbind the generic event handler.
			delete this.eventBindings_[eventName];
			this.getHtmlElement().unbind(eventName, this.handleEvent);
		}

		return true;
	};


	/**
	 * Bind a global event to a handler operation.
	 *
	 * Binds a callback function to fire when a global event is triggered on
	 * the global event router.
	 *
	 * @param {string} eventName The name of the event to bind to.
	 * @param {Function} callback The function to firewhen the event is triggered
	 */
	$.pkp.classes.Handler.prototype.bindGlobal = function(eventName, callback) {
		if (typeof this.globalEventListeners_[eventName] === 'undefined') {
			this.globalEventListeners_[eventName] = [];
		}
		var wrapper = this.callbackWrapper(callback);
		this.globalEventListeners_[eventName].push(wrapper);
		pkp.eventBus.$on(eventName, wrapper);
	};


	/**
	 * Unbind a global event from a handler operation.
	 *
	 * If passing a `null` callback, all callbacks bound to eventName by this
	 * handler will be unbound. See: http://backbonejs.org/#Events-off
	 *
	 * @see $.pkp.classes.Handler.prototype.bindGlobal()
	 * @param {string} eventName The name of the event to bind to
	 * @param {Function} callback The function to fire when event is triggered
	 */
	$.pkp.classes.Handler.prototype.unbindGlobal = function(eventName, callback) {
		var wrapper = this.callbackWrapper(callback),
				globalEventListeners = [];
		if (typeof this.globalEventListeners_[eventName] !== 'undefined') {
			this.globalEventListeners.forEach(function(callback) {
				if (callback !== wrapper) {
					globalEventListeners.push(callback);
				}
			});
			this.globalEventListeners = globalEventListeners;
		}
		pkp.eventBus.$off(eventName, wrapper);
	};


	/**
	 * Unbind all global event listeners on this handler and any child handlers
	 */
	$.pkp.classes.Handler.prototype.unbindGlobalAll = function() {
		var event, callback;
		if (typeof this.globalEventListeners_ !== 'undefined') {
			for (event in this.globalEventListeners_) {
				for (callback in this.globalEventListeners_[event]) {
					pkp.eventBus.$off(event, this.globalEventListeners_[event][callback]);
				}
			}
		}
		this.globalEventListeners = null;
		this.unbindGlobalChildren();
	};


	/**
	 * Unbind all global event listeners on child handlers
	 */
	$.pkp.classes.Handler.prototype.unbindGlobalChildren = function() {
		this.handlerChildren_.forEach(function(childHandler) {
			// Handler in legacy JS framework
			if (typeof childHandler.unbindGlobalAll !== 'undefined') {
				childHandler.unbindGlobalAll();
			// Handler in new Vue.js framework
			} else if (typeof childHandler.$destroy !== 'undefined') {
				delete pkp.registry._instances[childHandler.id];
				childHandler.$destroy();
			}
		});
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
	 * @param {Object=} opt_value The data item to be stored. If no item
	 *  is given then the existing value for the given key
	 *  will be returned.
	 * @return {Object} The cached data item.
	 */
	$.pkp.classes.Handler.prototype.data = function(key, opt_value) {
		$.pkp.classes.Handler.checkContext_(this);

		// Namespace the key.
		key = 'pkp.' + key;

		if (opt_value !== undefined) {
			// Add the key to the list of data items
			// that need to be garbage collected.
			this.dataItems_[key] = true;
		}

		// Add/retrieve the data to/from the
		// element's data cache.
		if (arguments.length > 1) {
			return this.getHtmlElement().data(key, opt_value);
		} else {
			return this.getHtmlElement().data(key);
		}
	};


	/**
	 * This function should be used to let the element emit events
	 * that bubble outside the widget and are published over the
	 * event bridge.
	 *
	 * @protected
	 * @param {string} eventName The event to be triggered.
	 * @param {Array=} opt_data Additional event data.
	 */
	$.pkp.classes.Handler.prototype.trigger =
			function(eventName, opt_data) {

		if (opt_data === undefined) {
			opt_data = null;
		}

		// Trigger the event on the handled element.
		var $handledElement = this.getHtmlElement();
		$handledElement.triggerHandler(eventName, opt_data);

		// Trigger the event publicly if it's not
		// published anyway.
		if (!this.publishedEvents_[eventName]) {
			this.triggerPublicEvent_(eventName, opt_data);
		}
	};


	/**
	 * Publish an event triggered by a nested widget. This event
	 * will bubble outside the widget and will also be published
	 * over the event bridge.
	 *
	 * @param {string} eventName The event name.
	 */
	$.pkp.classes.Handler.prototype.publishEvent = function(eventName) {
		// If the event has been published before then do nothing.
		if (this.publishedEvents_[eventName]) {
			return;
		}

		// Add the event to the published event list.
		this.publishedEvents_[eventName] = true;

		this.bind(eventName, function(context, privateEvent, var_args) {
			// Retrieve additional event data.
			var eventData = null;
			if (arguments.length > 2) {
				eventData = Array.prototype.slice.call(arguments, 2);
			}

			// Re-trigger the private event publicly.
			this.triggerPublicEvent_(eventName, eventData);
		});
	};


	/**
	 * Handle the "show more" and "show less" clicks triggered by the
	 * links in longer text items.
	 *
	 * @param {Event} event The event.
	 */
	$.pkp.classes.Handler.prototype.switchViz = function(event) {
		var eventElement = event.currentTarget;
		$(eventElement).parent().parent().find('span').toggle();
	};


	/**
	 * Initialize TinyMCE instances.
	 *
	 * There are instances where TinyMCE is not initialized with the call to
	 * init(). These occur when content is loaded after the fact (via AJAX).
	 *
	 * In these cases, search for richContent fields and initialize them.
	 */
	$.pkp.classes.Handler.prototype.initializeTinyMCE =
			function() {

		if (typeof tinyMCE !== 'undefined') {
			var $element = this.getHtmlElement(),
					elementId = $element.attr('id'),
					settings = $.pkp.controllers.SiteHandler.prototype.tinymceParams_;

			settings.defaultToolbar = settings.toolbar;

			$('#' + elementId).find('.richContent').each(function() {
				var id = /** @type {string} */ ($(this).attr('id')),
						icon = $('<div></div>'),
						iconParent = $('<div></div>'),
						classes, i, editor,
						settings = $.pkp.controllers.SiteHandler.prototype.tinymceParams_;

				// Set the extended toolbar, if requested
				if ($(this).hasClass('extendedRichContent')) {
					settings.toolbar = settings.richToolbar;
				} else {
					settings.toolbar = settings.defaultToolbar;
				}

				editor = tinyMCE.EditorManager.createEditor(id, settings).render();

				// For localizable text fields add globe and flag icons
				if ($(this).hasClass('localizable') || $(this).hasClass('flag')) {
					icon.addClass('mceLocalizationIcon localizable');
					icon.attr('id', 'mceLocalizationIcon-' + id);
					$(this).wrap(iconParent);
					$(this).parent().append(icon);

					if ($(this).hasClass('localizable')) {
						// Add a globe icon to localizable TinyMCE textareas
						icon.addClass('mceGlobe');
					} else if ($(this).hasClass('flag')) {
						// Add country flag icon to localizable TinyMCE textareas
						classes = $(this).attr('class').split(' ');
						if (classes.length) {
							for (i = 0; i < classes.length; i++) {
								if (classes[i].match(/^flag_[a-z]{2}_[A-Z]{2}$/)) {
									icon.addClass(classes[i]);
									break;
								}
							}
						}
					}
				}
			});
		}
	};


	//
	// Private methods
	//
	/**
	 * Trigger a public event.
	 *
	 * Public events will bubble outside the widget and will
	 * also be forwarded through the event bridge if one has
	 * been configured.
	 *
	 * @private
	 * @param {string} eventName The event to be triggered.
	 * @param {Array=} opt_data Additional event data.
	 */
	$.pkp.classes.Handler.prototype.triggerPublicEvent_ =
			function(eventName, opt_data) {

		// Publish the event.
		var $handledElement = this.getHtmlElement();
		$handledElement.parent().trigger(eventName, opt_data);

		// If we have an event bridge configured then re-trigger
		// the event on the target object.
		if (this.eventBridge_) {
			$('[id^="' + this.eventBridge_ + '"]').trigger(eventName, opt_data);
		}
	};


	/**
	 * Wrapper for the jQuery .replaceWith() function.
	 *
	 * This unbinds all global events before replacing the HTML content, to
	 * ensure there are no orphaned event listeners lingering from handlers
	 * which may have been destroyed when the HTML was replaced.
	 *
	 * This function can only be used when the entire handler is replaced. For
	 * replacing parts of a handler, see replacePartialWith().
	 *
	 * @param {string|jQueryObject} html The HTML content to replace the
	 *  current element with
	 */
	$.pkp.classes.Handler.prototype.replaceWith = function(html) {
		this.unbindGlobalAll();
		this.getHtmlElement().replaceWith(html);
	};


	/**
	 * Wrapper for the jQuery .replaceWith() function.
	 *
	 * This function works like the .replaceWith() wrapper above, except it
	 * allows you to pass a specific dom element to replace within the Handler.
	 *
	 * This function loops over any handlers found within the $partial dom
	 * element, unbinding global events to ensure there are no orphaned event
	 * listeners when the HTML element is replaced.
	 *
	 * The .replaceWith() function is preferred in most cases. This should only
	 * been used when you _need_ to replace part of a Handler's HTML content.
	 * Full handler refreshes are preferred to keep things simple. Also, this
	 * function isn't very performant, because it requires looping over every
	 * child DOM element.
	 *
	 * @param {string|jQueryObject} html The HTML content to inject into
	 *  the $partial
	 * @param {jQueryObject} $partial The HTML element to unbind
	 */
	$.pkp.classes.Handler.prototype.replacePartialWith =
			function(html, $partial) {

		// Check if the $partial already has a handler bound to it on which
		// we can call .unbindGlobalAll() instead
		if ($.pkp.classes.Handler.hasHandler($partial)) {
			$.pkp.classes.Handler.getHandler($partial).replaceWith(html);
			return;
		}

		this.unbindPartial($partial);
		$partial.replaceWith(html);
	};


	/**
	 * Wrapper for the jQuery .html() function.
	 *
	 * This unbinds all global events before replacing the inner HTML content.
	 * It differs from the .replaceWith() wrapper function in that the handler's
	 * element is not removed. This means the handler isn't re-initialized, and
	 * so only child handler events need to be unbound.
	 *
	 * @param {string} html The HTML content to inject into the $partial
	 */
	$.pkp.classes.Handler.prototype.html = function(html) {
		this.unbindGlobalChildren();
		this.getHtmlElement().html(html);
	};


	/**
	 * This function loops over any handlers found within the $partial dom
	 * element, unbinding global events to ensure there are no orphaned event
	 * listeners when the HTML element is replaced.
	 *
	 * This function isn't very performant. It requires looping over every
	 * element in scope, which could potentially be hundreds or thousands.
	 * This should only be used as a last resort for some handlers which need
	 * to empty out partial content, such as tabs and grids.
	 *
	 * @param {jQueryObject} $partial The HTML element to unbind
	 */
	$.pkp.classes.Handler.prototype.unbindPartial =
			function($partial) {

		$('*', $partial).each(function() {
			if ($.pkp.classes.Handler.hasHandler($(this))) {
				var handler = $.pkp.classes.Handler.getHandler($(this));
				handler.callbackWrapper(handler.unbindGlobalAll());
			}
		});
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
			throw new Error('Trying to call handler method in non-handler context!');
		}
	};


}(jQuery));

/**
 * @defgroup js_classes
 */


// Create PKP namespaces.
/** @type {Object} */
jQuery.pkp = jQuery.pkp || { };


/** @type {Object} */
jQuery.pkp.classes = jQuery.pkp.classes || { };


/**
 * @file js/classes/Helper.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Helper
 * @ingroup js_controllers
 *
 * @brief PKP helper methods
 */
(function($) {


	/**
	 * Helper singleton
	 * @constructor
	 */
	$.pkp.classes.Helper = function() {
		throw Error('Trying to instantiate the Helper singleton!');
	};


	//
	// Private class constants
	//
	/**
	 * Characters available for UUID generation.
	 * @const
	 * @private
	 * @type {Array}
	 */
	$.pkp.classes.Helper.CHARS_ = ['0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
		'abcdefghijklmnopqrstuvwxyz'].join('').split('');


	//
	// Public static helper methods
	//
	/**
	 * Generate a random UUID.
	 *
	 * Original code thanks to Robert Kieffer <robert@broofa.com>,
	 * http://www.broofa.com, adapted by PKP.
	 *
	 * Copyright (c) 2010 Robert Kieffer
	 * Copyright (c) 2010 John Willinsky
	 * Distributed under the GNU GPL v2 and MIT licenses. For full
	 * terms see the file docs/COPYING.
	 *
	 * See discussion of randomness versus uniqueness:
	 * http://www.broofa.com/2008/09/javascript-uuid-function/
	 *
	 * @return {string} an RFC4122v4 compliant UUID.
	 */
	$.pkp.classes.Helper.uuid = function() {
		var chars = $.pkp.classes.Helper.CHARS_, uuid = new Array(36), rnd = 0, r;
		for (var i = 0; i < 36; i++) {
			if (i == 8 || i == 13 || i == 18 || i == 23) {
				uuid[i] = '-';
			} else if (i == 14) {
				uuid[i] = '4';
			} else {
				if (rnd <= 0x02) {
					rnd = 0x2000000 + (Math.random() * 0x1000000) | 0;
				}
				r = rnd & 0xf;
				rnd = rnd >> 4;
				uuid[i] = chars[(i == 19) ? (r & 0x3) | 0x8 : r];
			}
		}
		return uuid.join('');
	};


	/**
	 * Let one object inherit from another.
	 *
	 * Example:
	 *  $.pkp.classes.Parent = function() {...};
	 *  $.pkp.classes.Child = function() {...};
	 *  $.pkp.classes.Helper.inherits($.pkp.classes.Child, $.pkp.classes.Parent);
	 *
	 * @param {Function} Child Constructor of the child object.
	 * @param {Function} Parent Constructor of the parent object.
	 */
	$.pkp.classes.Helper.inherits = function(Child, Parent) {
		// Use an empty temporary object to avoid
		// calling a potentially costly constructor
		// on the parent object which also may have
		// undesired side effects. Also avoids instantiating
		// a potentially big object.
		/** @constructor */ var Temp = function() {};
		Temp.prototype = Parent.prototype;

		// Provide a way to reach the parent's
		// method implementations even after
		// overriding them in the child object.
		Child.parent_ = Parent.prototype;

		// Let the child object inherit from
		// the parent object.
		Child.prototype = new Temp();

		// Need to fix the child constructor because
		// it get's lost when setting the prototype
		// to an object instance.
		Child.prototype.constructor = Child;

		// Make sure that we can always call the parent object's
		// constructor without coupling the child constructor
		// to it. This should work even when the parent inherits
		// directly from an Object instance (i.e. the parent's
		// prototype was set like this: Parent.prototype = {...})
		// which wipes out the original constructor.
		if (Parent.prototype.constructor == Object.prototype.constructor) {
			Parent.prototype.constructor = Parent;
		}
	};


	/**
	 * Introduce a central object factory that maintains some
	 * level of indirection so that we can enrich objects, e.g.
	 * with aspects, provide different runtime-implementations
	 * of objects, distinguish between singletons and prototypes
	 * or even implement dependency injection if we want to.
	 *
	 * The standard implementation has a 'convention over
	 * configuration' approach that assumes that an object's
	 * name corresponds to the name of a constructor within
	 * the global jQuery namespace ($).
	 *
	 * The factory also helps us to avoid the common pitfall to
	 * use a constructor without the 'new' keyword.
	 *
	 * @param {string} objectName The name of an object.
	 * @param {Array} args The arguments to be passed
	 *  into the object's constructor.
	 * @return {Object} the instantiated object.
	 */
	$.pkp.classes.Helper.objectFactory = function(objectName, args) {
		// Resolve the object name.
		var ObjectConstructor = $.pkp.classes.Helper.resolveObjectName(objectName);

		// Create a new proxy constructor instance.
		var ObjectProxyInstance = $.pkp.classes.Helper.getObjectProxyInstance();

		// Let the proxy inherit from the proxied object.
		$.pkp.classes.Helper.inherits(ObjectProxyInstance, ObjectConstructor);

		// Copy proxy object members into the new proxy constructor instance.
		$.extend(true, ObjectProxyInstance.prototype,
				$.pkp.classes.ObjectProxy.prototype);
		$.extend(ObjectProxyInstance, $.pkp.classes.ObjectProxy);

		// Instantiate the proxy with the proxied object.
		var objectInstance = new ObjectProxyInstance(objectName, args);
		return objectInstance;
	};


	/**
	 * Resolves the given object name to an object implementation
	 * (or better to it's constructor).
	 * @param {string} objectName The object name to resolve.
	 * @return {Function} The constructor of the object.
	 */
	$.pkp.classes.Helper.resolveObjectName = function(objectName) {
		// Currently only objects in the $ namespace are
		// supported.
		var objectNameParts = objectName.split('.');
		if (objectNameParts.shift() != '$') {
			throw Error(['Namespace "', objectNameParts[0], '" for object"',
				objectName, '" is currently not supported!'].join(''));
		}

		// Make sure that we actually have a constructor name
		// (starts with an upper case letter).
		var functionName = objectNameParts[objectNameParts.length - 1];
		if (functionName.charAt(0).toUpperCase() !== functionName.charAt(0)) {
			throw Error(['The name "', objectName, '" does not point to a constructor',
				'which must always be upper case!'].join(''));
		}

		// Run through the namespace and identify the constructor.
		var ObjectConstructor = $;
		for (var i in objectNameParts) {
			ObjectConstructor = ObjectConstructor[objectNameParts[i]];
			if (ObjectConstructor === undefined) {
				throw Error(['Constructor for object "', objectName, '" not found!']
						.join(''));
			}
		}

		// Check that the constructor actually is a function.
		if (!$.isFunction(ObjectConstructor)) {
			throw Error(['The name "', objectName, '" does not point to a constructor',
				'which must always be a function!'].joint());
		}

		return ObjectConstructor;
	};


	/**
	 * Create a new instance of a proxy constructor.
	 *
	 * NB: We do this in a separate closure to avoid
	 * memory leaks.
	 *
	 * @return {Function} a new proxy instance.
	 */
	$.pkp.classes.Helper.getObjectProxyInstance = function() {
		// Create a new proxy constructor so that proxies
		// do not interfere with each other.
		/**
		 * @constructor
		 * @param {string} objectName The name of the proxied
		 *  object.
		 * @param {Array} args The arguments to be passed to
		 *  the constructor of the proxied object.
		 */
		return function(objectName, args) {
			// Set the internal object name.
			this.objectName_ = objectName;

			// Call the constructor of the proxied object.
			this.parent.apply(this, args);
		};
	};


	/**
	 * Inject (mix in) an interface into an object.
	 * @param {Function} Constructor The target object's constructor.
	 * @param {string} mixinObjectName The object name of interface
	 *  that can be resolved to an interface implementation by the
	 *  object factory.
	 */
	$.pkp.classes.Helper.injectMixin = function(Constructor, mixinObjectName) {
		// Retrieve an instance of the mix-in interface implementation.
		var mixin = $.pkp.classes.Helper.objectFactory(mixinObjectName, []);

		// Inject the mix-in into the target constructor.
		$.extend(true, Constructor, mixin);
	};


	/**
	 * A function currying implementation borrowed from Google Closure.
	 * @param {Function} fn A function to partially apply.
	 * @param {Object} context Specifies the object which |this| should
	 *  point to when the function is run. If the value is null or undefined, it
	 *  will default to the global object.
	 * @param {...*} var_args Additional arguments that are partially
	 *  applied to the function.
	 * @return {!Function} A partially-applied form of the function bind() was
	 *  invoked as a method of.
	 */
	$.pkp.classes.Helper.curry = function(fn, context, var_args) {
		if (arguments.length > 2) {
			var boundArgs = Array.prototype.slice.call(arguments, 2);
			return function() {
				// Prepend the bound arguments to the current arguments.
				var newArgs = Array.prototype.slice.call(arguments);
				Array.prototype.unshift.apply(newArgs, boundArgs);
				return fn.apply(context, newArgs);
			};
		} else {
			return function() {
				return fn.apply(context, arguments);
			};
		}
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

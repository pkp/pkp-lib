/**
 * @file js/classes/ObjectProxy.js
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ObjectProxy
 * @ingroup js_classes
 *
 * @brief Proxy that will be added to every object before
 *  instantiation.
 *
 *  This proxy allows us to use a generic object factory. It'll
 *  also be a good place to intercept objects and implement
 *  cross-cutting concerns if required.
 */
(function($) {


	/**
	 * @constructor
	 * The constructor must remain empty because it will
	 * be replaced on instantiation of the proxy.
	 */
	$.pkp.classes.ObjectProxy = function() {};


	//
	// Private instance variables
	//
	/**
	 * @private
	 * @type {string} The object name of this object.
	 */
	$.pkp.classes.ObjectProxy.prototype.objectName_ = '';


	//
	// Protected methods
	//
	/**
	 * Find a static property in the constructor hierarchy.
	 *
	 * NB: If the property is a function then it will be executed
	 * in the current context with the additional arguments given.
	 * If it is any other type then the property will be returned.
	 *
	 * @param {string} propertyName The name of the static
	 *  property to be found.
	 * @param {...*} var_args Arguments to be passed to the
	 *  static method (if any).
	 * @return {*} The property or undefined if the property
	 *  was not found.
	 */
	$.pkp.classes.ObjectProxy.prototype.self =
			function(propertyName, var_args) {

		// Loop through the inheritance hierarchy to find the property.
		for (var ctor = this.constructor; ctor;
				ctor = ctor.parent_ && ctor.parent_.constructor) {

			// Try to find the property in the current constructor.
			if (ctor.hasOwnProperty(propertyName)) {
				var foundProperty = ctor[propertyName];
				if ($.isFunction(foundProperty)) {
					// If the property is a function then call it.
					var args = Array.prototype.slice.call(arguments, 1);
					return foundProperty.apply(this, args);
				} else {
					// Return the property itself.
					return foundProperty;
				}
			}
		}

		// The property was not found on any of the functions
		// in the constructor hierarchy.
		throw Error(['Static property "', propertyName, '" not found!'].join(''));
	};


	/**
	 * Find the parent constructor or method in the prototype
	 * hierarchy.
	 *
	 * NB: If the method is found then it will be executed in the
	 * context of the me parameter with the given arguments.
	 *
	 * @param {string=} methodName The name of the method to
	 *  be found. Do not set when calling this method from a
	 *  constructor!
	 * @param {...*} var_args Arguments to be passed to the
	 *  parent method.
	 * @return {*} The return value of the parent method.
	 */
	$.pkp.classes.ObjectProxy.prototype.parent =
			function(methodName, var_args) {

		// Retrieve a reference to the function that called us.
		var caller = $.pkp.classes.ObjectProxy.prototype.parent.caller,
				args;

		// 1) Check whether the caller is a constructor.
		if (caller.parent_) {
			// We were called from within a constructor and
			// therefore the methodName parameter is not set.
			args = Array.prototype.slice.call(arguments);

			// Call the constructor.
			return caller.parent_.constructor.apply(this, args);
		}

		// Assume that we were called from within a method and that
		// therefore the methodName parameter is set.
		args = Array.prototype.slice.call(arguments, 1);

		// 2) Look for the caller in the top-level instance methods.
		if (this.hasOwnProperty(methodName) && this[methodName] === caller) {
			return this.constructor.parent_[methodName].apply(this, args);
		}

		// 3) Look for the caller in the prototype chain.
		var foundCaller = false;
		for (var ctor = this.constructor; ctor;
				ctor = ctor.parent_ && ctor.parent_.constructor) {
			if (ctor.prototype.hasOwnProperty(methodName) &&
					ctor.prototype[methodName] === caller) {
				foundCaller = true;
			} else if (foundCaller) {
				return ctor.prototype[methodName].apply(this, args);
			}
		}

		// 4) This method was not called by the right caller.
		throw Error(['Trying to call parent from a method of one name ',
			'to a method of a different name'].join(''));
	};


	//
	// Public methods
	//
	/**
	 * Return the object name of this object
	 * @return {string} The object name of this object.
	 */
	$.pkp.classes.ObjectProxy.prototype.getObjectName = function() {
		return this.objectName_;
	};


/** @param {jQuery} $ jQuery closure. */
})(jQuery);

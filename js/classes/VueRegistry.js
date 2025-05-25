/**
 * @file js/classes/VueRegistry.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VueRegistry
 * @ingroup js_classes
 *
 * @brief Registry and initialization class for Vue.js handlers
 */

import { getComponentStoreByName } from "@/utils/defineComponentStore";

export default {
	_piniaInstance: null,
	/**
	 * Registry of all active vue instances
	 */
	_instances: {},

	/**
	 * Registry of all global components
	 */
	_globalComponents: {},

	/**
	 * Registry of all global directives
	 */
	_globalDirectives: {},

	/**
	 * Initialize a Vue controller
	 *
	 * This method is often called directly from a <script> tag in a template
	 * file to spin up a Vue controller on-demand. This allows the Vue component
	 * lifecycle to be compatible with the legacy JS framework.
	 *
	 * @param string id Element ID to attach this controller to
	 * @param string type The type of controller to initialize
	 * @param object The data object to pass to the controller. Can include
	 *  configuration parameters, translatable strings and initial data.
	 */
	init: function (id, type, data) {
		if (pkp.controllers[type] === undefined) {
			return;
		}

		var baseData = {};
		if (typeof pkp.controllers[type].data === 'function') {
			baseData = pkp.controllers[type].data();
		}

		var args = $.extend(true, {}, pkp.controllers[type], {
			data: function () {
				return $.extend(true, {}, baseData, data, {id: id});
			},
		});

		pkp.registry._instances[id] = pkp.pkpCreateVueApp(args);

		const rootComponent = pkp.registry._instances[id].mount(`#${id}`);

		pkp.eventBus.$emit('root:mounted', id, rootComponent);

		// Register with a parent handler from the legacy JS framework, so that
		// those componments can destroy a Vue instance when removing HTML code
		var $parents = $(pkp.registry._instances[id].$el).parents();
		$parents.each(function (i) {
			if ($.pkp.classes.Handler.hasHandler($($parents[i]))) {
				$.pkp.classes.Handler.getHandler($($parents[i])).handlerChildren_.push(
					pkp.registry._instances[id],
				);
				return false; // only attach to the closest parent handler
			}
		});
	},

	attachPiniaInstance(piniaInstance) {
		this._piniaInstance = piniaInstance;
	},

	/**
	 * Keeps track of all globally registered vue components
	 *
	 * This is important especially for plugins with custom vue components
	 * All global components gets automatically registered for each vue instance thats created
	 * It has same signature as vueInstance.component()
	 * @param string componentName
	 * @param object component
	 */
	registerComponent(componentName, component) {
		this._globalComponents[componentName] = component;
	},

	/**
	 * Allow possibility to allow retrive component object
	 *
	 * Should be needed very rarely. This is important for some plugins which currently extends existing component, like FieldPubIdUrn
	 * @param string Able to retrieve component object
	 */
	getComponent(componentName) {
		return this._globalComponents[componentName];
	},

	/**
	 * Provides all globaly registered components
	 *
	 * Main reason is to be able get all components to be registered in lib/pkp/load.js
	 * for every vue instance which is created
	 * @returns object Object of all components, where key is component name and value component object
	 */
	getAllComponents() {
		return this._globalComponents;
	},

	/**
	 * Register a global Vue directive
	 * @param {string} directiveName - The directive name
	 * @param {object} directive - The directive object
	 */
	registerDirective(directiveName, directive) {
		this._globalDirectives[directiveName] = directive;
	},

	/**
	 * Retrieve all registered directives
	 */
	getAllDirectives() {
		return this._globalDirectives;
	},

	/** Get pinia store by name */
	getPiniaStore(storeName) {
		return getComponentStoreByName(storeName);
	},

	storeExtend(storeName, extenderFn) {
		this._piniaInstance.use((context) => {

			if (context.store.$id === storeName) {
				extenderFn(context);
			}

		})

	},

	storeExtendFn(storeName, fnName, extenderFn) {
		this._piniaInstance.use((context) => {

			if (context.store.$id === storeName) {
				context.store.extender.extendFn(fnName, extenderFn)
			}

		})
		
	},

	getPiniaInstance() {
		this._piniaInstance;
	},

	storeAddFn(storeName, fnName, fn) {
		this._piniaInstance.use((context) => {

			if (context.store.$id === storeName) {
				context.store[fnName] = fn;
			}
		})
	},

	storeListExtendableFns(storeName) {
		if(!storeName) {
			throw new Error('missing storeName')
		}
		const store = getComponentStoreByName(storeName);

		if(store.extender) {
			return store.extender.listExtendableFns()
		}
		return false;

	}


};

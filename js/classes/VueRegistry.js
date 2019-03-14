/**
 * @file js/classes/VueRegistry.js
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class VueRegistry
 * @ingroup js_classes
 *
 * @brief Registry and initialization class for Vue.js handlers
 */
export default {
	/**
	 * Registry of all active vue instances
	 */
	_instances: {},

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
	init: function(id, type, data) {
		if (pkp.controllers[type] === undefined) {
			return;
		}

		var args = $.extend(true, {}, pkp.controllers[type], {
			el: '#' + id,
			data: $.extend(true, {}, pkp.controllers[type].data(), data, {id: id})
		});

		pkp.registry._instances[id] = new pkp.Vue(args);

		// Register with a parent handler from the legacy JS framework, so that
		// those componments can destroy a Vue instance when removing HTML code
		var $parents = $(pkp.registry._instances[id].$el).parents();
		$parents.each(function(i) {
			if ($.pkp.classes.Handler.hasHandler($($parents[i]))) {
				$.pkp.classes.Handler.getHandler($($parents[i])).handlerChildren_.push(
					pkp.registry._instances[id]
				);
				return false; // only attach to the closest parent handler
			}
		});
	}
};

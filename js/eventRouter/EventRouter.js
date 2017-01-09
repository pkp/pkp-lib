/**
 * @defgroup js_classes
 */

/**
 * @file js/eventRouter/EventRouter.js
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @brief A global event router to pass events between handlers. This is an
 *  adaptation of the Observer pattern. Instead of allowing handlers to
 *  subscribe to events, all event responses are defined in the eventRouter.
 *  This is a workaround for the lapsed listener problem, in which handlers will
 *  leave obsolete listeners around in the eventRouter as they're refreshed and
 *  re-initialized. This is because we don't have any central handler registry,
 *  and can't track handler removals which occur due to changes in the DOM.
 */
(function($) {

	/**
	 * Event Router object
	 *
	 * @type {object} The Event Router object.
	 */
	$.pkp.eventRouter = {};

	/**
	 * Registery of target DOM elements which should receive events
	 *
	 * @type {object} Key list of event names with an array of jQuery selectors
	 */
	$.pkp.eventRouter.eventRegistry = {
		'issuePublished': [
			'[id^="component-grid-issues-backissuegrid-"].pkp_controllers_grid',
		],
		'issueUnpublished': [
			'[id^="component-grid-issues-futureissuegrid-"].pkp_controllers_grid',
		],
	};

	/**
	 * Respond to events triggered on the event router
	 *
	 * @type {string} Event name
	 * @type {eventData} Event data
	 * @type {object} Handler which fired the event
	 */
	$.pkp.eventRouter.trigger = function(eventName, eventData, handler) {
		if (_.has(this.eventRegistry, eventName)) {
			$(this.eventRegistry[eventName].join(','))
				.trigger(eventName, [eventData, handler]);
		}
	};

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

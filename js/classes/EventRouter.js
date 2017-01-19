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
 *  instance of the Backbone Events object. It allows handlers to bind to events
 *  triggered on the EventRouter.
 * @see http://backbonejs.org/#Events
 */
(function($) {

	/**
	 * Event Router object
	 *
	 * @type {object} The Event Router object.
	 */
	$.pkp.classes.EventRouter = _.extend({}, Backbone.Events);

/** @param {jQuery} $ jQuery closure. */
}(jQuery));

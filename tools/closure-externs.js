/**
 * closure-externs.js
 *
 * Copyright (c) 2010-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Import symbols into the closure compiler that are not defined
 * within the compiled files.
 *
 * @externs
 */

/**
 * @constructor
 * @param {(string|Function|HTMLElement|HTMLDocument|Location)} selector A CSS selector
 * @param {jQuery=} context The context for the selector.
 */
function jQuery(selector, context) {};

/**
 * @param {(string|Function|HTMLElement|HTMLDocument|Location)} selector A CSS selector.
 * @param {jQuery=} context The context for the selector.
 */
function $(selector, context) {};

/**
 * @constructor
 */
function JSON() {};

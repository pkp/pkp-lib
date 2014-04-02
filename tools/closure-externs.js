/**
 * closure-externs.js
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2010-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Import symbols into the closure compiler that are not defined
 * within the compiled files.
 *
 * See https://code.google.com/p/closure-compiler/source/browse/trunk/contrib/externs
 * for pre-extracted extern files, e.g. for jQuery.
 *
 * @externs
 */

/**
 * @param {Object} arg1
 */
jQueryObject.prototype.autocomplete = function(arg1) {};

jQueryObject.prototype.button = function() {};


/**
 * @param {Object=} options
 */
jQueryObject.prototype.validate = function(options) {};

/**
 * @constructor
 * @param {Object=} options
 * @param {jQueryObject=} form
 */
jQuery.validator = function(options, form) {};

jQuery.validator.prototype.checkForm = function() {};

jQuery.validator.prototype.defaultShowErrors = function() {};

jQuery.validator.prototype.settings = {};

/**
 * @constructor
 * @param {Object=} options
 */
jQuery.pnotify = function(options) {};

/**
 * @param {Object=} userDefinedSettings
 * @return {jQueryObject}
 */
jQueryObject.prototype.imgPreview = function(userDefinedSettings) {};

/**
 * @constructor
 * @private
 */
function tinyMCEObject() {};

tinyMCEObject.prototype.triggerSave = function() {}

/**
 * @param {string} c
 * @param {boolean} u
 * @param {string} v
 */
tinyMCEObject.prototype.execCommand = function(c, u, v) {}

/**
 * @type {tinyMCEObject}
 */
var tinyMCE;


$.pkp.locale = {
	search_noKeywordError: '',
	form_dataHasChanged: ''
};

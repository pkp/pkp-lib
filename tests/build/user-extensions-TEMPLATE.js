/**
 * @file lib/pkp/tests/build/user-extensions-TEMPLATE.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Used to permit configuration of the Selenium remote control with parameters
 * from the local environment. Also adds custom actions to selenium object to
 * be used by tests.
 */

//
// Configuration.
//
var dbtype='MySQL';
var dbhost='localhost';
var dbname='ojs-ci';
var dbusername='ojs-ci';
var dbpassword='ojs-ci';
var filesdir='files'; // Complete path.
var dummyfilepath='dummy.pdf'; // Complete path to existing file.

// Some slower environments will need higher times to complete tests. If you have problems with actions
// not finding widgets elements or tests timing out, tweak these values.
var pauseTime=2; // Time in seconds that selenium will wait for some widgets to get ready, like tinyMCE.
var timeout=60; // Time in seconds that selenium will wait in actions like clickAndWait() until it times out.


//
// DO NOT CHANGE FOR CONFIGURATION PURPOSES. All
// configuration should be done above.
//
//
// Selenium object config and custom actions.
//
/**
 * Override the default selenium timeout with our setting.
 * @type {integer}
 */
Selenium.DEFAULT_TIMEOUT = timeout * 1000;
/**
 * Transform seconds into miliseconds.
 * @type {integer}
 */
pauseTime = pauseTime * 1000;


/**
 * Type text into tinyMCE editor.
 *
 * @param {string} id The text area id that is
 * replaced by the tinyMCE editor.
 * @param {string} text The text you want to type.
 */
Selenium.prototype.doTypeTinyMCE = function(id, text) {
	var script = "tinyMCE.get(document.evaluate('//textarea[contains(@id, \\'" + id + "\\')]', document, null, 9, null).singleNodeValue.id).setContent('" + text + "');";
	var sl = this;

	// Wait for tinyMCE to load.
	setTimeout(function(){sl.doRunScript(script);}, pauseTime);
	this.doPause(pauseTime);
};
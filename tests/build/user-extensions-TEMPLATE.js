/**
 * @file tests/build/user-extensions.js
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Used to permit configuration of the Selenium remote control with parameters
 * from the local environment.
 */

var dbtype='MySQL';
var dbhost='localhost';
var dbname='ojs-ci';
var dbusername='ojs-ci';
var dbpassword='ojs-ci';
var filesdir='files'; // Complete path.
var dummyfilepath='dummy.pdf'; // Complete path to existing file.
var timeout=60000; // Time in ms that selenium will wait in actions like clickAndWait() until it times out. Some slower environments will need higher times to complete tests.

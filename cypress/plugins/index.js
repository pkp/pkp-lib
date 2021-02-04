/**
 * @file cypress/plugins/index.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 */

// See https://stackoverflow.com/questions/58657895/is-there-a-reliable-way-to-have-cypress-exit-as-soon-as-a-test-fails/58660504#58660504
// See https://github.com/bahmutov/cypress-failed-log
// See https://github.com/cypress-io/cypress/issues/3199#issuecomment-534717443
// See https://github.com/cypress-io/cypress/issues/909#issuecomment-578505704
let shouldSkip = false;
module.exports = ( on, config ) => {
	on('task', {
		resetShouldSkipFlag () {
			shouldSkip = false;
			return null;
		},
		shouldSkip ( value ) {
			if ( value != null ) shouldSkip = value;
			return shouldSkip;
		},
		failed: require('cypress-failed-log/src/failed')(),
		consoleLog(message) {
			console.log(message);
			return null;
		}
	});

	// Allow the baseUrl to be overwritten
	// in a local cypress.env.json file.
	const baseUrl = config.env.baseUrl || null;
	if (baseUrl) {
		config.baseUrl = baseUrl;
	}

	return config;
}
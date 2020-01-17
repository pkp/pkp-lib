/**
 * @file cypress/plugins/index.js
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 */

// See https://stackoverflow.com/questions/58657895/is-there-a-reliable-way-to-have-cypress-exit-as-soon-as-a-test-fails/58660504#58660504
// See https://github.com/bahmutov/cypress-failed-log
// See https://github.com/cypress-io/cypress/issues/3199#issuecomment-534717443
let shouldSkip = false;
module.exports = ( on ) => {
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
}

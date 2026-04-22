// @ts-check

/**
 * SEAM for the forthcoming OJS test-scenario API (built in parallel to
 * this boilerplate PR). These methods throw "not implemented" today —
 * when the backend endpoints land, this file is the single place to wire
 * them up. Every test that declares the `scenarios` fixture
 * (see support/base-test.js) will get the real client automatically.
 *
 * Rough shape of the eventual API: one-call factories that return a
 * ready-to-use fixture in a known state ("submission in review", "issue
 * published last month"), so feature tests don't have to re-assemble
 * state through the UI or many API calls.
 */
exports.createScenarioClient = function createScenarioClient({
	request,
	baseURL,
}) {
	return {
		request,
		baseURL,

		async createSubmissionInReview(/* overrides */) {
			throw new Error('TODO: hook up scenario API endpoint');
		},

		async createPublishedIssue(/* overrides */) {
			throw new Error('TODO: hook up scenario API endpoint');
		},

		async destroySubmission(/* id */) {
			throw new Error('TODO: hook up scenario API endpoint');
		},
	};
};

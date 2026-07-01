// @ts-check

/**
 * Shared HTTP client. Wraps Playwright's APIRequestContext and exposes
 * canonical cross-app endpoints (login, CSRF, test-bootstrap). OJS-only
 * endpoints live in playwright/support/ojs-api.js at the OJS root.
 */

const fs = require('fs');
const path = require('path');
const {getPassword} = require('../data/users.js');

/**
 * Per-call timing for the scenario controllers. Gated behind
 * `PKP_SCENARIO_TIMING=1` so it is a no-op during normal test runs.
 *
 * When enabled, each `createSubmission` / `createJournal` call appends a
 * JSON line to `PKP_SCENARIO_TIMING_LOG` (default: repo root
 * `.scenario-timing.log`). Lines look like:
 *   {"ts":1730000000000,"endpoint":"submission","ms":342,"status":200,"tag":"foo","keys":["participants","decisions"]}
 *
 * Aggregate post-run with `jq` or a quick Node script. Workers append
 * concurrently; POSIX write()s under PIPE_BUF are atomic so JSON lines
 * don't interleave.
 */
const SCENARIO_TIMING_ENABLED = process.env.PKP_SCENARIO_TIMING === '1';
const SCENARIO_TIMING_LOG =
	process.env.PKP_SCENARIO_TIMING_LOG ||
	path.resolve(__dirname, '..', '..', '..', '..', '.scenario-timing.log');

function writeScenarioTiming(entry) {
	if (!SCENARIO_TIMING_ENABLED) return;
	try {
		fs.appendFileSync(SCENARIO_TIMING_LOG, JSON.stringify(entry) + '\n');
	} catch {
		// Timing logs are best-effort; never fail a test on log write.
	}
}

/**
 * @typedef {Object} ApiClientOpts
 * @property {import('@playwright/test').APIRequestContext} request
 * @property {string=} baseURL
 */

/**
 * @param {ApiClientOpts} opts
 */
exports.createApiClient = function createApiClient({request, baseURL}) {
	const testApiKey = process.env.TEST_API_KEY;

	return {
		request,
		baseURL,

		/**
		 * Fetch a CSRF token from /api/v1/_csrf. Required for authenticated
		 * POSTs that don't carry a Bearer apiToken.
		 */
		async getCsrfToken() {
			const res = await request.get('/index.php/index/api/v1/_csrf');
			if (!res.ok()) {
				throw new Error(`CSRF token request failed: ${res.status()} ${await res.text()}`);
			}
			const body = await res.json();
			return body.csrfToken ?? body.token ?? body;
		},

		/**
		 * Log a user in via the /login form. Stores the session cookie in
		 * the request context. Follow-up calls from the same context
		 * re-use the session.
		 *
		 * @param {string} username
		 * @param {string=} password defaults to the Cypress-compatible derivation
		 */
		async login(username, password) {
			const pw = password ?? getPassword(username);
			const res = await request.post('/index.php/index/login/signIn', {
				form: {
					username,
					password: pw,
					source: '',
					remember: '',
				},
				maxRedirects: 0,
			});
			// OJS responds with a 302 to the dashboard on success.
			if (res.status() !== 302 && !res.ok()) {
				throw new Error(`Login failed for ${username}: ${res.status()} ${await res.text()}`);
			}
		},

		/**
		 * Detect whether OJS is installed. Pre-install, OJS routes redirect
		 * to /install; post-install, the API responds normally. Used by
		 * bootstrap.setup.js's install stage to skip re-installation.
		 */
		async isInstalled() {
			try {
				const res = await request.get('/index.php', {maxRedirects: 0});
				// A 302 to /install means we're pre-install.
				if (res.status() === 302) {
					const loc = res.headers()['location'] || '';
					if (loc.includes('/install')) return false;
				}
				return res.status() < 500;
			} catch {
				return false;
			}
		},

		/**
		 * Detect whether the baseline has been seeded by probing for the
		 * publicknowledge journal homepage. Cheap and doesn't require the
		 * test API key. Used to skip re-seeding on warm runs.
		 *
		 * @param {string=} journalPath  defaults to 'publicknowledge'
		 */
		async isBootstrapped(journalPath = 'publicknowledge') {
			try {
				const res = await request.get(`/index.php/${journalPath}`, {
					maxRedirects: 0,
				});
				// A live journal returns 200 or redirects (302/301) to its
				// locale-prefixed home. A missing one 404s. Accept any 2xx
				// or redirect as "journal exists".
				const status = res.status();
				return status === 200 || status === 301 || status === 302;
			} catch {
				return false;
			}
		},

		/**
		 * Call the test-only submission scenario endpoint with a full spec.
		 * Creates one submission with any combination of participants,
		 * decisions, review rounds, and publications. Gated server-side
		 * by TestModeGate (APPLICATION_ENV=test + X-Test-Key).
		 *
		 * @param {object} spec  see lib/pkp/classes/testing/scenario/schema/submission.json
		 * @returns {Promise<object>} { submission, publications, participants, decisions, reviewRounds, tag }
		 */
		async createSubmission(spec) {
			if (!testApiKey) {
				throw new Error(
					'TEST_API_KEY env var is not set. Set it (same value on client and server) to call /api/v1/_test/scenarios/submission.',
				);
			}
			const t0 = Date.now();
			const res = await request.post(
				'/index.php/index/api/v1/_test/scenarios/submission',
				{
					headers: {
						'X-Test-Key': testApiKey,
						'Content-Type': 'application/json',
					},
					data: spec,
				},
			);
			const ms = Date.now() - t0;
			writeScenarioTiming({
				ts: t0,
				endpoint: 'submission',
				ms,
				status: res.status(),
				tag: spec?.tag,
				keys: Object.keys(spec || {}),
			});
			const bodyText = await res.text();
			if (!res.ok()) {
				throw new Error(
					`createSubmission failed: ${res.status()} — ${bodyText}`,
				);
			}
			try {
				return JSON.parse(bodyText);
			} catch {
				throw new Error(`createSubmission returned non-JSON body: ${bodyText}`);
			}
		},

		/**
		 * Call the test-only journal scenario endpoint. Creates a scratch
		 * journal (unique URL path, default sections + email templates +
		 * user groups auto-installed) and optionally assigns bootstrapped
		 * users to roles inside it. Use this from any test that needs to
		 * mutate journal-level configuration — the bootstrapped
		 * publicknowledge journal must stay read-only across the suite.
		 *
		 * @param {object} spec see lib/pkp/classes/testing/scenario/schema/context.json
		 * @returns {Promise<{context: {id: number, path: string, name: object|null, primaryLocale: string|null, primaryManager: {username: string}|null}, tag: string}>}
		 */
		async createJournal(spec) {
			if (!testApiKey) {
				throw new Error(
					'TEST_API_KEY env var is not set. Set it (same value on client and server) to call /api/v1/_test/scenarios/journal.',
				);
			}
			const t0 = Date.now();
			const res = await request.post(
				'/index.php/index/api/v1/_test/scenarios/journal',
				{
					headers: {
						'X-Test-Key': testApiKey,
						'Content-Type': 'application/json',
					},
					data: spec,
				},
			);
			const ms = Date.now() - t0;
			writeScenarioTiming({
				ts: t0,
				endpoint: 'journal',
				ms,
				status: res.status(),
				tag: spec?.tag,
				keys: Object.keys(spec || {}),
			});
			const bodyText = await res.text();
			if (!res.ok()) {
				throw new Error(
					`createJournal failed: ${res.status()} — ${bodyText}`,
				);
			}
			try {
				return JSON.parse(bodyText);
			} catch {
				throw new Error(`createJournal returned non-JSON body: ${bodyText}`);
			}
		},

		/**
		 * Bootstrap is now a thin alias over `createJournal` — the same
		 * `/scenarios/journal` endpoint accepts the baseline spec
		 * (sections + categories + issues + users-with-passwords)
		 * alongside the per-test scratch shape. Kept as a named method so
		 * `bootstrap.setup.js` reads idiomatically.
		 *
		 * @param {object} spec  see lib/pkp/classes/testing/scenario/schema/context.json
		 * @returns {Promise<object>}
		 */
		async bootstrap(spec) {
			return this.createJournal(spec);
		},
	};
};

// @ts-check

/**
 * Shared HTTP client. Wraps Playwright's APIRequestContext and exposes
 * canonical cross-app endpoints (login, CSRF, test-bootstrap). OJS-only
 * endpoints live in playwright/support/ojs-api.js at the OJS root.
 */

const {getPassword} = require('../data/users.js');

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
		 * Call the test-only bootstrap endpoint with a full spec.
		 * Gated server-side by TestModeGate (APPLICATION_ENV=test + X-Test-Key).
		 *
		 * @param {object} spec  bootstrap spec body (journals, users, ...)
		 * @returns {Promise<object>} ID map keyed by natural spec keys
		 */
		async bootstrap(spec) {
			if (!testApiKey) {
				throw new Error(
					'TEST_API_KEY env var is not set. Set it (same value on client and server) to call /api/v1/_test/bootstrap.',
				);
			}

			const res = await request.post(
				'/index.php/index/api/v1/_test/bootstrap',
				{
					headers: {
						'X-Test-Key': testApiKey,
						'Content-Type': 'application/json',
					},
					data: spec,
				},
			);

			const bodyText = await res.text();
			if (!res.ok()) {
				throw new Error(
					`Bootstrap failed: ${res.status()} — ${bodyText}`,
				);
			}
			try {
				return JSON.parse(bodyText);
			} catch {
				throw new Error(`Bootstrap returned non-JSON body: ${bodyText}`);
			}
		},
	};
};

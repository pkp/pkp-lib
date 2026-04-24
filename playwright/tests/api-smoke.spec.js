// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');

/**
 * API smoke — row #47 in docs/e2e-playwright-migration.md.
 *
 * Cypress sources:
 *   - lib/pkp/cypress/tests/integration/API.cy.js (shared): sets
 *     api_key_secret, tries anonymous /users (expects 401), creates &
 *     deletes a manager's API key via the profile → API Settings tab,
 *     then re-hits /users with the apiToken query param.
 *   - cypress/tests/integration/API.cy.js (OJS): same pattern for an
 *     author (ccorino), asserting /submissions returns exactly one
 *     item (the author's own).
 *
 * Both Cypress specs spend the bulk of their body driving the jQuery
 * profile form to create/delete an apiToken. The capability under
 * test per the roadmap is:
 *   - anonymous authenticated-only endpoints return 401
 *   - an authenticated user can hit the API and get JSON back
 *   - a CSRF token can be pulled from the page for authenticated
 *     writes
 *
 * We drive this through session auth (dbarnes's baseline storageState)
 * rather than the apiToken round-trip, because:
 *   1. The apiToken flow requires an `api_key_secret` in
 *      config.test.inc.php, which the Cypress suite mutates at runtime
 *      (writeFile replacing the empty secret). Mutating the test
 *      config from a spec is fragile under parallel workers; the
 *      session-cookie path is the same has.user middleware gate and
 *      is how every other Playwright spec talks to the API.
 *   2. Session auth covers the same HasUser / HasRole middleware
 *      stack the apiToken decoder exercises (see
 *      DecodeApiTokenWithValidation.php:48 — HasUser runs for both
 *      auth modes).
 *   3. The "create/delete API key" UI is the legacy profile
 *      ApiProfileForm, a distinct concern from "API is reachable".
 *      Porting that form is a future row if we ever need it.
 *
 * Three tests:
 *   1. CSRF token — authenticated page exposes
 *      `window.pkp.currentUser.csrfToken`. This is the canonical source
 *      for the X-Csrf-Token header every existing Playwright spec
 *      (public-comments, data-availability, doi-crossref) uses. Also
 *      verifies the sibling helper at `/index.php/index/api/v1/_csrf`
 *      is NOT a live endpoint — if it starts returning 200 we want to
 *      know, because `pkpApi.getCsrfToken()` in
 *      lib/pkp/playwright/support/api.js targets it speculatively.
 *   2. Anonymous /submissions — context-scoped submissions endpoint
 *      requires `has.user` middleware, so an anonymous request returns
 *      401 (or 403 if misrouted). Replaces the Cypress `cy.request`
 *      with `failOnStatusCode: false` pattern using Playwright's
 *      APIRequestContext.
 *   3. Authenticated /submissions — dbarnes (publicknowledge editor)
 *      sees a JSON list. dbarnes has no seeded submissions by default
 *      but the response still must be well-formed (items[] +
 *      pagination-like shape). We don't assert a specific item count
 *      because each spec seeds its own submissions and parallel
 *      workers may have left traces.
 *
 * Helper note: lib/pkp/playwright/support/api.js ships
 * `pkpApi.getCsrfToken()` pointing at `/index.php/index/api/v1/_csrf`
 * — there is no such route. Test 1 explicitly documents this and the
 * production pattern (read `window.pkp.currentUser.csrfToken`) that
 * every spec already uses. When the helper is revisited, it should
 * either be removed or rewired to navigate + evaluate the global.
 */
test.describe('API smoke', () => {
	test(
		'authenticated page exposes a CSRF token via window.pkp.currentUser',
		{tag: '@regression'},
		async ({browser, baseURL}) => {
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {
					baseURL,
				}),
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				// The profile page renders for every authenticated user
				// regardless of context-scoped role. It carries the
				// standard PKP template shell, which injects
				// window.pkp.currentUser with csrfToken + id + roles
				// (see PKPTemplateManager::getJavaScriptData).
				await page.goto('/index.php/index/user/profile');
				await expect(page).not.toHaveURL(/\/login/);

				const currentUser = await page.evaluate(
					() => window.pkp?.currentUser,
				);
				expect(currentUser, 'pkp.currentUser injected').toBeTruthy();
				expect(currentUser.username).toBe('dbarnes');
				// CSRF token format: a 32-char hex string (Laravel's
				// random_bytes/Str::random on the session). Allow any
				// non-empty string — the shape guarantee is "present &
				// usable as X-Csrf-Token", not a specific length.
				expect(
					currentUser.csrfToken,
					'csrfToken is a non-empty string',
				).toMatch(/^[A-Za-z0-9]{16,}$/);

				// Negative: the speculative REST endpoint
				// lib/pkp/playwright/support/api.js targets
				// (`pkpApi.getCsrfToken` → /index/api/v1/_csrf) does
				// NOT exist. If the route is ever added (or removed),
				// this probe catches the drift. Today it 404s.
				const probe = await page.request.get(
					'/index.php/index/api/v1/_csrf',
				);
				expect(
					[404, 401, 403],
					`Unexpected /api/v1/_csrf status ${probe.status()} ` +
						'— if this endpoint starts returning 200, revisit ' +
						'lib/pkp/playwright/support/api.js `getCsrfToken`.',
				).toContain(probe.status());
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'anonymous request to /submissions is rejected',
		{tag: '@regression'},
		async ({request}) => {
			// APIRequestContext without storageState — no session cookie.
			// The /submissions endpoint uses has.user middleware which
			// responds 401 for anonymous callers. Accept 401 or 403
			// depending on the middleware chain; the shape the test
			// cares about is "not 200".
			const resp = await request.get(
				'/index.php/publicknowledge/api/v1/submissions',
			);
			expect(resp.status(), 'anonymous submissions must be rejected').toBe(
				401,
			);
			// Response body is JSON with an error message (not an HTML
			// login page).
			const body = await resp.json();
			expect(body).toHaveProperty('error');
		},
	);

	test(
		'authenticated user lists submissions',
		{tag: '@regression'},
		async ({browser, baseURL}) => {
			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {
					baseURL,
				}),
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				// Warm the session by navigating to a context page first
				// — ensures the session cookies are present on the
				// following in-page fetch.
				await page.goto('/index.php/publicknowledge/dashboard/editorial');
				await expect(page).not.toHaveURL(/\/login/);

				// In-page fetch so cookies ride along. PKP's API context
				// route + session cookie auth path is the same
				// middleware flow every authenticated UI click
				// exercises; asserting the endpoint returns a
				// well-formed response body is the "API is reachable"
				// smoke.
				const result = await page.evaluate(async () => {
					const r = await fetch(
						'/index.php/publicknowledge/api/v1/submissions',
						{headers: {Accept: 'application/json'}},
					);
					const j = await r.json();
					return {status: r.status, body: j};
				});
				expect(result.status).toBe(200);
				// The listing endpoint returns {items, itemsMax, ...}.
				// Items may be empty for dbarnes depending on what other
				// tests have seeded — we only assert the shape.
				expect(result.body).toHaveProperty('items');
				expect(Array.isArray(result.body.items)).toBe(true);
				expect(result.body).toHaveProperty('itemsMax');
				expect(typeof result.body.itemsMax).toBe('number');
			} finally {
				await ctx.close();
			}
		},
	);
});

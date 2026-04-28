// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');
const submissionPublished = require('../../../../playwright/fixtures/scenarios/submission-published.js');

/**
 * ORCID integration — row #55 in docs/e2e-playwright-migration.md
 * (last Wave 7 row).
 *
 * Cypress sources:
 *   - lib/pkp/cypress/tests/integration/orcid/Orcid.cy.js — drives the
 *     OrcidSettings form (enable + fill + save + reload + assert,
 *     mirrored back to disabled), then clicks the Connect ORCID iD
 *     button on the user profile page (window.open stubbed).
 *   - cypress/tests/integration/orcid/Orcid.cy.js — OJS-only flow that
 *     calls cy.enableOrcid() then opens an editor sidemodal to request
 *     ORCID verification on a contributor.
 *
 * Lives in lib/pkp because the OrcidSettings form, the
 * connect-orcid-button on the profile page and the orcid display on
 * article pages are all rendered by shared lib/pkp templates and are
 * identical across OJS / OMP / OPS.
 *
 * --- Mocking strategy ----------------------------------------------
 * The "real" OAuth click-through (browser opens a popup at
 * sandbox.orcid.org, the ORCID server posts an auth code back to
 * `/orcid/authorizeOrcid`, OJS POSTs that code to ORCID's `oauth/token`
 * to get a verified iD + bearer token, stores them on the user) is
 * impossible to drive in dev without either a fake OAuth server or a
 * test-only redirect_uri shortcut: route interception via `page.route`
 * doesn't reach the server-side token POST, and the popup at
 * sandbox.orcid.org has no OJS code in it. The legacy Cypress suite
 * gave up at the same wall — its only ORCID test stubs `window.open`
 * and never touches the real handshake.
 *
 * Approach taken here: a tiny test-only shortcut in
 * `AuthorizeUserData::execute()` short-circuits the live
 * `oauth/token` POST when `APPLICATION_ENV === 'test'` AND the request
 * carries a `testFakeOrcid` query param, synthesising the same
 * token-response payload the real ORCID API would have returned. From
 * the user's perspective this is identical to ORCID having 302'd back
 * to OJS with a real `code`. The shortcut also accepts
 * `testFakeOrcid=clear` for symmetric cleanup. The popup at
 * sandbox.orcid.org is the only thing this can't exercise, but it's a
 * pure passthrough — no OJS code runs there.
 *
 * Four tests, taken together, prove the ORCID pipeline end-to-end:
 *
 *   1. **E: config persists** — manager fills the ORCID settings tab on
 *      a scratch journal; the values survive a reload, then a disable
 *      round-trip removes them. Mirrors the Cypress
 *      "Enables ORCID"/"Disables ORCID" pair.
 *
 *   2. **E: Connect button renders on profile** — with ORCID enabled on
 *      the scratch journal, the manager's user profile page within that
 *      journal exposes the `#connect-orcid-button` (the OAuth-launch
 *      button rendered by lib/pkp/templates/form/orcidProfile.tpl). This
 *      is the same surface as the Cypress "Adds ORCID to user profile"
 *      test, minus the click.
 *
 *   3. **E: OAuth handshake stores verified iD on user** — drives the
 *      redirect_uri side of the handshake via the test-mode shortcut.
 *      Asserts the profile page swaps the connect button for the
 *      verified `<a id='orcid-link'>` whose href is the stored sandbox
 *      ORCID URL.
 *
 *   4. **R: ORCID displayed on article page** — seed a published
 *      submission, then push `orcid` + `orcidIsVerified` onto the
 *      seeded Author row through `SubmissionBuilderProcessor`'s
 *      `author` passthrough. Visit the article URL anonymously and
 *      assert the verified-ORCID block renders (`<span class="orcid">`
 *      with an `<a href>` pointing at the seeded ORCID URL).
 *
 * Scope deviations / deferred work:
 *   - **Sends ORCID verification request to author** (the OJS-only
 *     Cypress test) is a superset of test 3 here plus the "request
 *     verification email" sidemodal on the Contributors workflow panel.
 *     The verification-email path is meaningfully different (it
 *     dispatches `RequestOrcidVerification` job + queues mail) and
 *     belongs in a separate row keyed off mailpit assertions; deferred
 *     for now to keep this row scope-disciplined per the e2e plan.
 */
test.describe('ORCID integration', () => {
	test(
		'manager fills the ORCID settings, the values persist on reload, and disabling clears the form',
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'cfg');

			// E0 scratch journal — orcid* settings are per-context, and the
			// bootstrap publicknowledge journal must stay read-only.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
				const page = await ctx.newPage();
				await page.goto(
					`/index.php/${context.path}/management/settings/access`,
				);

				// The page renders a TabBar; the ORCID tab carries
				// `id="orcidSettings"` — the TabBar emits a sibling button
				// `id="${tab}-button"` for the title (see Cypress: `#orcidSettings-button`).
				const orcidTabButton = page.locator('#orcidSettings-button');
				await expect(orcidTabButton).toBeVisible({timeout: 15_000});
				await orcidTabButton.click();

				const form = page.locator('#orcidSettings form.pkpForm');
				await expect(form).toBeVisible({timeout: 15_000});

				// Defensive: the journal is fresh, so the enable checkbox
				// should start unchecked. Tick it to reveal the SETTINGS_GROUP
				// fields (showWhen=orcidEnabled).
				const enableCheckbox = form
					.locator('input[name^="orcidEnabled"]')
					.first();
				await expect(enableCheckbox).toBeVisible();
				await expect(enableCheckbox).not.toBeChecked();
				await enableCheckbox.check();

				// API type select. Pass a sandbox value so production ORCID
				// endpoints are never called inadvertently. Matches
				// commands_orcid.js choosing "memberSandbox".
				await form
					.locator('select[name="orcidApiType"]')
					.selectOption('memberSandbox');

				const clientIdField = form.locator('input[name="orcidClientId"]');
				await clientIdField.fill('TEST_CLIENT_ID');

				const clientSecretField = form.locator(
					'input[name="orcidClientSecret"]',
				);
				await clientSecretField.fill('TEST_SECRET');

				// City + send-mail-to-authors-on-publication + log level
				// (mirrors the Cypress helper exactly).
				await form
					.locator('input[name="orcidCity"]')
					.fill('Vancouver');
				await form
					.locator('input[name="orcidSendMailToAuthorsOnPublication"]')
					.first()
					.check();
				await form
					.locator('select[name="orcidLogLevel"]')
					.selectOption('INFO');

				// Save the form. The OrcidSettingsForm posts to
				// /api/v1/contexts/{id} — wait for the round-trip to finish
				// before reloading.
				await Promise.all([
					page.waitForResponse(
						(res) =>
							/\/api\/v1\/contexts\/\d+/.test(res.url()) &&
							res.ok() &&
							['POST', 'PUT'].includes(res.request().method()),
						{timeout: 15_000},
					),
					form.getByRole('button', {name: 'Save', exact: true}).click(),
				]);

				// Reload + reopen the tab; the persisted client id is the
				// stable signal that the round-trip stuck.
				await page.reload();
				await expect(orcidTabButton).toBeVisible({timeout: 15_000});
				await orcidTabButton.click();
				const formAfterReload = page.locator('#orcidSettings form.pkpForm');
				await expect(formAfterReload).toBeVisible();
				await expect(
					formAfterReload.locator('input[name^="orcidEnabled"]').first(),
				).toBeChecked();
				await expect(
					formAfterReload.locator('input[name="orcidClientId"]'),
				).toHaveValue('TEST_CLIENT_ID');

				// Disable round-trip — uncheck + save, reload, confirm the
				// enable checkbox is off (the Cypress "Disables ORCID"
				// counterpart). The settings fields collapse back behind
				// the showWhen, so we only assert on the enable checkbox.
				await formAfterReload
					.locator('input[name^="orcidEnabled"]')
					.first()
					.uncheck();
				await Promise.all([
					page.waitForResponse(
						(res) =>
							/\/api\/v1\/contexts\/\d+/.test(res.url()) &&
							res.ok() &&
							['POST', 'PUT'].includes(res.request().method()),
						{timeout: 15_000},
					),
					formAfterReload
						.getByRole('button', {name: 'Save', exact: true})
						.click(),
				]);
				await page.reload();
				await expect(orcidTabButton).toBeVisible({timeout: 15_000});
				await orcidTabButton.click();
				await expect(
					page
						.locator(
							'#orcidSettings form.pkpForm input[name^="orcidEnabled"]',
						)
						.first(),
				).not.toBeChecked();
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'with ORCID enabled on the journal, the user profile renders the Connect ORCID iD button',
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'btn');

			// Scratch journal with dbarnes as manager. Then drive the
			// OrcidSettings form just far enough to flip orcidEnabled + set
			// the client credentials (the IdentityForm gates the button on
			// `OrcidManager::isEnabled()`, which reads
			// `$context->getData('orcidEnabled')`).
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
				const page = await ctx.newPage();
				await enableOrcidViaSettingsForm(page, context.path);

				// Defensive pre-cleanup: dbarnes is shared across tests
				// in this file, and the OAuth-shortcut test verifies an
				// ORCID on this same user. If that test's `finally`
				// cleanup was skipped on a prior run (process kill, retry
				// budget, etc.), dbarnes is still verified and the
				// connect button never renders. Hit the test-mode
				// shortcut to wipe the orcid fields.
				await ctx.request.get(
					`/index.php/${context.path}/orcid/authorizeOrcid` +
						`?targetOp=profile&testFakeOrcid=clear`,
				);

				// User profile lives at /{contextPath}/user/profile. The
				// connect-orcid-button is rendered by
				// lib/pkp/templates/form/orcidProfile.tpl and inserted into
				// the Identity form via templates/user/identityForm.tpl.
				// Note: the button gets injected via JS (insertAfter on the
				// hidden orcid input), so we must wait for it to appear in
				// the DOM rather than asserting on the initial HTML.
				await page.goto(`/index.php/${context.path}/user/profile`);
				const connect = page.locator('#connect-orcid-button');
				await expect(connect).toBeVisible({timeout: 15_000});
				// Sanity: the button label should include the localized
				// "Create or Connect your ORCID iD" / "Authorize ORCID"
				// text (orcid.connect / orcid.authorise).
				await expect(connect).toContainText(/ORCID/i);
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'OAuth redirect_uri flow stores the verified ORCID iD on the user profile (test-mode shortcut)',
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'oauth');
			const fakeOrcid = '0000-0001-2345-6789';
			const orcidSandboxUrl = `https://sandbox.orcid.org/${fakeOrcid}`;

			// E0 scratch journal with dbarnes as manager. Enable ORCID so
			// the IdentityForm renders the connect button and so
			// `OrcidManager::isEnabled()` lets `AuthorizeUserData` run.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
			});
			try {
				const page = await ctx.newPage();
				await enableOrcidViaSettingsForm(page, context.path);

				// Defensive pre-cleanup: a previous run of this test
				// against the same DB instance may have left dbarnes in a
				// verified-ORCID state if cleanup was skipped (process
				// kill, retry budget, etc.). Reset before asserting on
				// the unverified-state UI.
				await ctx.request.get(
					`/index.php/${context.path}/orcid/authorizeOrcid` +
						`?targetOp=profile&testFakeOrcid=clear`,
				);

				// Sanity-check the Connect button is present before the
				// handshake, so a regression in step 1 surfaces clearly.
				await page.goto(`/index.php/${context.path}/user/profile`);
				await expect(
					page.locator('#connect-orcid-button'),
				).toBeVisible({timeout: 15_000});

				// Simulate the redirect ORCID would issue back to OJS after
				// a successful OAuth handshake. The real flow is:
				//   1. window.open(orcidOAuthUrl) -> sandbox.orcid.org
				//   2. user authenticates on ORCID
				//   3. ORCID 302s back to /orcid/authorizeOrcid?code=...
				//   4. OJS POSTs that `code` to ORCID's oauth/token endpoint
				//      and receives the verified iD.
				// Steps 1-3 happen entirely outside OJS. We jump straight
				// to step 4's URL, replacing `code` with `testFakeOrcid` —
				// AuthorizeUserData::execute()'s test-mode shortcut sees
				// the param, skips the live ORCID token POST, and
				// synthesises the same token-response payload the real
				// endpoint would have returned. Same `targetOp=profile`
				// branch then runs unchanged: `setVerifiedOrcidOAuthData`
				// + `Repo::user()->edit`.
				await page.goto(
					`/index.php/${context.path}/orcid/authorizeOrcid` +
						`?targetOp=profile&testFakeOrcid=${fakeOrcid}`,
				);

				// The handler emits an inline <script> that closes the
				// popup window via `window.close()` and reloads the
				// opener's profile-tabs widget. In a single-page nav (no
				// real popup), that script is a no-op for our purposes —
				// we just need the side effect on the user row, which is
				// committed before the response is rendered. Reload the
				// profile and verify.
				await page.goto(`/index.php/${context.path}/user/profile`);

				// When `orcidIsVerified` is true, orcidProfile.tpl swaps
				// the connect button for an `<a id='orcid-link'>` whose
				// href is the stored ORCID URL (line 32 of
				// templates/form/orcidProfile.tpl). identityForm.tpl
				// additionally renders `#deleteOrcidButton` only on the
				// verified branch.
				const verifiedLink = page.locator('#orcid-link');
				await expect(verifiedLink).toBeVisible({timeout: 15_000});
				await expect(verifiedLink).toHaveAttribute(
					'href',
					orcidSandboxUrl,
				);
				await expect(
					page.locator('#deleteOrcidButton'),
				).toBeVisible();
				// And the connect button should be gone.
				await expect(
					page.locator('#connect-orcid-button'),
				).toHaveCount(0);
			} finally {
				try {
					// Cleanup: dbarnes is a baseline user shared with
					// other tests in this file (the connect-button test
					// uses dbarnes too). Leaving the verified ORCID on
					// the user row would contaminate later runs against
					// the same DB instance — the connect button only
					// renders for unverified users. Hit the same
					// shortcut endpoint with `testFakeOrcid=clear` to
					// wipe the ORCID fields. Wrapped so a cleanup-only
					// failure can't mask the test result.
					await ctx.request.get(
						`/index.php/${context.path}/orcid/authorizeOrcid` +
							`?targetOp=profile&testFakeOrcid=clear`,
					);
				} catch {
					// best-effort
				}
				await ctx.close();
			}
		},
	);

	test(
		'a verified ORCID iD seeded onto a contributor renders on the article reader page',
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'rdr');
			const orcidUrl = 'https://orcid.org/0000-0001-2345-6789';

			// Seed a fully-published submission via the standard fixture,
			// then push orcid + orcidIsVerified onto the seeded primary
			// Author row through the SubmissionBuilderProcessor's
			// `author` passthrough (added in this PR). Going through the
			// public REST contributors endpoint isn't an option — the
			// Author validator hard-blocks orcid updates with
			// `api.orcid.403.cannotUpdateAuthorOrcid` so that the only
			// path to an ORCID iD is the live OAuth handshake. The
			// passthrough writes via Repo::author()->edit() and bypasses
			// validation, which is exactly the "DB-level injection"
			// option called out in the row plan.
			//
			// The article-page template
			// (templates/frontend/objects/article_details.tpl#135-146)
			// gates the ORCID block on `$author->getData('orcid')` and
			// picks the verified-vs-unverified icon via
			// `$author->hasVerifiedOrcid()`, which reads `orcidIsVerified`.
			const spec = submissionPublished({tag});
			spec.author = {orcid: orcidUrl, orcidIsVerified: true};
			const {submission} = await pkpApi.createSubmission(spec);

			// Anonymous reader visits the article landing page — no auth.
			const anon = await browser.newContext({
				baseURL,
			});
			try {
				const page = await anon.newPage();
				const resp = await page.goto(
					`/index.php/publicknowledge/article/view/${submission.id}`,
				);
				expect(resp?.status()).toBe(200);

				// The ORCID block is `<span class="orcid">` with the
				// verified-icon and a link whose href is the raw ORCID
				// URL we stored. Anchor on the seeded URL so parallel
				// workers' authors don't collide.
				const orcidLink = page.locator(
					`a[href="${orcidUrl}"]`,
				);
				await expect(orcidLink).toBeVisible({timeout: 15_000});
				// The verified branch (hasVerifiedOrcid()=true) shows the
				// raw iD without the "(unauthenticated)" suffix.
				await expect(orcidLink).toContainText(orcidUrl);
			} finally {
				await anon.close();
			}
		},
	);
});

/**
 * Shared helper: drive the OrcidSettings form on a scratch journal far
 * enough to flip `orcidEnabled` and seed valid sandbox credentials, so
 * downstream pages that gate UI on `OrcidManager::isEnabled()` start
 * rendering the ORCID surface.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} contextPath
 */
async function enableOrcidViaSettingsForm(page, contextPath) {
	await page.goto(
		`/index.php/${contextPath}/management/settings/access`,
	);
	const tabButton = page.locator('#orcidSettings-button');
	await expect(tabButton).toBeVisible({timeout: 15_000});
	await tabButton.click();
	const form = page.locator('#orcidSettings form.pkpForm');
	await expect(form).toBeVisible({timeout: 15_000});
	await form.locator('input[name^="orcidEnabled"]').first().check();
	await form
		.locator('select[name="orcidApiType"]')
		.selectOption('memberSandbox');
	await form.locator('input[name="orcidClientId"]').fill('TEST_CLIENT_ID');
	await form
		.locator('input[name="orcidClientSecret"]')
		.fill('TEST_SECRET');
	await Promise.all([
		page.waitForResponse(
			(res) =>
				/\/api\/v1\/contexts\/\d+/.test(res.url()) &&
				res.ok() &&
				['POST', 'PUT'].includes(res.request().method()),
			{timeout: 15_000},
		),
		form.getByRole('button', {name: 'Save', exact: true}).click(),
	]);
}

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared submissions list / scratch-journal path.
 *
 * @param {import('@playwright/test').TestInfo} info
 * @param {string} suffix
 */
function uniqueTag(info, suffix) {
	const slug = info.title
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.slice(0, 12);
	const rand = Math.random().toString(36).slice(2, 6);
	return `t-w${info.parallelIndex}-${suffix}-${slug}-${rand}`;
}

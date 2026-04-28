// @ts-check
const {test, expect} = require('../support/base-test.js');
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
 * impossible to drive in dev without a fake OAuth server. The Cypress
 * suite never tried — it stubbed `window.open` to load a hidden
 * iframe whose srcdoc runs an inline script that writes the
 * registration form fields in the parent window directly, mirroring
 * what the success-side script returned by `AuthorizeUserData` would
 * do on the parent. Test 3 below ports that approach verbatim.
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
 *   3. **E: registration form populated via stubbed window.open** —
 *      anonymous visit to the registration page, stub `window.open`
 *      with the iframe-srcdoc trick (matching the Cypress
 *      "Uses ORCID in user registration" test), click the connect
 *      button, assert the registration fields get populated as the
 *      success-script would set them.
 *
 *   4. **R: ORCID displayed on article page** — seed a published
 *      submission, then push `orcid` + `orcidIsVerified` onto the
 *      seeded Author row through `SubmissionBuilderProcessor`'s
 *      `author` passthrough. Visit the article URL anonymously and
 *      assert the verified-ORCID block renders (`<span class="orcid">`
 *      with an `<a href>` pointing at the seeded ORCID URL).
 *
 * Scope deviations / deferred work:
 *   - The server-side OAuth callback (`AuthorizeUserData::execute`'s
 *     token POST + `setVerifiedOrcidOAuthData` storage path) is not
 *     covered end-to-end. The Cypress suite never covered it either;
 *     a test-only `?testFakeOrcid=...` bypass to drive it would
 *     require a query-string-only privilege escalation that doesn't
 *     match the rest of the test surface (everything else is gated by
 *     `TestModeGate`'s `X-Test-Key` header). That validation belongs
 *     in a PHP unit test, not an end-to-end spec.
 *   - **Sends ORCID verification request to author** (the OJS-only
 *     Cypress test) is the request-verification-email sidemodal on
 *     the Contributors workflow panel. That path dispatches
 *     `RequestOrcidVerification` + queues mail and belongs in a
 *     separate row keyed off mailpit assertions; deferred for now.
 */
test.describe('ORCID integration', () => {
	test(
		'manager fills the ORCID settings, the values persist on reload, and disabling clears the form',
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'cfg');

			// E0 scratch journal — orcid* settings are per-context, and the
			// bootstrap publicknowledge journal must stay read-only.
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await asUser('dbarnes');
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
		
		},
	);

	test(
		'with ORCID enabled on the journal, the user profile renders the Connect ORCID iD button',
		async ({pkpApi, asUser}) => {
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

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await enableOrcidViaSettingsForm(page, context.path);

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
		
		},
	);

	test(
		'connecting ORCID during user registration populates the form fields',
		async ({pkpApi, asUser, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'reg');

			// E0 scratch journal with ORCID enabled — the registration
			// page only injects the connect button when the journal has
			// orcidEnabled set (templates/frontend/pages/userRegister.tpl
			// includes form/orcidProfile.tpl behind that flag).
			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});
			const managerCtx = await asUser('dbarnes');
			const settingsPage = await managerCtx.newPage();
			await enableOrcidViaSettingsForm(settingsPage, context.path);

			// Anonymous registration flow — no authenticated session.
			const anon = await browser.newContext({baseURL});
			try {
				const page = await anon.newPage();

				// Short-circuit any request to sandbox.orcid.org. The
				// template's openORCID() makes a JSONP call to
				// userStatus.json before opening the popup, and the
				// stubbed iframe sets `src=url` once before its srcdoc
				// override takes effect. Both could otherwise reach the
				// public ORCID sandbox during a test run.
				await page.route('**/sandbox.orcid.org/**', (route) =>
					route.fulfill({status: 200, body: ''}),
				);

				await page.goto(`/index.php/${context.path}/user/register`);
				await expect(
					page.locator('#connect-orcid-button'),
				).toBeVisible({timeout: 15_000});

				// Stub window.open with the same iframe-srcdoc trick the
				// legacy Cypress suite uses (commands_orcid.js +
				// Orcid.cy.js). Cypress doesn't have multi-tab support;
				// neither does this approach attempt one. The iframe's
				// srcdoc runs in the parent's origin and writes the
				// registration form fields directly — exactly the side
				// effect a real OAuth round-trip would achieve via the
				// inline script `AuthorizeUserData` returns. The
				// returned contentWindow lets the template's
				// `oauthWindow.opener = self` line run without throwing.
				await page.evaluate(() => {
					/** @type {any} */ (window).open = (url) => {
						const iframe = document.createElement('iframe');
						iframe.id = 'orcid-stub-iframe';
						iframe.style.display = 'none';
						iframe.src = url;
						document.body.appendChild(iframe);
						iframe.srcdoc = `<html><body><script type='text/javascript'>
							parent.document.getElementById('givenName').value = 'John';
							parent.document.getElementById('familyName').value = 'Doe';
							parent.document.getElementById('email').value = 'john.doe@example.com';
							parent.document.getElementById('country').value = 'JM';
							parent.document.getElementById('affiliation').value = 'PKP';
							parent.document.getElementById('orcid').value = 'https://orcid.org/1000-2000-3000-4000';
							parent.document.getElementById('connect-orcid-button').style.display = 'none';
						</script></body></html>`;
						return iframe.contentWindow;
					};
				});

				await page.locator('#connect-orcid-button').click();

				// The iframe's script is asynchronous — it runs after
				// srcdoc parse. Wait on the field that's set last in
				// the script (the connect-button hide), then check the
				// rest.
				await expect(
					page.locator('#connect-orcid-button'),
				).toBeHidden({timeout: 10_000});
				await expect(page.locator('#givenName')).toHaveValue('John');
				await expect(page.locator('#familyName')).toHaveValue('Doe');
				await expect(page.locator('#email')).toHaveValue(
					'john.doe@example.com',
				);
				await expect(page.locator('select#country')).toHaveValue('JM');
				await expect(page.locator('#affiliation')).toHaveValue('PKP');
				await expect(page.locator('#orcid')).toHaveValue(
					'https://orcid.org/1000-2000-3000-4000',
				);
			} finally {
				await anon.close();
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

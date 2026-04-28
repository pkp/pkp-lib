// @ts-check
const {test, expect} = require('../support/base-test.js');
const {getPassword} = require('../data/users.js');

/**
 * Public user registration — row #58 in docs/e2e-playwright-migration.md.
 *
 * Ports the Cypress `cy.register()` helper (lib/pkp/cypress/support/
 * commands.js:181) plus the post-registration "Make a New Submission"
 * handoff used by the canonical AmwandengaSubmission.cy.js's
 * `Registers as author and creates a submission` test (and ~20 other
 * `60-content/*Submission.cy.js` specs that bundle the same pattern).
 *
 * Spec runs anonymously (no storageState). The bootstrapped
 * publicknowledge journal is the registration context — the test only
 * INSERTs into the users table, so it doesn't disturb any other spec
 * that reads the user list (parallel-safe via a unique-tag username).
 *
 * Form note — `/user/register` is rendered by
 * `lib/pkp/templates/frontend/pages/userRegister.tpl` +
 * `frontend/components/registrationForm.tpl`. Despite the surrounding
 * Cypress helper using `id=` selectors, the template ships stable
 * `name=` attributes on every input which we anchor on instead.
 * Country is a plain `<select name="country">` whose options are keyed
 * by ISO-3166 alpha-2 code (see RegistrationForm::display:
 * `$countries[$country->getAlpha2()] = $country->getLocalName()`), so
 * we select by value rather than visible label.
 *
 * Post-submit flow — RegistrationHandler::register logs the user in
 * (no `email.require_validation` in the test config) and redirects
 * back to `/user/register`; that GET sees `Validation::isLoggedIn()`
 * and serves `frontend/pages/userRegisterComplete.tpl` with the
 * "Make a New Submission" link to `/{context}/submission`.
 */
test.describe('Public user registration', () => {
	test(
		'anonymous visitor registers via /user/register, lands on the dashboard, and can start a new submission',
		async ({browser, baseURL}) => {
			const ctx = await browser.newContext({
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();

				// Unique-tag username keeps parallel workers + reruns
				// from colliding on the users table's UNIQUE(username)
				// constraint. 32-char max per the form's input cap.
				const suffix = Math.random().toString(36).slice(2, 8);
				const username = `reg-w${test.info().parallelIndex}-${suffix}`;
				const password = getPassword(username);
				const email = `${username}@mailinator.com`;

				await page.goto('/index.php/publicknowledge/user/register');
				await expect(
					page.getByRole('heading', {name: 'Register'}),
				).toBeVisible();

				// Identity fieldset.
				await page.locator('input[name="givenName"]').fill('Reg');
				await page.locator('input[name="familyName"]').fill('Tester');
				await page
					.locator('input[name="affiliation"]')
					.fill('Public Knowledge Project');
				// Country picker: native <select> keyed by alpha-2.
				await page.locator('select[name="country"]').selectOption('CA');

				// Login fieldset.
				await page.locator('input[name="email"]').fill(email);
				await page.locator('input[name="username"]').fill(username);
				await page.locator('input[name="password"]').fill(password);
				await page.locator('input[name="password2"]').fill(password);

				// Privacy consent — required when the journal carries a
				// privacyStatement (the bootstrapped publicknowledge does;
				// see config/registry/contextSettings.xml). The Cypress
				// helper unconditionally clicks this.
				await page.locator('input[name="privacyConsent"]').check();

				// Submit. The handler logs the user in and redirects to
				// /user/register, which then serves the success page.
				// Wait for the URL to settle on /user/register and the
				// "Make a New Submission" link to render before
				// proceeding (the redirect chain involves a fresh GET).
				await Promise.all([
					page.waitForURL(/\/user\/register(\/|\?|$)/),
					page.locator('form#register button[type="submit"]').click(),
				]);

				// The post-registration landing page.
				await expect(
					page.getByRole('heading', {
						name: 'Registration complete',
					}),
				).toBeVisible();

				// Click "Make a New Submission" — the post-registration CTA
				// rendered by templates/frontend/pages/userRegisterComplete.tpl
				// (`<li class="new_submission"><a>...</a></li>`).
				await page
					.getByRole('link', {name: 'Make a New Submission'})
					.click();

				// Wizard's Start step. `getByRole('heading', {name: 'Make
				// a Submission'})` is the same anchor used by
				// wizard-section-rules.spec.js. Wait for the StartSubmission
				// Vue form to mount via its TinyMCE iframe (the Title
				// control). 15s mirrors the precedent in row #12.
				await expect(
					page.getByRole('heading', {name: 'Make a Submission'}),
				).toBeVisible();
				await expect(
					page.locator('#startSubmission-title-control_ifr'),
				).toBeAttached({timeout: 15_000});

				// Section dropdown — bootstrap journal seeds two sections
				// (Articles + Reviews), so StartSubmission renders a
				// FieldOptions radio. Anchor on the Section legend +
				// Articles option label, same pattern as
				// wizard-section-rules.spec.js.
				const sectionField = page.locator('.pkpFormField--options', {
					has: page.locator('legend', {hasText: 'Section'}),
				});
				await expect(sectionField).toBeVisible();
				await expect(
					sectionField.locator('label', {hasText: 'Articles'}),
				).toBeVisible();
			} finally {
				await ctx.close();
			}
		},
	);
});

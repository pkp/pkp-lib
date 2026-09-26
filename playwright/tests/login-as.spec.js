// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Login-as (impersonation) — row #44 in docs/e2e-playwright-migration.md.
 *
 * Cypress source: AmwandengaSubmission.cy.js test 13 ("Logout as should
 * redirect to the same submission workflow"). That serial-suite test
 * drives the stage-participants `Login As` affordance on a specific
 * submission (legacy `StageParticipantGridRow` → side-modal OK) and
 * asserts a post-logout redirect that relies on the legacy `redirectUrl`
 * query param. The feature under test — per the roadmap cell — is
 * "admin logs in as user; logout returns to the admin session", i.e.
 * the site-level impersonation capability surfaced by
 * `PKPSessionGuard::signInAs` / `signOutAs` and wired from both the
 * Users & Roles admin grid (`UserGridRow` → `logInAs` link action) and
 * the stage-participant grid. This spec exercises that capability
 * through the canonical URL route both UI affordances invoke:
 *
 *   GET  /index.php/index/login/signInAsUser/{userId}   — start impersonation
 *   GET  /index.php/index/login/signOutAsUser           — end impersonation
 *
 * Driving impersonation through the URL rather than the legacy jQuery
 * grid's More Actions dropdown + RedirectConfirmationModal keeps the
 * spec anchored on the server-side state transition (the part that can
 * actually break) instead of the legacy grid UX. Verification of the
 * impersonation state is read directly from `window.pkp.currentUser`,
 * which `PKPTemplateManager::getJavaScriptData` injects on every
 * authenticated page and whose `isUserLoggedInAs` / `loggedInAsUser`
 * fields are the single source of truth for the TopNavActions
 * "Logged in as X / Log Out As X" UI affordance.
 *
 * Roadmap scope: "admin logs in as user; logout returns to the admin
 * session". Scope dropped vs. the Cypress source: the
 * `workflowSubmissionId` redirect round-trip (that's a legacy
 * stage-participant grid concern — impersonation of a submission
 * participant; the roadmap asks for the site-level capability).
 */
test.describe('Login-as (impersonation)', () => {
	test.use({user: 'admin'});

	test(
		'site admin impersonates a user; logout returns to the admin session',
		{tag: '@regression'},
		async ({page}) => {
			// Baseline assertion: logged in as admin. The user/profile
			// page is the smallest page that renders for any
			// authenticated user regardless of role (no dashboard role
			// gate), so it's safe to use here both pre- and
			// post-impersonation. Read pkp.currentUser to prove the
			// session belongs to admin at this point.
			await page.goto('/index.php/index/user/profile');

			// Resolve dbarnes's user id through the in-page `fetch`. The
			// Playwright `page.request` API is wired to a separate
			// APIRequestContext that doesn't always inherit the page's
			// session cookies in the expected way on context-scoped
			// routes, so we use the authenticated document's own
			// `fetch` — cookies ride along automatically. The route is
			// context-scoped (publicknowledge); site admin has read
			// access across contexts through the UserController's
			// ROLE_ID_SITE_ADMIN allow.
			const target = await page.evaluate(async () => {
				const r = await fetch(
					'/index.php/publicknowledge/api/v1/users?searchPhrase=dbarnes',
					{headers: {Accept: 'application/json'}},
				);
				if (!r.ok) {
					throw new Error(`GET users: ${r.status} ${await r.text()}`);
				}
				const j = await r.json();
				return (j.items ?? []).find((u) => u.userName === 'dbarnes');
			});
			expect(target, 'dbarnes user resolved via API').toBeTruthy();
			await expect(page).not.toHaveURL(/\/login/);
			let currentUser = await page.evaluate(() => window.pkp?.currentUser);
			expect(currentUser, 'pkp.currentUser injected').toBeTruthy();
			expect(currentUser.username).toBe('admin');
			expect(currentUser.isUserLoggedInAs).toBeFalsy();

			// Drive impersonation through the canonical URL. This is the
			// exact URL the Users & Roles "Log in as" link action and
			// the stage-participant "Login As" button both navigate to
			// after the RedirectConfirmationModal's OK click.
			const signInResp = await page.goto(
				`/index.php/index/login/signInAsUser/${target.id}`,
			);
			expect(signInResp?.status()).toBeLessThan(400);

			// Post-impersonation: the same profile page now reports
			// dbarnes as the active user and flags the impersonation
			// state. loggedInAsUser carries the original admin identity
			// (used by TopNavActions to render the "Logged in as admin
			// / Log Out As admin" notice). Use the context-scoped
			// user/profile route — dbarnes has a publicknowledge role,
			// but the site-level profile also resolves since they're a
			// site user.
			await page.goto('/index.php/index/user/profile');
			await expect(page).not.toHaveURL(/\/login/);
			currentUser = await page.evaluate(() => window.pkp?.currentUser);
			expect(currentUser).toBeTruthy();
			expect(currentUser.username).toBe('dbarnes');
			expect(currentUser.isUserLoggedInAs).toBe(true);
			expect(currentUser.loggedInAsUser, 'admin identity preserved').toMatchObject({
				username: 'admin',
			});

			// End impersonation via the canonical URL. The
			// LoginHandler::signOutAsUser path rolls the session back to
			// admin and redirects home.
			const signOutResp = await page.goto(
				'/index.php/index/login/signOutAsUser',
			);
			expect(signOutResp?.status()).toBeLessThan(400);

			// Session is back to admin. isUserLoggedInAs flipped off.
			await page.goto('/index.php/index/user/profile');
			await expect(page).not.toHaveURL(/\/login/);
			currentUser = await page.evaluate(() => window.pkp?.currentUser);
			expect(currentUser).toBeTruthy();
			expect(currentUser.username).toBe('admin');
			expect(currentUser.isUserLoggedInAs).toBeFalsy();
		},
	);
});

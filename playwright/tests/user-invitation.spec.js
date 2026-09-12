// @ts-check
const {test, expect} = require('../support/base-test.js');
/**
 * User invitation flow (multi-actor) — row #57 in
 * docs/e2e-playwright-migration.md.
 *
 * Ports the Cypress `createUserByInvitation` helper
 * (`lib/pkp/cypress/support/commands.js#1008`), which strings together
 * `inviteUser` (manager-side wizard) + `confirmEmail` (Mailpit click-
 * through) + `confirmationByUser` (AcceptInvitation Vue page) into the
 * single multi-actor journey baseline OJS users actually traverse:
 *
 *   manager → email → invitee → registration → role on context.
 *
 * Row #60 (`user-role-assignment.spec.js`) covers the manager-side
 * wizard for an EXISTING user; this spec covers the new-user branch end
 * to end including the email click-through + acceptance + REST
 * verification.
 *
 * ## Step shapes (verified via classes/invitation/stepTypes/...)
 *
 * `SendInvitationStep::getSteps` (manager side):
 *   1. searchUser  — only when `!$invitation && !$user`. The Vue
 *      `UserInvitationSearchFormStep` searches `/api/v1/users` for an
 *      exact email/username/orcid match. When no match is found AND
 *      the search value is a valid email, it stashes the value as
 *      `inviteeEmail` on the wizard payload. The next-button label is
 *      "Search User"; `nextStep` runs the registered action which is
 *      idempotent for a new email — falls into the new-user branch.
 *   2. userDetails  — Enter Details: `inviteeEmail` (pre-filled),
 *      `givenName`, `familyName`, plus the role table
 *      (`UserInvitationUserGroupsTable`). The wizard pre-renders one
 *      empty `userGroupsToAdd` row (see store.js#239), so picking a
 *      role + start date + masthead does NOT require an extra click.
 *   3. userInvited — Email Composer. The auto-loaded
 *      UserRoleAssignmentInvitationNotify body is fine; the
 *      "Invite user to the role" button drives `invitations/{id}/invite`.
 *
 * `AcceptInvitationStep::getSteps` (invitee side, anonymous +
 * `OrcidManager::isEnabled` is false on a scratch journal so the ORCID
 * step is omitted):
 *   1. userCreate         — username + password + privacy consent
 *      (component `AcceptInvitationUserAccountDetails`).
 *   2. userDetails        — affiliation, givenName, familyName,
 *      userCountry. Pre-filled from the manager's invitation but the
 *      `userCountry` field is required and not pre-set (the manager
 *      step doesn't ask for it), so the spec fills it.
 *   3. userCreateReview   — review + finalize. Surprise — the en
 *      translation for the next-button label lives in the OJS-specific
 *      `locale/en/invitation.po` ("Accept And Continue to OJS"), NOT
 *      the shared lib/pkp invitation.po. OMP and OPS override the same
 *      key with their own brand strings. Anchor on the "Accept And
 *      Continue" prefix to keep the spec application-agnostic.
 *
 * Surprise #2 — the PKP `finalize` endpoint does NOT auto-log-in the
 * new user; clicking "View All Submissions" on the success dialog
 * redirects to `/{contextPath}/submissions` which bounces through
 * `/login`. The spec therefore drives a fresh login with the new
 * credentials as the final fidelity check.
 *
 * Surprise #3 — the OJS login form's password input ships with
 * `maxlength="32"` (lib/pkp/templates/frontend/components/loginForm.tpl),
 * but the AcceptInvitation wizard's password field has no such cap.
 * A long generated password (e.g. `username + username` per the
 * baseline `getPassword` rule, which produces 40+ chars for a tagged
 * username) writes a hash on accept that no later login can ever match
 * — Playwright's `.fill()` honours maxlength and silently truncates.
 * Use a short literal password (under 32 chars) here; the data/users.js
 * baseline rule does NOT apply to this spec.
 *
 * ## Email link extraction
 *
 *   The body's <a class='btn btn-accept'> anchor reads "Accept
 *   Invitation" (locale `emails.userRoleAssignmentInvitationNotify.body`,
 *   verified at lib/pkp/locale/en/emails.po:618). `pkpMail.extractLink`
 *   finds it via the link-text regex.
 *
 * ## Locator pitfalls
 *
 *   - Headlessui menus + reka-ui dialogs: same scoping rules as
 *     row #60 — `getByRole('menuitem', ...)` / `getByRole('dialog')` at
 *     the page level (portaled to document root).
 *   - Side-modal vs full-page: the manager-side invitation wizard
 *     mounts at `/management/settings/access/invitation` (full page),
 *     not a side modal. The "Invite to a role" button on the access
 *     page navigates there.
 *   - The accept URL is a `/{contextPath}/invitation/accept?id=…&key=…`
 *     redirect that the InvitationActionRedirectController bounces
 *     through to `/{contextPath}/invitation/userRoleAssignment/...`
 *     before mounting the AcceptInvitationPage Vue component. Don't
 *     race the URL — wait for the page mount via the first-step heading.
 *   - Privacy-consent renders as a FieldOptions checkbox with a single
 *     option whose label contains an `<a>` for the privacy statement;
 *     the input itself is `input[name="privacyStatement"][type="checkbox"]`.
 *     Use `.check()`, not `.click()` (the label wraps the link too).
 *   - The user-details form is multilingual; the wizard's primary
 *     locale on the scratch journal is `en`, so anchor on the `-en`
 *     suffixed inputs. Cypress's `confirmationByUser` also drove the
 *     French tab — dropped here since the row's invariant is the
 *     primary-locale round-trip, not multilingual editing (covered by
 *     row #34 / multilingual.spec.js).
 *
 * ## Mail::fake boundary
 *
 *   The manager-side POSTs go through the regular invitation API
 *   (`/api/v1/invitations/{id}/invite`), so the resulting mail flows to
 *   Mailpit (verified pattern from row #7 graduate). `pkpMail.clearAll()`
 *   isn't strictly needed since each test uses a unique-tag email, but
 *   it's cheap insurance against stale mail from other workers/tests
 *   targeting the same baseline addresses.
 */
test.describe('User invitation flow (multi-actor)', () => {
	test(
		'manager invites a new user; the invitee accepts via the email link, completes registration, and lands in the assigned role',
		{tag: '@regression'},
		async ({pkpApi, pkpMail, browser, baseURL, asUser}) => {
			const tag = uniqueTag();
			const inviteeEmail = `invitee-${tag}@mailinator.com`;
			const inviteeUsername = `invitee-${tag}`;
			// The OJS login form's password input ships `maxlength=32`
			// (lib/pkp/templates/frontend/components/loginForm.tpl); the
			// AcceptInvitation wizard's password field has no such cap,
			// so a `getPassword(username)` derivative ("username + username"
			// per data/users.js#getPassword) writes a 40+ char hash on
			// accept that no subsequent login can ever match — the form
			// silently truncates to 32 chars. Use a short literal that
			// fits both forms; "Inv1tee!Pwd" exceeds the minimum-length
			// rule (6 by default) and stays under any maxlength.
			const inviteePassword = 'Inv1tee!Pwd';
			const inviteeGivenName = 'Invited';
			const inviteeFamilyName = `User-${tag}`;

			const {context} = await pkpApi.createJournal({
				tag,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			// Pre-clear Mailpit; the unique-tag email keeps us safe but
			// stale latestTo lookups across workers are cheaper to
			// pre-empt than to debug.
			await pkpMail.clearAll();

			const managerCtx = await asUser('dbarnes');
			let inviteeCtx;
			try {
				const managerPage = await managerCtx.newPage();
				await managerPage.goto(
					`/index.php/${context.path}/management/settings/access`,
				);
				await expect(
					managerPage.getByRole('heading', {name: 'Users & Roles'}),
				).toBeVisible();

				// Drive "Invite to a role". Per InvitationHandler / the
				// access page's button, this navigates to the invitation
				// wizard at /invitation/userRoleAssignment.
				await managerPage
					.getByRole('button', {name: 'Invite to a role', exact: true})
					.click();

				// Step 1 — Search User. Wait for the search input + its
				// surrounding step heading to mount.
				await expect(
					managerPage.getByRole('heading', {
						name: /STEP 1 - Search User/i,
					}),
				).toBeVisible({timeout: 15_000});

				// The FieldText control name is `search` on the wizard's
				// payload. Native input has `name="search"`; we anchor on
				// it directly.
				await managerPage.locator('input[name="search"]').fill(inviteeEmail);

				// "Search User" advance — the registered action runs the
				// user query, finds none, and sets `inviteeEmail` on the
				// wizard payload before letting the wizard advance.
				await managerPage
					.getByRole('button', {name: 'Search User', exact: true})
					.click();

				// Step 2 — Enter Details. Email is pre-filled from the
				// search step; given/family names + role table need
				// driving.
				await expect(
					managerPage.getByRole('heading', {
						name: /STEP 2 - Enter details and invite for roles/i,
					}),
				).toBeVisible({timeout: 15_000});

				// givenName / familyName are multilingual <FieldText>
				// inputs whose `<input>` `name` is `givenName-en` etc.
				// The form is mounted with the wizard's primary locale
				// (en on the scratch journal).
				await managerPage
					.locator('input[name="givenName-en"]')
					.fill(inviteeGivenName);
				await managerPage
					.locator('input[name="familyName-en"]')
					.fill(inviteeFamilyName);

				// Role table — one empty row pre-rendered. Pick Reviewer
				// (the row's plan choice; reviewer roles auto-show on
				// masthead and don't surface the masthead select).
				await managerPage
					.locator('select[name="userGroupId"]')
					.selectOption({label: 'Reviewer'});

				const today = new Date().toISOString().split('T')[0];
				await managerPage.locator('input[name="dateStart"]').fill(today);

				// Reviewer is one of `reviewerUserGroupIds`, so masthead
				// renders as a static "Visible on Journal Masthead" span
				// (no select); skip the masthead pick.

				// Step 2 → Step 3 (email composer).
				await managerPage
					.getByRole('button', {name: 'Save And Continue', exact: true})
					.click();

				// Step 3 — Email Composer. The mailable's body is auto-
				// loaded; "Invite user to the role" submits the
				// invitation and dispatches the email.
				await expect(
					managerPage.getByRole('button', {
						name: 'Invite user to the role',
						exact: true,
					}),
				).toBeVisible({timeout: 15_000});
				await managerPage
					.getByRole('button', {
						name: 'Invite user to the role',
						exact: true,
					})
					.click();

				// "Invitation Sent" reka-ui PkpDialog confirms the
				// dispatch on the manager side.
				const sentDialog = managerPage.getByRole('dialog', {
					name: 'Invitation Sent',
				});
				await expect(sentDialog).toBeVisible({timeout: 15_000});
				await expect(sentDialog).toContainText(inviteeEmail);

				// ----- Email side -----
				// Pull the latest message addressed to the invitee and
				// extract the "Accept Invitation" link from the HTML body.
				const latest = await pkpMail.latestTo(inviteeEmail, {
					timeout: 15_000,
				});
				const full = await pkpMail.fullMessage(latest.ID);
				const html = full.HTML || '';
				// `pkpMail.extractLink` matches double-quoted hrefs only;
				// the UserRoleAssignmentInvitationNotify email body
				// (locale/en/emails.po:606-622) uses single-quoted hrefs
				// AND includes a `class='btn btn-accept'` attribute
				// between href and the closing `>` — neither variant the
				// shared helper covers. Anchor on the `class='btn-accept'`
				// signature (vs the sibling `class='btn-decline'` decline
				// link) instead, which is the stable invariant of the
				// invitation template.
				const acceptUrl = extractAcceptUrl(html);
				expect(acceptUrl, 'Accept Invitation link from email').toBeTruthy();

				// ----- Invitee side -----
				inviteeCtx = await browser.newContext({
					baseURL,
				});
				const inviteePage = await inviteeCtx.newPage();
				await inviteePage.goto(acceptUrl);

				// AcceptInvitation step 1 — userCreate (username +
				// password + privacy). Wait for the receiveInvitation
				// fetch to settle by anchoring on the email display
				// block — `store.email` is null until the fetch returns,
				// and the FieldText controls only mount once the wizard
				// store transitions through openStep, so a stale fill
				// can race the receive.
				await expect(
					inviteePage.getByText(inviteeEmail, {exact: true}).first(),
				).toBeVisible({timeout: 20_000});

				await inviteePage
					.locator('input[name="username"]')
					.fill(inviteeUsername);
				await inviteePage
					.locator('input[name="password"]')
					.fill(inviteePassword);

				// Privacy consent — FieldOptions checkbox + the post-fill
				// `toBeChecked` assertion forces the v-model emit to
				// land before "Save and continue", otherwise the wizard's
				// updateInvitationPayload() local privacy gate rejects
				// the step transition.
				const privacyCheckbox = inviteePage.locator(
					'input[name="privacyStatement"][type="checkbox"]',
				);
				await privacyCheckbox.check();
				await expect(privacyCheckbox).toBeChecked();

				// Step 1 → Step 2 (userDetails).
				await inviteePage
					.getByRole('button', {name: 'Save and continue', exact: true})
					.click();

				// AcceptInvitation step 2 — userDetails. The form fields
				// `givenName` / `familyName` are pre-filled from the
				// manager step. `affiliation` and `userCountry` are NOT
				// pre-filled — the manager-side wizard never collects
				// them.
				//
				// The form's input name pattern matches the same Field
				// definitions used by `UserDetailsForm`, with
				// `affiliation-en` for the multilingual text and
				// `userCountry` as a non-multilingual <select>.
				await expect(
					inviteePage.locator('input[name="affiliation-en"]'),
				).toBeVisible({timeout: 15_000});
				await inviteePage
					.locator('input[name="affiliation-en"]')
					.fill('Public Knowledge Project');
				await inviteePage
					.locator('select[name="userCountry"]')
					.selectOption('CA');

				// Step 2 → Step 3 (review). Same "Save and continue".
				await inviteePage
					.getByRole('button', {name: 'Save and continue', exact: true})
					.click();

				// AcceptInvitation step 3 — review + finalize.
				await expect(
					inviteePage.getByRole('heading', {
						name: /Review & create account/i,
					}),
				).toBeVisible({timeout: 15_000});

				// Review step's next button — `acceptInvitation
				// .detailsReview.nextButtonLabel` resolves via the
				// OJS-specific override at `locale/en/invitation.po:10`
				// to "Accept And Continue to OJS" (OMP/OPS would
				// override the same key with "...OMP" / "...OPS").
				// Anchor on the leading invariant prefix so this spec
				// stays application-agnostic.
				await inviteePage
					.getByRole('button', {name: /^Accept And Continue/i})
					.click();

				// Success modal — the post-finalize dialog confirms the
				// invitation was accepted. The modal title pulls from
				// `acceptInvitation.modal.title` ("You've been assigned a
				// new role in OJS" via the OJS-specific override at
				// locale/en/invitation.po:19).
				const successDialog = inviteePage.getByRole('dialog');
				await expect(successDialog).toBeVisible({timeout: 20_000});
				await expect(successDialog).toContainText(/new role/i);
				// Close the dialog. The "View All Submissions" callback
				// redirects to `/{contextPath}/submissions` — but the
				// PKP `finalize` endpoint does NOT auto-log-in the new
				// user (the OJS-side journey requires a manual login,
				// confirmed by the redirect bouncing through `/login`).
				// The fidelity step below logs in explicitly with the
				// freshly-created credentials, which is the canonical
				// "user lands in the assigned role" assertion.
				await successDialog
					.getByRole('button', {name: 'View All Submissions', exact: true})
					.click();

				// ----- REST verification (via the manager's session) -----
				// Confirm the new user exists with the assigned role on
				// the scratch journal.
				const usersRes = await managerPage.request.get(
					`/index.php/${context.path}/api/v1/users?searchPhrase=${encodeURIComponent(
						inviteeEmail,
					)}`,
				);
				expect(usersRes.ok()).toBeTruthy();
				const usersBody = await usersRes.json();
				expect(Array.isArray(usersBody.items)).toBeTruthy();
				const newUser = usersBody.items.find(
					(u) => u.email === inviteeEmail,
				);
				expect(newUser, `new user row for ${inviteeEmail}`).toBeTruthy();
				expect(newUser.userName).toBe(inviteeUsername);

				// Role-assignment shape: the user's `groups` array on
				// the journal-scoped users endpoint contains the user-
				// group rows for the journal context. Reviewer is in
				// the list.
				const groupNames = (newUser.groups || []).map((g) =>
					typeof g.name === 'string'
						? g.name
						: g.name?.en || Object.values(g.name || {})[0],
				);
				expect(
					groupNames.some((name) => /reviewer/i.test(String(name))),
					`new user has Reviewer role (groups: ${JSON.stringify(groupNames)})`,
				).toBeTruthy();

				// ----- Fidelity: log in as the new user -----
				// Drive the standard login form to confirm the
				// credentials the AcceptInvitation wizard wrote work
				// end-to-end. The dashboard URL pattern matches both
				// editorial (manager/editor) and mySubmissions (author/
				// reviewer-only) landings — Reviewer typically gets the
				// reviewer dashboard, but the bootstrap may route via
				// mySubmissions on first login. Anchor on either.
				await inviteePage.goto(`/index.php/${context.path}/login`);
				await inviteePage
					.locator('input[name="username"]')
					.fill(inviteeUsername);
				await inviteePage
					.locator('input[name="password"]')
					.fill(inviteePassword);
				await inviteePage
					.locator('form#login button[type="submit"]')
					.click();
				await inviteePage.waitForURL(
					/\/dashboard\/(editorial|mySubmissions|reviewAssignments)/,
					{timeout: 20_000},
				);
			} finally {
				if (inviteeCtx) {
					await inviteeCtx.close();
				}
				// Leave Mailpit empty for the next test — the unique
				// tag protects this run, but the next test's clearAll
				// will be cheaper if the inbox is small.
				await pkpMail.clearAll();
			}
		},
	);
});

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `r57-w${workerIndex}-${suffix}`;
}

/**
 * Pull the `<a class='btn btn-accept' href='...'>` URL out of the
 * userRoleAssignmentInvitationNotify HTML body. The shared
 * `pkpMail.extractLink` helper requires double-quoted hrefs and
 * contiguous link text, neither of which the email template ships;
 * keep this local rather than hand-tuning the shared helper for one
 * caller.
 */
function extractAcceptUrl(html) {
	const re = /<a[^>]+href=['"]([^'"]+)['"][^>]*class=['"][^'"]*btn-accept[^'"]*['"][^>]*>/i;
	const match = html.match(re);
	if (!match) {
		throw new Error('Accept Invitation link not found in mail body');
	}
	return match[1];
}

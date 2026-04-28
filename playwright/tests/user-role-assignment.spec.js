// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');

/**
 * Manager assigns a user to a role on Users & Roles — row #60 in
 * docs/e2e-playwright-migration.md.
 *
 * Mirrors the inbound side of the Cypress `inviteUser` helper
 * (`lib/pkp/cypress/support/commands.js#967`) for an EXISTING journal
 * user, distinct from the new-email invite branch covered by row #57.
 *
 * ## Surprise — there is no non-invite path in OJS today
 *
 * Probing the live UI confirmed the plan's "scope-drop" branch:
 *
 *   1. The Users & Roles page exposes ONE entry point for adding a
 *      role — the invitation wizard. Both "Invite to a role" (Users
 *      tab top-right) and the per-row "Edit" action route through
 *      `app(Invitation::class)->createNew('userRoleAssignment')`.
 *   2. The wizard's user-search step (`UserInvitationSearchFormStep`)
 *      hits `/api/v1/users?searchPhrase=…`, which filters by
 *      `contextId` server-side. So a baseline user not yet in the
 *      scratch journal is invisible to the wizard's search and falls
 *      into the "new user" branch — same as row #57.
 *   3. The "Edit user" action on a journal user opens the SAME wizard
 *      with the search step skipped (see `SendInvitationStep::getSteps`
 *      — `if (!$invitation && !$user) { ... }`). The remaining two
 *      steps are Enter Details + Email Composer; submitting the email
 *      only WRITES a pending invitation row + dispatches the email,
 *      it does not assign the role directly. The user must accept
 *      via the email link before `user_user_groups` is mutated.
 *
 * The genuinely-distinct UI path Row #60 can cover, then, is the
 * editUser branch: a manager opens the Edit-user wizard for a journal
 * user, adds a SECOND role, and the OJS UI surfaces a pending
 * invitation. The role-assignment-completion side (token + accept
 * link) is identical to row #57's territory; this spec stops at the
 * "Invitation Sent" confirmation + the pending invitation REST row,
 * which is the load-bearing assertion that the manager-side UI
 * actually wired the form to the invitation-create pipeline.
 *
 * ## Seed shape
 *
 *   E0 scratch journal:
 *     - dbarnes  → manager (scratch journal admin)
 *     - phudson  → reviewer (already has a role, so visible in the
 *                  Users grid + the Edit-user wizard reaches him with
 *                  the search step skipped)
 *
 * ## Locator notes
 *
 *   - The user-row "More Actions" trigger is a headlessui menu button
 *     with `aria-haspopup="menu"`. Its accessible name is the
 *     `userAccess.management.options` translation, but on a scratch
 *     journal that translation key falls back to `##…##`-wrapped raw
 *     in some locales' compile state — anchor on the row + the
 *     `aria-haspopup="menu"` attribute instead, which is stable.
 *   - The dropdown's menuitems render via headlessui portal at the
 *     document root; scope `getByRole('menuitem', {name: 'Edit'})` to
 *     the page (not the row).
 *   - The wizard's role select is a native `<select name="userGroupId">`.
 *     The `availableUserGroups` computed in `UserInvitationUserGroupsTable`
 *     filters out roles the user already holds, so "Reviewer" is
 *     absent for phudson — pick "Author" instead.
 *   - User-group IDs are scratch-journal-specific, so anchor on the
 *     visible role label (the option's text), not its value.
 *   - "Save And Continue" advances from Details → Email; submission
 *     button on the email step is "Invite user to the role".
 *   - The success dialog has `role="dialog"` with the
 *     `userInvitation.modal.title` heading "Invitation Sent".
 *
 * ## Drop list (vs the plan)
 *
 *   - The "user not yet in any scratch-journal role" requirement was
 *     dropped — see the surprise note above. phudson is seeded as a
 *     reviewer so the Users grid surfaces him; the spec then drives
 *     the editUser → Add Another Role path, which IS the only role
 *     surface the UI exposes for an already-known journal user.
 *   - The "log in as the assignee and verify role-gated pages" arm
 *     was dropped — that requires driving the email-link accept flow
 *     (row #57's territory).
 */
test.describe('Users & Roles — assign user to a role', () => {
	test(
		'manager assigns an existing journal user to an additional role and sees a pending invitation',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag();
			const {context} = await pkpApi.createJournal({
				tag,
				users: [
					{username: 'dbarnes', roles: ['manager']},
					// phudson seeded as reviewer so the Users grid
					// surfaces him AND the wizard's filterByContextIds
					// search would find him — but we drive the editUser
					// flow which sidesteps the search step entirely.
					{username: 'phudson', roles: ['reviewer']},
				],
			});

			const ctx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {baseURL}),
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await ctx.newPage();
				await page.goto(
					`/index.php/${context.path}/management/settings/access`,
				);

				// Wait for the Users-and-Roles page to land; the user
				// access table renders the seeded users via /api/v1/users.
				await expect(
					page.getByRole('heading', {name: 'Users & Roles'}),
				).toBeVisible();

				// Confirm phudson is in the user access table at baseline
				// — the row contains "Paul Hudson" + "Reviewer".
				const phudsonRow = page.locator('tr', {hasText: 'Paul Hudson'});
				await expect(phudsonRow).toBeVisible();
				await expect(phudsonRow).toContainText('Reviewer');

				// Open phudson's More Actions menu. The button carries
				// `aria-haspopup="menu"` reliably; the accessible name
				// uses an unresolved translation key on scratch journals
				// in some compile states, so anchor by attribute.
				await phudsonRow
					.locator('button[aria-haspopup="menu"]')
					.click();

				// The menu portal renders at the document root, hence the
				// page-scoped getByRole. "Edit" routes to the editUser
				// wizard (no Search step).
				await page
					.getByRole('menuitem', {name: 'Edit', exact: true})
					.click();

				// editUser → /management/settings/user/{id}; the wizard
				// renders directly into Step 1 ("Enter details"), with
				// the user's email/given/family pre-rendered as
				// read-only display blocks. The page-level h1 is empty
				// in editUser mode (UserRoleAssignmentInviteUIController
				// sets `pageTitle = ''` when `$user` is set), so anchor
				// on the step-2 heading "STEP 1 - Enter details and
				// invite for roles" (h2) which Vue renders once the
				// page mounts.
				await page.waitForURL(/\/management\/settings\/user\/\d+(\?|#|$)/);
				await expect(
					page.getByRole('heading', {
						name: /STEP 1 - Enter details and invite for roles/,
					}),
				).toBeVisible();
				// The role table renders the user's existing roles +
				// the start-date / masthead controls. Anchor on the
				// Journal Masthead columnheader before driving the form.
				await expect(
					page.getByRole('columnheader', {name: 'Journal Masthead'}),
				).toBeVisible();

				// Add Another Role appends an empty row to the
				// userGroupsToAdd state with role/date/masthead controls.
				await page
					.getByRole('button', {name: 'Add Another Role', exact: true})
					.click();

				// Pick the new role by visible label (the user_group_id
				// option values are scratch-journal-specific).
				// `availableUserGroups` filters out roles phudson
				// already holds, so "Reviewer" is excluded — Author is
				// the canonical pick for an existing-reviewer test.
				const newRoleSelect = page.locator('select[name="userGroupId"]');
				await newRoleSelect.selectOption({label: 'Author'});

				// Start date — the wizard uses HTML5 date input;
				// today's ISO date keeps things deterministic.
				const today = new Date().toISOString().split('T')[0];
				await page.locator('input[name="dateStart"]').fill(today);

				// Masthead — "Author" is not a reviewer role, so the
				// FieldSelect renders with show/hide options. Pick
				// "Appear on the masthead".
				await page
					.locator('select[name="masthead"]')
					.last()
					.selectOption({label: 'Appear on the masthead'});

				// Step 1 → Step 2 (email composer). The page's
				// `updateInvitation` POST creates the invitation row
				// (status=PENDING) and lets us pull the id later.
				await page
					.getByRole('button', {name: 'Save And Continue', exact: true})
					.click();

				// Email step. The mailable's body is auto-loaded from
				// the seeded UserRoleAssignmentInvitationNotify
				// template, so we don't need to set anything in
				// TinyMCE — submit drives `invitations/{id}/invite`
				// which dispatches the email.
				await expect(
					page.getByRole('button', {
						name: 'Invite user to the role',
						exact: true,
					}),
				).toBeVisible();
				await page
					.getByRole('button', {name: 'Invite user to the role', exact: true})
					.click();

				// Success dialog — reka-ui PkpDialog with the
				// userInvitation.modal.title heading.
				const sentDialog = page.getByRole('dialog', {name: 'Invitation Sent'});
				await expect(sentDialog).toBeVisible({timeout: 15_000});
				await expect(sentDialog).toContainText('phudson@mailinator.com');

				// REST sanity — the journal-scoped invitations
				// endpoint should now list one PENDING userRoleAssignment
				// invitation for phudson. We piggy-back dbarnes's
				// authenticated browser context for the GET so we
				// inherit the session cookie + CSRF surface.
				const apiRes = await page.request.get(
					`/index.php/${context.path}/api/v1/invitations/userRoleAssignment`,
				);
				expect(apiRes.ok()).toBeTruthy();
				const body = await apiRes.json();
				expect(Array.isArray(body.items)).toBeTruthy();
				const phudsonInvite = body.items.find(
					(i) => i.existingUser?.email === 'phudson@mailinator.com',
				);
				expect(phudsonInvite, 'pending invitation row for phudson').toBeTruthy();
				expect(phudsonInvite.status).toBe('PENDING');

				// And one of the userGroupsToAdd entries is the
				// "Author" role we just picked — the resource serializes
				// userGroupName per locale.
				const userGroupNames = (phudsonInvite.userGroupsToAdd || []).map(
					(g) => g.userGroupName,
				);
				expect(
					userGroupNames.some((name) => /author/i.test(String(name))),
					`userGroupsToAdd contains Author (got ${JSON.stringify(userGroupNames)})`,
				).toBeTruthy();

				// User-side: the Users tab's Invitations panel mirrors
				// the same pending row. Re-navigating to the access
				// page (the dialog has a "View All Users" CTA but
				// asserting on the URL is more robust than racing the
				// dialog click) and checking the Invitations table
				// header count flips from 0 to 1.
				await page.goto(
					`/index.php/${context.path}/management/settings/access`,
				);
				await expect(
					page.getByRole('heading', {name: /^Invitations \(1\)$/}),
				).toBeVisible({timeout: 15_000});
			} finally {
				await ctx.close();
			}
		},
	);
});

function uniqueTag() {
	const workerIndex = test.info().parallelIndex;
	const suffix = Math.random().toString(36).slice(2, 8);
	return `r60-w${workerIndex}-${suffix}`;
}

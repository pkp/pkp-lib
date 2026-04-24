// @ts-check
const {test} = require('../../../../playwright/support/fixtures.js');

/**
 * Row #43 — Author edit-published permission — DEFERRED.
 *
 * Cypress source: AmwandengaSubmission.cy.js tests 3–5 drive three
 * assertions around an author-only user named `amwandenga` (registered
 * inside test 1 via `cy.register`):
 *
 *   1. After the editor publishes, the author's workflow view has a
 *      disabled Save on Title & Abstract, no Add Contributor, no
 *      galley management controls (Repo::submission()->canEditPublication
 *      returns false for an AUTHOR-only user on a published
 *      publication).
 *   2. When the editor toggles `canChangeMetadata=true` on the
 *      author's StageAssignment, the same author sees an enabled
 *      Save and saves land successfully.
 *
 * Blocked: the Playwright baseline has no functional author-only user.
 * `lib/pkp/playwright/data/users.js` lists sixteen seeded users —
 * admin + fifteen publicknowledge roles (manager / editor /
 * sectionEditor / reviewer / copyeditor / layoutEditor / proofreader).
 * There is no user with only the author role. The closest candidate,
 * `rvaca`, has `mustChangePassword: true` so login redirects to the
 * password-change form before reaching any workflow page.
 *
 * Why a dbarnes-as-submitter substitution does NOT work: the author
 * gate in Repo::submission()->canEditPublication is a per-user check
 * that bypasses stage-assignment evaluation whenever the user holds
 * a manager/editor role in the context
 * (_canUserAccessUnassignedSubmissions via
 * UserGroup::NOT_CHANGE_METADATA_EDIT_PERMISSION_ROLES). dbarnes is
 * a journal editor and always passes that bypass — his
 * author-side stage assignment is never consulted. Substituting
 * dbarnes as the submitter would give a green test that proves
 * nothing about the author gate.
 *
 * Reopen when either:
 *   (a) A baseline "author-only" user without mustChangePassword is
 *       added (cross-cutting change, not this row — cf. the row #12
 *       note in docs/e2e-playwright-migration.md).
 *   (b) A scenario-level user-seeding extension lands (similar in
 *       shape to the participant-processor extension from row #22)
 *       that lets a spec register a per-test author user without
 *       mutating the baseline.
 *
 * This file is kept as a deferred placeholder so `grep`-driven
 * discovery finds the row; the assertions live here ready for the
 * baseline change.
 */

test.describe('Author edit-published permission', () => {
	test.skip('author cannot edit metadata on a published submission by default', async () => {
		// Blocked on a non-mustChangePassword author-only baseline user.
		// See top-of-file DEFERRED note.
	});

	test.skip('author can edit metadata when editor grants canChangeMetadata on their stage assignment', async () => {
		// Blocked on the same baseline gap. Toggle UI is
		// ParticipantManager row "Edit" → canChangeMetadata checkbox
		// (same form as row #41 / #42 drive declaratively via
		// ParticipantProcessor). The gate itself is tested in row #42
		// for a section editor; an author-scoped equivalent is
		// impossible without an author-only login.
	});
});

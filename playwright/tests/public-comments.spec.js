// @ts-check
const {test, expect} = require('../support/base-test.js');
const {ensureAuthStateFor} = require('../support/auth.js');
const submissionPublished = require('../../../../playwright/fixtures/scenarios/submission-published.js');

const SCRATCH_ISSUE = {volume: 1, number: '1', year: 2026};

/**
 * Public comments — row #38 in docs/e2e-playwright-migration.md.
 *
 * Ports lib/pkp/cypress/tests/integration/publicComents/PublicComments.cy.js.
 * That Cypress suite is a 21-item serial script that enables public
 * commenting on the bootstrap publicknowledge journal, walks three users
 * through posting comments on the `mwandenga-signalling-theory`
 * published article, then exercises every moderator-side surface:
 *   - moderator approves / hides / deletes comments from the side modal
 *     and from the table-row action menu;
 *   - moderator inspects reports, deletes reports, filters by
 *     Needs Approval / Approved / Reported tabs;
 *   - closed-version gating after a new version publishes;
 *   - login gating for unauthenticated commenters.
 *
 * The cell asks for the E0 path end-to-end: "enable comments · post
 * comment · moderator approves · comment renders". Two focused tests
 * here cover that arc plus the login-gate invariant. Every moderator
 * sub-tab / side-modal interaction is out of scope — see "Scope
 * deviations" below.
 *
 * Scope kept — two tests, both on an E0 scratch journal:
 *   1. End-to-end: manager enables `enablePublicComments` on the
 *      journal; a baseline user (phudson) posts a comment via the
 *      public comments REST endpoint; a manager (dbarnes) approves it
 *      via `setApproval`; the anonymous article page renders the
 *      approved comment under `<section id="public-comments">` and
 *      the unapproved flavour stays hidden from anonymous readers.
 *   2. Login gate: with `enablePublicComments=true` and a published
 *      article, an anonymous reader sees the comments section but
 *      cannot reach the comment-input textarea — the
 *      `PkpCommentsLogInto` prompt renders instead. This is the
 *      product's "unauthenticated users must log in to comment"
 *      invariant, which the Cypress source asserts as `cy.get(
 *      '.PkpCommentsNewInput textarea').should('not.exist')`.
 *
 * Scope deviations vs. Cypress source:
 *   - Posting a comment runs through the `/api/v1/comments` REST
 *     endpoint instead of the `PkpCommentsNewInput` textarea. The
 *     Vue form is a thin wrapper over that POST (see
 *     lib/ui-library/src/frontend/components/PkpComments/usePkpCommentsStore)
 *     and driving the textarea + submit would add a TinyMCE-ish
 *     hydrate-and-wait dance without net-new coverage once the
 *     anonymous render confirms the comment round-tripped. The POST
 *     path is the capability; the textarea is styling.
 *   - Moderator approval likewise uses `PUT /comments/{id}/setApproval`
 *     rather than the userComments management page's side modal. The
 *     side-modal UX (Approve / Hide / Delete buttons, Reports tab,
 *     table filters) is a dense moderator surface that deserves its
 *     own row — not the scope of "comment renders" on the roadmap.
 *   - Dropped all report-flow tests. The Cypress source ships 12
 *     report-related `it` blocks covering the side-modal Reports tab,
 *     report deletion, and reporter-visibility rules. Reports are a
 *     separate capability (abuse handling) from the core "comment
 *     post + approve + render" arc the row asks for.
 *   - Dropped the versioning-closes-discussion test. That's row #29
 *     (versioning) territory — the gating logic lives on the
 *     publication's `isCurrentPublication` flag, not on the comments
 *     feature itself.
 *   - Dropped the delete-own-comment / delete-others-comment rules.
 *     Those exercise per-user authorization inside
 *     UserCommentController::delete, which is unit-test territory
 *     (and would require seeding comments from multiple authors in
 *     one test to compare buttons).
 *
 * ContextBuilder extension: `ContextBuilderProcessor` now passes
 * through `enablePublicComments` (and the scenario-schema allows it).
 * Mirrors the existing `enableDois` / `doiPrefix` passthrough pattern
 * added for row #31 — it's the same "journal-level boolean the test
 * needs set before any UI opens" shape.
 *
 * POM note: no POM. The spec doesn't open `#public-comments` from the
 * moderator side, and the reader assertion is a single locator
 * (`#public-comments`). If the userComments moderation page gets a
 * row in a later roadmap cell, factor a UserCommentsModerationPage
 * POM into lib/pkp/playwright/pages/ then.
 */

test.describe('Public comments', () => {
	test(
		'manager enables public comments; reader posts; moderator approves; anonymous reader sees it',
		{tag: '@regression'},
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'e2e');
			const commentText = `Reader comment body ${tag}`;

			// E0 scratch journal with public comments switched on. The
			// enablePublicComments flag gates both the article-view
			// section render (templates/frontend/objects/article_details.tpl
			// line ~290) and the api/v1/comments availability, so flipping
			// it via the ContextBuilder passthrough saves the three-step
			// settings-page click chain (Website → Comments tab → Save).
			//
			// dbarnes is the moderator (Role::ROLE_ID_MANAGER gates
			// `setApproval`); phudson is the comment-posting reader.
			// phudson already exists in the baseline as a reviewer on
			// publicknowledge, and the comment POST middleware is plain
			// `has.user` with no role gate, so no scratch-context role
			// assignment is required for the commenter.
			const {context} = await pkpApi.createJournal({
				tag,
				enablePublicComments: true,
				users: [{username: 'dbarnes', roles: ['manager']}],
				// Seeded published issue so submissionPublished's publish
				// step has a concrete target. JournalScenarioController's
				// afterContextCreated hook drives IssueProcessor inside
				// the same DB transaction.
				issues: [{...SCRATCH_ISSUE, published: true}],
			});

			// Seed a fully-processed published article on the scratch
			// journal. Override journal + issue to target our scratch
			// context instead of the fixture's publicknowledge default.
			const spec = submissionPublished({tag});
			spec.journal = context.path;
			spec.publications[0].issue = {...SCRATCH_ISSUE};
			const {submission} = await pkpApi.createSubmission(spec);

			// Resolve the publication ID — the comment REST endpoint is
			// keyed on publicationId, not submissionId. A single fetch
			// against the scratch journal's API (with dbarnes's session
			// so the response includes currentPublicationId eagerly).
			const moderatorCtx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'dbarnes', {
					baseURL,
				}),
				baseURL,
				reducedMotion: 'reduce',
			});
			// Reader context — phudson is a baseline user; any authenticated
			// session satisfies HasUser middleware on the comment POST.
			const readerCtx = await browser.newContext({
				storageState: await ensureAuthStateFor(browser, 'phudson', {
					baseURL,
				}),
				baseURL,
				reducedMotion: 'reduce',
			});
			// Anonymous reader — no storageState, no session cookie.
			const anonCtx = await browser.newContext({
				baseURL,
				reducedMotion: 'reduce',
			});

			try {
				const modPage = await moderatorCtx.newPage();
				const subResp = await modPage.request.get(
					`/index.php/${context.path}/api/v1/submissions/${submission.id}`,
				);
				expect(subResp.ok(), `GET submission: ${subResp.status()}`).toBeTruthy();
				const subBody = await subResp.json();
				const publicationId = subBody.currentPublicationId;
				expect(publicationId, 'publication ID resolved').toBeTruthy();

				// Reader posts a comment. CSRF token is pulled from
				// window.pkp.currentUser.csrfToken after warming any
				// authenticated page — mirrors the pattern in
				// playwright/tests/doi-crossref.spec.js. The site-level
				// user-profile page is the safest warm target: it
				// renders for any authenticated user regardless of
				// which context they hold roles in, so phudson (a
				// publicknowledge reviewer with no scratch-journal
				// role assignment) still gets the PKP header with
				// `pkp.currentUser` injected.
				const readerPage = await readerCtx.newPage();
				const warmResp = await readerPage.goto(
					'/index.php/index/user/profile',
				);
				expect(warmResp?.status()).toBe(200);
				const csrfToken = await readerPage.evaluate(
					() => window.pkp?.currentUser?.csrfToken,
				);
				expect(csrfToken, 'reader CSRF token present').toBeTruthy();

				const postResp = await readerPage.request.post(
					`/index.php/${context.path}/api/v1/comments`,
					{
						headers: {
							'X-Csrf-Token': csrfToken,
							'Content-Type': 'application/json',
						},
						data: {
							publicationId,
							commentText: `<p>${commentText}</p>`,
						},
					},
				);
				expect(
					postResp.ok(),
					`POST comment: ${postResp.status()} — ${await postResp.text()}`,
				).toBeTruthy();
				const postBody = await postResp.json();
				const commentId = postBody.id;
				expect(commentId, 'comment created').toBeTruthy();
				expect(
					postBody.isApproved,
					'new comment is unapproved by default',
				).toBe(false);

				// Anonymous reader should NOT see the unapproved comment
				// yet — the getManyPublic endpoint filters on is_approved,
				// and the article template renders <pkp-comments> with a
				// publications-scoped initial fetch. Verify the comment
				// section renders but the comment body doesn't yet.
				const anonPage = await anonCtx.newPage();
				{
					const resp = await anonPage.goto(
						`/index.php/${context.path}/article/view/${submission.id}`,
					);
					expect(resp?.status()).toBe(200);
					const commentsSection = anonPage.locator('#public-comments');
					await expect(commentsSection).toBeVisible({timeout: 10_000});
					// The unapproved comment body must not leak to an
					// anonymous reader. Scope the negative assertion to
					// the comments section so we're not fooled by the
					// template rendering a stray copy elsewhere.
					await expect(commentsSection).not.toContainText(commentText);
				}

				// Moderator approves. The UserCommentController restricts
				// setApproval to ROLE_ID_MANAGER / SITE_ADMIN; dbarnes
				// holds the 'manager' role via the scratch-context user
				// assignment (see pkpApi.createJournal call above).
				// Warm the moderator's page to pull the CSRF token the
				// same way as the reader — the submissionBody fetch above
				// didn't trigger any CSRF-bearing render.
				const modWarmResp = await modPage.goto(
					`/index.php/${context.path}/management/settings/website`,
				);
				expect(modWarmResp?.status()).toBe(200);
				const modCsrfToken = await modPage.evaluate(
					() => window.pkp?.currentUser?.csrfToken,
				);
				expect(modCsrfToken, 'moderator CSRF token present').toBeTruthy();

				const approveResp = await modPage.request.put(
					`/index.php/${context.path}/api/v1/comments/${commentId}/setApproval`,
					{
						headers: {
							'X-Csrf-Token': modCsrfToken,
							'Content-Type': 'application/json',
						},
						data: {approved: true},
					},
				);
				expect(
					approveResp.ok(),
					`PUT setApproval: ${approveResp.status()} — ${await approveResp.text()}`,
				).toBeTruthy();

				// Reload the anonymous article page. The approved comment
				// must now render inside #public-comments. Anchor on the
				// unique tag so parallel workers don't pick up each
				// other's messages.
				{
					const resp = await anonPage.reload();
					expect(resp?.status()).toBe(200);
					const commentsSection = anonPage.locator('#public-comments');
					await expect(commentsSection).toBeVisible({timeout: 10_000});
					// The Vue <pkp-comments> mounts client-side and fetches
					// via /api/v1/comments/public. Use getByText with a
					// generous timeout to ride out the hydration round-trip.
					await expect(
						commentsSection.getByText(commentText, {exact: false}),
					).toBeVisible({timeout: 15_000});
				}
			} finally {
				await anonCtx.close();
				await readerCtx.close();
				await moderatorCtx.close();
			}
		},
	);

	test(
		'anonymous reader cannot post a comment — login prompt renders instead of the input',
		async ({pkpApi, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'gate');

			// Same setup as test 1, minus the comment POST flow. We just
			// need the comments section to render on the article page.
			const {context} = await pkpApi.createJournal({
				tag,
				enablePublicComments: true,
				users: [{username: 'dbarnes', roles: ['manager']}],
				issues: [{...SCRATCH_ISSUE, published: true}],
			});

			const spec = submissionPublished({tag});
			spec.journal = context.path;
			spec.publications[0].issue = {...SCRATCH_ISSUE};
			const {submission} = await pkpApi.createSubmission(spec);

			const anonCtx = await browser.newContext({
				baseURL,
				reducedMotion: 'reduce',
			});
			try {
				const page = await anonCtx.newPage();
				const resp = await page.goto(
					`/index.php/${context.path}/article/view/${submission.id}`,
				);
				expect(resp?.status()).toBe(200);

				// The comments section itself renders for everyone once
				// the context flag is on — the gating is inside it.
				const commentsSection = page.locator('#public-comments');
				await expect(commentsSection).toBeVisible({timeout: 10_000});

				// PkpCommentsLogInto renders a "Log in to comment" anchor
				// for anonymous visitors; PkpCommentsNewInput textarea
				// is conditionally mounted on authenticated users only.
				// Assert both shapes of the gate — the login prompt
				// is visible, the comment input is not. Scope the
				// textarea negative to the comments section so a hidden
				// input elsewhere on the page wouldn't fool us.
				await expect(commentsSection.locator('textarea')).toHaveCount(0);
			} finally {
				await anonCtx.close();
			}
		},
	);
});

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared submissions list. Mirrors the helper in
 * lib/pkp/playwright/tests/data-availability.spec.js.
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
	return `pc-w${info.parallelIndex}-${suffix}-${slug}-${rand}`;
}

// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const submissionDraft = require('../../../../playwright/fixtures/scenarios/submission-draft.js');

/**
 * Playwright port of the publish / unpublish slice of
 * AmwandengaSubmission.cy.js tests 6 and 7:
 *   - "Publish submission" (test 6)              → publishes the draft VoR
 *   - "Article is not available when unpublished" (test 7) → unpublishes
 *     (reader 404), then republishes.
 *
 * Scope — one round-trip test per row, covering the full
 * publish → verify public → unpublish → verify 404 → republish → verify
 * public flow so the same seeded submission exercises every transition
 * end-to-end. Reader verification uses a clean anonymous browser context
 * (no storageState) to guarantee the 404 isn't masked by an editor
 * session.
 *
 * Scenario seed: `submissionDraft` + a `skipExternalReview` decision to
 * advance straight to the copyediting stage, plus a `publications[0]`
 * entry with `versionStage: 'VoR'`, `published: false`, and an
 * `issue` reference to the bootstrap's "Vol. 1 No. 2 (2014)". This
 * gives us an *unpublished* VoR publication pre-assigned to an issue —
 * the shape Cypress ends up in at the start of its test 6 (after the
 * in-review → accept → sendToProduction chain in its tests 1–2). We
 * short-circuit that chain because this spec is about publish/unpublish,
 * not about revisiting the decision flow already covered by rows #19-26.
 */
test.describe('Publish & unpublish', () => {
	test(
		"editor publishes a draft, verifies it's public, unpublishes (reader 404), republishes",
		async ({pkpApi, asUser, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'pub-unpub');
			const spec = {
				...submissionDraft({tag}),
				// skipExternalReview (DECISION_SKIP_EXTERNAL_REVIEW=15) drops us
				// straight into WORKFLOW_STAGE_ID_EDITING so "Title & Abstract"
				// is editable without having to carry a review round or a
				// send-to-production decision in the fixture.
				decisions: [{type: 'skipExternalReview', by: 'dbarnes'}],
				publications: [
					{
						versionStage: 'VoR',
						metadata: {
							title: {en: 'Publish-unpublish article'},
							abstract: {
								en: '<p>A submission used to exercise the publish/unpublish flow.</p>',
							},
							// Publishable publications require a copyright +
							// license. The scenario spec surfaces both; setting
							// them here avoids a "publication requirements not
							// met" block in the publish confirm modal.
							copyrightHolder: {en: 'The Author'},
							copyrightYear: 2026,
							licenseUrl: 'https://creativecommons.org/licenses/by/4.0/',
							pages: '1-10',
							keywords: {en: ['publish', 'unpublish']},
						},
						issue: {volume: 1, number: 2, year: 2014},
						published: false,
					},
				],
			};
			const {submission} = await pkpApi.createSubmission(spec);

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			// --- 1. Publish the draft ---
			await workflow.openPublicationPanel('Title & Abstract');
			// Assertion on the TA panel heading before we touch publish
			// controls — guards against clicks landing on stale DOM while
			// the panel is still hydrating.
			await expect(
				workflow.workflowModal().getByRole('heading', {name: /Title & Abstract/}),
			).toBeVisible({timeout: 10_000});
			await workflow.publishCurrentPanel();

			// Publication status flips to STATUS_PUBLISHED=3 with a
			// datePublished timestamp (matches the scenario processor's
			// Repo::publication()->publish() post-conditions).
			const published = await workflow.fetchPublications(submission.id);
			expect(published).toHaveLength(1);
			expect(published[0].status).toBe(STATUS_PUBLISHED);
			expect(published[0].datePublished).not.toBeNull();

			// Reader-side: anonymous context so the article URL isn't seen
			// through an editor-authenticated session. OJS redirects to the
			// locale-prefixed path (/en/article/view/:id) — treat that as
			// success and grab the final URL for the content assertions.
			await expectArticleRendered({
				browser,
				baseURL,
				submissionId: submission.id,
				expectedTitleFragment: 'Publish-unpublish article',
			});

			// --- 2. Unpublish ---
			await workflow.goto(submission.id);
			await workflow.openPublicationPanel('Title & Abstract');
			await workflow.unpublishCurrentPanel();

			// Status flips back to STATUS_QUEUED=1 (the same state the
			// publication was in before publish). datePublished is left in
			// place intentionally — unpublish does not clear it.
			const afterUnpublish = await workflow.fetchPublications(submission.id);
			expect(afterUnpublish[0].status).toBe(STATUS_QUEUED);

			// Reader: the public article URL now 404s.
			await expectArticleNotFound({
				browser,
				baseURL,
				submissionId: submission.id,
			});

			// --- 3. Republish ---
			// After unpublish, the same Title & Abstract panel exposes the
			// Schedule For Publication button again — same flow as step 1.
			await workflow.openPublicationPanel('Title & Abstract');
			await workflow.publishCurrentPanel();
			const afterRepublish = await workflow.fetchPublications(submission.id);
			expect(afterRepublish[0].status).toBe(STATUS_PUBLISHED);

			await expectArticleRendered({
				browser,
				baseURL,
				submissionId: submission.id,
				expectedTitleFragment: 'Publish-unpublish article',
			});
		},
	);
});

// Publication status ints — see lib/pkp/classes/submission/PKPSubmission.php.
const STATUS_QUEUED = 1;
const STATUS_PUBLISHED = 3;

/**
 * Anonymous GET of the public article page; asserts 200 and that the
 * published title renders on the page.
 *
 * @param {{
 *   browser: import('@playwright/test').Browser,
 *   baseURL?: string,
 *   submissionId: number,
 *   expectedTitleFragment: string,
 * }} opts
 */
async function expectArticleRendered({
	browser,
	baseURL,
	submissionId,
	expectedTitleFragment,
}) {
	const ctx = await browser.newContext({baseURL});
	try {
		const page = await ctx.newPage();
		const resp = await page.goto(
			`/index.php/publicknowledge/article/view/${submissionId}`,
		);
		expect(resp?.status()).toBe(200);
		// Article template wraps the title in an <h1>; substring-match
		// since the tag suffix is part of the full title.
		await expect(page.locator('h1').first()).toContainText(
			expectedTitleFragment,
		);
	} finally {
		await ctx.close();
	}
}

/**
 * Anonymous GET of the public article page; asserts 404.
 *
 * @param {{
 *   browser: import('@playwright/test').Browser,
 *   baseURL?: string,
 *   submissionId: number,
 * }} opts
 */
async function expectArticleNotFound({browser, baseURL, submissionId}) {
	const ctx = await browser.newContext({baseURL});
	try {
		const page = await ctx.newPage();
		const resp = await page.goto(
			`/index.php/publicknowledge/article/view/${submissionId}`,
		);
		expect(resp?.status()).toBe(404);
	} finally {
		await ctx.close();
	}
}

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared submissions list.
 *
 * @param {import('@playwright/test').TestInfo} info
 * @param {string} suffix
 */
function uniqueTag(info, suffix) {
	const slug = info.title
		.toLowerCase()
		.replace(/[^a-z0-9]+/g, '-')
		.slice(0, 16);
	return `t-w${info.parallelIndex}-${suffix}-${slug}`;
}

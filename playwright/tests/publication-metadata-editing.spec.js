// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const {setTinyMceContent} = require('../support/tinymce.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Playwright port of the publication-metadata-editing slice of
 * AmwandengaSubmission.cy.js test 3 ("Editor can edit publication
 * details"). The Cypress source drives a full tour of Title & Abstract,
 * Metadata, Permissions, Issue, Contributors, Galleys — we reduce that
 * to the smallest set that proves the Publication panel persists edits
 * round-trip:
 *   - Title & Abstract: title (TinyMCE) + abstract (TinyMCE)
 *   - Metadata:         keywords (autosuggest)
 *
 * Scope deviation — scenario seed:
 *   The roadmap suggests `submissionPublished({tag})` so the R portion
 *   can walk the public article page. Probing that confirmed the UI
 *   surfaces a `WorkflowPublicationEditWarning` on a published publication
 *   (status=STATUS_PUBLISHED) — editing is blocked without creating a
 *   new version. Rather than entangle this spec with versioning UX
 *   (which belongs to row #29), we seed `submissionInReview({tag})`
 *   — its draft-state publication (status=STATUS_QUEUED, versionStage=AO,
 *   "Author Original 1.0") accepts edits directly.
 *
 * Scope deviation — reader verification:
 *   R ("updated values appear on article page") is dropped here because
 *   the draft publication has no public URL. The publish/unpublish flow
 *   and reader verification live in row #28 (publish-unpublish).
 *
 * Scope deviation — contributors:
 *   The roadmap's optional contributors test is dropped per the
 *   3-attempt budget — title/abstract/keywords together already exercise
 *   the two input idioms (TinyMCE + autosuggest) and the shared
 *   Save → [role=status] "Saved" round-trip, so a contributors variant
 *   adds dialog-plumbing coverage rather than net-new publication-panel
 *   coverage.
 */
test.describe('Publication metadata editing', () => {
	test('editor updates publication title, abstract, and keywords; changes persist on reload', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag(test.info(), 'metadata');
		const spec = submissionInReview({tag});
		const {submission} = await pkpApi.createSubmission(spec);

		const newTitle = `Updated title ${tag}`;
		const newAbstract =
			`<p>Updated abstract for ${tag} — rewritten by the editor.</p>`;
		const newKeyword = `kw-${tag}`;

		const ctx = await asUser('dbarnes');
		const page = await ctx.newPage();
		const workflow = new EditorialWorkflowPage(page);
		await workflow.goto(submission.id);

		// The workflow page renders itself inside a reka-ui dialog tagged
		// `[data-cy="active-modal"]`. Its side-nav exposes the Publication
		// sub-items (Title & Abstract, Metadata, Contributors, …) as
		// anchors with href="#". The anchor text is padded with
		// whitespace, so use getByText with exact:true — filter's
		// hasText applies against the raw textContent and mismatches.
		const modal = page.locator('[data-cy="active-modal"]');
		const sideNav = modal.locator('nav a');

		// --- Title & Abstract ---
		await sideNav.getByText('Title & Abstract', {exact: true}).first().click();
		// The heading `Publication:  Title & Abstract` confirms the panel
		// mounted before we start touching TinyMCE editors (which rely on
		// their initialization hook for setContent to land).
		await expect(
			modal.getByRole('heading', {name: /Title & Abstract/}),
		).toBeVisible({timeout: 10_000});

		await setTinyMceContent(page, 'titleAbstract-title-control-en', newTitle);
		await setTinyMceContent(
			page,
			'titleAbstract-abstract-control-en',
			newAbstract,
		);

		await modal.getByRole('button', {name: 'Save', exact: true}).click();
		// The Vue form toasts via [role="status"] containing "Saved" on
		// success (Cypress used the same assertion — see
		// AmwandengaSubmission.cy.js#307).
		await expect(page.locator('[role="status"]').filter({hasText: 'Saved'}))
			.toBeVisible({timeout: 15_000});

		// --- Metadata (keywords) ---
		await sideNav.getByText('Metadata', {exact: true}).first().click();
		await expect(modal.getByRole('heading', {name: /Metadata/})).toBeVisible({
			timeout: 10_000,
		});

		// Keyword FieldControlledVocab autosuggest: type + Enter to commit
		// a tag. Matches the Cypress flow in AmwandengaSubmission.cy.js#313.
		const keywordInput = modal.locator('#metadata-keywords-control-en');
		await keywordInput.fill(newKeyword);
		await keywordInput.press('Enter');
		// Per-keyword chips render as "Remove <keyword>" buttons in the
		// selected list; asserting the one for our new keyword is a
		// clean ready signal before we save.
		await expect(
			modal.getByRole('button', {name: `Remove ${newKeyword}`}),
		).toBeVisible({timeout: 10_000});

		await modal.getByRole('button', {name: 'Save', exact: true}).click();
		await expect(page.locator('[role="status"]').filter({hasText: 'Saved'}))
			.toBeVisible({timeout: 15_000});

		// --- Reload & verify persistence ---
		// The publication API is the source of truth — fetching it
		// confirms the edits landed independently of any UI caching.
		const pub = await fetchCurrentPublication(page, submission.id);
		expect(pub.fullTitle?.en || pub.title?.en).toContain(newTitle);
		// Abstract round-trips as HTML; the added sentence is the
		// stable substring to assert on.
		expect(pub.abstract?.en || '').toContain(`Updated abstract for ${tag}`);
		// Keywords round-trip as {name: string} objects on the publication
		// payload (see ControlledVocab::asShallowArray → nameTransformer).
		const keywordNames = (pub.keywords?.en || []).map((k) =>
			typeof k === 'string' ? k : k?.name,
		);
		expect(keywordNames).toContain(newKeyword);

		// And — separately — the workflow page itself re-renders the new
		// title after a full page reload (guards against any stale
		// in-memory state vs what the API returns). The title appears
		// as a sub-header beneath the submitter name; plain-text match
		// is enough since the tag makes it globally unique on the page.
		await page.reload();
		await expect(page.getByText(newTitle).first()).toBeVisible({
			timeout: 15_000,
		});
	});
});

/**
 * Fetch the submission's current publication JSON via the REST API.
 * OJS exposes publications under the submission resource; we pull the
 * submission first to get the current publication id, then fetch the
 * publication itself so we see the full metadata (title, abstract,
 * keywords) in the shape the form writes to.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} submissionId
 */
async function fetchCurrentPublication(page, submissionId) {
	const subRes = await page.request.get(
		`/index.php/publicknowledge/api/v1/submissions/${submissionId}`,
	);
	if (!subRes.ok()) {
		throw new Error(
			`GET submission ${submissionId} failed: ${subRes.status()} ${await subRes.text()}`,
		);
	}
	const sub = await subRes.json();
	const pubRes = await page.request.get(
		`/index.php/publicknowledge/api/v1/submissions/${submissionId}/publications/${sub.currentPublicationId}`,
	);
	if (!pubRes.ok()) {
		throw new Error(
			`GET publication ${sub.currentPublicationId} failed: ${pubRes.status()} ${await pubRes.text()}`,
		);
	}
	return pubRes.json();
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

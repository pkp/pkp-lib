// @ts-check
const {test, expect} = require('../../../../playwright/support/fixtures.js');
const {EditorialWorkflowPage} = require('../../../../playwright/pages/EditorialWorkflowPage.js');
const {setTinyMceContent} = require('../support/tinymce.js');
const submissionPublished = require('../../../../playwright/fixtures/scenarios/submission-published.js');

/**
 * Playwright port of the versioning slice of AmwandengaSubmission.cy.js
 * tests 8–10:
 *   - "Editor must create version to make changes"              (test 8)
 *   - "Article landing page displays versions at correct url"   (test 9)
 *   - "Article landing page displays correct version after v2
 *      unpublish"                                               (test 10)
 *
 * Scope — one test covers the full lifecycle on a single seeded
 * submission:
 *   1. v1 is seeded as published via `submissionPublished({tag})`.
 *   2. Editor creates v2 via "Create New Version".
 *   3. Editor edits v2's title.
 *   4. Editor publishes v2.
 *   5. Reader: article page defaults to v2; v1 is reachable via the
 *      `.versions` picker list; visiting v1 shows the outdated-version
 *      notice.
 *   6. Editor unpublishes v2.
 *   7. Reader: article now shows v1 again; v2 is no longer in the
 *      version picker.
 *
 * Two tests in this file:
 *   1. Versioning lifecycle (above): create v2, edit title, publish,
 *      reader sees v2 + can reach v1, unpublish v2, reader sees v1.
 *   2. v2-aware contributor add: on a freshly-created v2, drive the
 *      Contributors panel's Add Contributor flow, assert the new
 *      author lands in v2's authors collection (and NOT v1's). Mirrors
 *      row #27's Contributors pattern but proves the version-scoped
 *      contributor pipeline binds to v2 specifically.
 *
 * Scope deviation — Cypress also drives v2 galley + v2 issue panel
 * edits. Galley management is its own row (#51) and shares the same
 * post-version-create save infrastructure as Contributors. The Issue
 * panel's stacked-form Save resists scoping under the dashboard
 * dialog (see row #27's deferred verdict for the same Vue widget
 * surface); deferred here too. The pkpForm save infrastructure on v2
 * is proven by test 1's title edit + test 2's contributor add.
 *
 * Scope deviation — Cypress uses a URL path (`/article/view/mwandenga`)
 * to exercise urlPath routing. We use the numeric submissionId URL
 * because (a) urlPath is a per-publication field that Cypress sets as
 * part of the v2 issue-edit flow we dropped, and (b) the numeric route
 * is the canonical one — it's what every other reader-side assertion
 * in this migration uses.
 */
test.describe('Versioning', () => {
	test(
		'editor creates v2, edits it, publishes it; reader sees v2 by default and can reach v1; unpublishing v2 hides it from the version picker',
		async ({pkpApi, asUser, browser, baseURL}) => {
			const tag = uniqueTag(test.info(), 'versioning');
			const spec = submissionPublished({tag});
			const {submission, publications} = await pkpApi.createSubmission(spec);
			expect(publications).toHaveLength(1);
			const v1PublicationId = publications[0].id;

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			// --- 1. Create v2 ---
			await workflow.createNewVersion({
				versionStage: 'VoR',
				versionIsMinor: 'true',
			});

			// The Vue side-nav caches the publications list, so the v1.1
			// entry only appears after a full page reload (see POM note on
			// createNewVersion). Reload, then the nav shows both versions.
			await workflow.goto(submission.id);
			const modal = workflow.workflowModal();
			await expect(
				modal.locator('nav').getByText('Version of Record 1.1'),
			).toBeVisible({timeout: 10_000});

			// Two publications now — v1 published, v2 draft.
			const afterCreate = await workflow.fetchPublications(submission.id);
			expect(afterCreate).toHaveLength(2);
			const v2Create = afterCreate.find((p) => p.id !== v1PublicationId);
			expect(v2Create).toBeDefined();
			expect(v2Create.versionMajor).toBe(1);
			expect(v2Create.versionMinor).toBe(1);
			expect(v2Create.status).toBe(STATUS_QUEUED);

			// --- 2. Edit v2's title ---
			// The side-nav renders v1's sub-items first, then v2's. Target
			// the second "Title & Abstract" anchor to land on v2.
			await workflow.openPublicationPanel('Title & Abstract', {
				version: 'last',
			});
			await expect(
				modal.getByRole('heading', {name: /Title & Abstract/}),
			).toBeVisible({timeout: 10_000});

			const v2Title = `V2 ${tag}`;
			await setTinyMceContent(page, 'titleAbstract-title-control-en', v2Title);
			await modal.getByRole('button', {name: 'Save', exact: true}).click();
			await expect(
				page.locator('[role="status"]').filter({hasText: 'Saved'}),
			).toBeVisible({timeout: 15_000});

			// --- 3. Publish v2 ---
			// The v2 TA panel exposes a plain "Publish" button (not
			// Schedule For Publication) because v1 already baselined the
			// issue assignment; the POM handles that label divergence.
			await workflow.publishCurrentPanel();

			const afterPublishV2 = await workflow.fetchPublications(submission.id);
			const v2After = afterPublishV2.find((p) => p.versionMinor === 1);
			expect(v2After.status).toBe(STATUS_PUBLISHED);
			expect(v2After.datePublished).not.toBeNull();

			// --- 4. Reader: default view renders v2, version picker
			//       surfaces v1 as a distinct link ---
			await expectReaderDefaultsToVersion({
				browser,
				baseURL,
				submissionId: submission.id,
				expectedTitleFragment: v2Title,
				expectedOtherVersionLabel: 'Version of Record 1.0',
				v1PublicationId,
			});

			// --- 5. Unpublish v2 ---
			// The workflow modal state may have drifted after publish (the
			// side-nav remounts to reflect the published v2). Reload, then
			// target v2's Title & Abstract panel again to click Unpublish.
			await workflow.goto(submission.id);
			await workflow.openPublicationPanel('Title & Abstract', {
				version: 'last',
			});
			await workflow.unpublishCurrentPanel();

			const afterUnpubV2 = await workflow.fetchPublications(submission.id);
			const v2Unpub = afterUnpubV2.find((p) => p.versionMinor === 1);
			expect(v2Unpub.status).toBe(STATUS_QUEUED);

			// --- 6. Reader: v1 is the only version in the picker; default
			//       view is v1's content again ---
			await expectReaderHasOnlyVersion({
				browser,
				baseURL,
				submissionId: submission.id,
				expectedTitleFragment: 'Published article',
				excludedTitleFragment: v2Title,
			});
		},
	);

	test(
		'editor adds a contributor on v2; v2 authors include the new row, v1 unchanged',
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'v2-contrib');
			const spec = submissionPublished({tag});
			const {submission, publications} = await pkpApi.createSubmission(spec);
			expect(publications).toHaveLength(1);
			const v1PublicationId = publications[0].id;

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			const workflow = new EditorialWorkflowPage(page);
			await workflow.goto(submission.id);

			// Create v2 (minor version — keeps the issue assignment so
			// no Schedule For Publication ceremony is needed at the
			// reader-side stage).
			await workflow.createNewVersion({
				versionStage: 'VoR',
				versionIsMinor: 'true',
			});
			await workflow.goto(submission.id);

			// Open the Contributors panel under v2 (pick the LAST nav
			// link with that label). The side-nav renders v1's
			// sub-items first, then v2's.
			await workflow.openPublicationPanel('Contributors', {
				version: 'last',
			});

			const givenName = `V2${tag.replace(/[^a-z0-9]/gi, '')}`;
			const familyName = `Contributor`;
			const email = `v2-contrib-${tag}@mailinator.com`.toLowerCase();

			// Mirrors row #27's Contributors test — Add Contributor →
			// fill form → tick Author role → Save (race with the
			// publication-scoped contributors POST).
			const contributorManager = page.locator(
				'[data-cy="contributor-manager"]',
			);
			await expect(contributorManager).toBeVisible({timeout: 15_000});
			await contributorManager
				.getByRole('button', {name: 'Add Contributor', exact: true})
				.click();

			const emailInput = page.locator('input[name="email"]').last();
			await expect(emailInput).toBeVisible({timeout: 15_000});
			await page.locator('input[name="givenName-en"]').last().fill(givenName);
			await page
				.locator('input[name="familyName-en"]')
				.last()
				.fill(familyName);
			await emailInput.fill(email);
			await page
				.locator('select[name="country"]')
				.last()
				.selectOption('CA');
			await page
				.locator('label', {hasText: 'Author'})
				.locator('input[type="checkbox"]')
				.first()
				.check({force: true});

			// Capture the actual contributor POST URL so we know which
			// publication the panel wrote to.
			const [contribResp] = await Promise.all([
				page.waitForResponse(
					(res) =>
						/\/api\/v1\/submissions\/\d+\/publications\/\d+\/contributors/.test(
							res.url(),
						) &&
						res.ok(),
					{timeout: 20_000},
				),
				page
					.getByRole('button', {name: 'Save', exact: true})
					.last()
					.click(),
			]);
			const postedUrl = contribResp.url();
			const postedPubId = Number(
				postedUrl.match(/\/publications\/(\d+)\/contributors/)?.[1],
			);
			expect(
				postedPubId,
				`extracted publicationId from POST URL ${postedUrl}`,
			).toBeTruthy();

			// The publication-list endpoint returns a thin authors
			// summary; fetch each publication individually for the
			// full authors array.
			const fetchPub = async (pubId) => {
				const r = await page.request.get(
					`/index.php/publicknowledge/api/v1/submissions/${submission.id}/publications/${pubId}`,
				);
				if (!r.ok()) {
					throw new Error(
						`GET publication ${pubId}: ${r.status()} ${await r.text()}`,
					);
				}
				return r.json();
			};
			const all = await workflow.fetchPublications(submission.id);
			expect(all).toHaveLength(2);
			const v2Id = all.find((p) => p.id !== v1PublicationId).id;
			const v1 = await fetchPub(v1PublicationId);
			const v2 = await fetchPub(v2Id);

			// Sanity: the panel should have written to v2's id.
			expect(postedPubId).toBe(v2Id);

			const findAuthor = (pub) =>
				(pub.authors || []).find(
					(a) => (a.email || '').toLowerCase() === email,
				);

			const v2Match = findAuthor(v2);
			const v1Match = findAuthor(v1);
			expect(
				v2Match,
				`v2 should contain the new author (${email}); ` +
					`posted to publicationId ${postedPubId}, ` +
					`v1=${v1PublicationId}, v2=${v2Id}; ` +
					`v1 has it: ${!!v1Match}, v2 has it: ${!!v2Match}`,
			).toBeTruthy();
			expect(v2Match.givenName?.en).toBe(givenName);
			expect(
				v1Match,
				`v1 must NOT contain the v2-only author`,
			).toBeFalsy();
		},
	);
});

// Publication status ints — see lib/pkp/classes/submission/PKPSubmission.php.
const STATUS_QUEUED = 1;
const STATUS_PUBLISHED = 3;

/**
 * Reader-side assertions for the dual-version state:
 *   - /article/view/:id returns 200, renders the v2 title
 *   - the `.versions` picker lists v1 as a link pointing to
 *     /article/view/:id/version/:v1PublicationId
 *   - following that link loads v1's content and shows the "outdated
 *     version" notice.
 *
 * The `.versions` list renders the CURRENT version as plain text and
 * older versions as <a> tags; that shape is the feature we're asserting
 * (picker exists + older version is reachable), not a visual design.
 *
 * @param {{
 *   browser: import('@playwright/test').Browser,
 *   baseURL?: string,
 *   submissionId: number,
 *   expectedTitleFragment: string,
 *   expectedOtherVersionLabel: string,
 *   v1PublicationId: number,
 * }} opts
 */
async function expectReaderDefaultsToVersion({
	browser,
	baseURL,
	submissionId,
	expectedTitleFragment,
	expectedOtherVersionLabel,
	v1PublicationId,
}) {
	const ctx = await browser.newContext({baseURL});	const page = await ctx.newPage();
	const resp = await page.goto(
		`/index.php/publicknowledge/article/view/${submissionId}`,
	);
	expect(resp?.status()).toBe(200);
	await expect(page.locator('h1').first()).toContainText(
		expectedTitleFragment,
	);

	// Version picker — v2 is the current version (no link), v1 renders
	// as a link in the .versions list. The label always includes
	// "(Version of Record 1.0)".
	const versions = page.locator('.versions');
	await expect(versions).toBeVisible({timeout: 5_000});
	const olderLink = versions.locator(
		`a[href*="/version/${v1PublicationId}"]`,
	);
	await expect(olderLink).toHaveCount(1);
	await expect(olderLink).toContainText(expectedOtherVersionLabel);

	// Follow the v1 link; page should render v1 content with an
	// outdated-version notice.
	await olderLink.click();
	await page.waitForURL(new RegExp(`/version/${v1PublicationId}($|\\?)`), {
		timeout: 10_000,
	});
	await expect(page.locator('h1').first()).toContainText(
		'Published article',
	);
	await expect(page.getByText(/outdated version/i).first()).toBeVisible({
		timeout: 5_000,
	});

}

/**
 * Reader-side assertions for the single-version state (after v2
 * unpublish):
 *   - /article/view/:id returns 200, renders v1's title
 *   - the `.versions` picker does NOT include v2 (excludedTitleFragment
 *     must not appear in the list)
 *
 * @param {{
 *   browser: import('@playwright/test').Browser,
 *   baseURL?: string,
 *   submissionId: number,
 *   expectedTitleFragment: string,
 *   excludedTitleFragment: string,
 * }} opts
 */
async function expectReaderHasOnlyVersion({
	browser,
	baseURL,
	submissionId,
	expectedTitleFragment,
	excludedTitleFragment,
}) {
	const ctx = await browser.newContext({baseURL});	const page = await ctx.newPage();
	const resp = await page.goto(
		`/index.php/publicknowledge/article/view/${submissionId}`,
	);
	expect(resp?.status()).toBe(200);
	await expect(page.locator('h1').first()).toContainText(
		expectedTitleFragment,
	);

	// v2 no longer listed in the version picker.
	const versions = page.locator('.versions');
	await expect(versions).not.toContainText(excludedTitleFragment);

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

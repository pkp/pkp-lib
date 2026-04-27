// @ts-check
const {test, expect} = require('../support/base-test.js');
const {DiscussionManagerPage} = require('../pages/DiscussionManagerPage.js');

/**
 * Sanity spec for Step 2 of the scenario-extensions plan:
 *   1. Every seeded submission gets a default Article Text file (matches
 *      what a real wizard upload produces).
 *   2. Spec-level `commentsForEditor` is honored as a submission setting,
 *      and `submitted: true` triggers Repo::submission()->submit() which
 *      converts that setting into a Stage 1 "Comments for the Editor"
 *      discussion (closes row #14's "→ discussion" gap).
 *
 * Lives in lib/pkp because both the scenario API and the default-file
 * behaviour are shared concerns. Inlines a minimal submission spec
 * (publicknowledge / ART / dbarnes) so the test stays standalone — no
 * cross-repo fixture import.
 */

/** Worker-scoped tag so parallel runs don't collide. */
function uniqueTag(suffix) {
	const workerIndex = test.info().parallelIndex;
	const rand = Math.random().toString(36).slice(2, 8);
	return `sdf-w${workerIndex}-${suffix}-${rand}`;
}

/**
 * Minimal stage-1 submission spec — same shape as the OJS-root
 * submission-draft fixture but inlined to avoid a cross-repo require.
 */
function baseSpec(tag) {
	return {
		tag,
		journal: 'publicknowledge',
		submitter: 'rvaca',
		section: 'ART',
		locale: 'en',
		participants: [{user: 'dbarnes', role: 'editor'}],
		publications: [
			{
				versionStage: 'AO',
				metadata: {
					title: {en: `Default-file sanity ${tag}`},
					abstract: {en: '<p>Sanity test for the scenario default file.</p>'},
				},
				published: false,
			},
		],
	};
}

test.describe('Scenario submission — default file + commentsForEditor', () => {
	test('every seeded submission has a default Article Text PDF', {tag: '@regression'}, async ({pkpApi, asUser}) => {
		const tag = uniqueTag('file');
		const {submission} = await pkpApi.createSubmission(baseSpec(tag));

		// Use a logged-in browser context to query the files endpoint —
		// the bare APIRequestContext fixture has no session cookies.
		const ctx = await asUser('dbarnes');
		const res = await ctx.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/files?fileStages[]=2`,
		);
		expect(res.ok(), `files endpoint returned ${res.status()}: ${await res.text()}`).toBe(true);
		const body = await res.json();
		const items = body.items ?? body;
		expect(Array.isArray(items), 'files response should be an array').toBe(true);
		// fileStage = 2 = SubmissionFile::SUBMISSION_FILE_SUBMISSION
		const submissionStageFiles = items.filter((f) => f.fileStage === 2);
		expect(submissionStageFiles.length, 'exactly one submission-stage file').toBe(1);

		const file = submissionStageFiles[0];
		expect(file.mimetype).toBe('application/pdf');
		expect(file.genreId, 'genreId should resolve to an installed genre').toBeGreaterThan(0);
	});

	test('commentsForEditor + submitted creates a Stage 1 discussion (row #14)', {tag: '@regression'}, async ({pkpApi, asUser}) => {
		const tag = uniqueTag('cfe');
		const commentBody = `Editor please note ${tag}: this submission targets the Reviews track.`;
		const spec = {
			...baseSpec(tag),
			commentsForEditor: commentBody,
			submitted: true,
		};
		const {submission} = await pkpApi.createSubmission(spec);

		const ctx = await asUser('dbarnes');

		// (a) The submission row's `commentsForTheEditors` setting matches.
		const subRes = await ctx.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}`,
		);
		expect(subRes.ok(), `submission GET ${subRes.status()}`).toBe(true);
		const subBody = await subRes.json();
		expect(subBody.commentsForTheEditors, 'commentsForTheEditors setting should match').toBe(commentBody);

		// (b) Editor sees the comment as a Stage 1 discussion entry. The
		// URL shape is OJS-specific so we navigate directly rather than
		// going through a page object that lives in the OJS root.
		const page = await ctx.newPage();
		await page.goto(
			`/index.php/publicknowledge/en/dashboard/editorial?workflowSubmissionId=${submission.id}`,
		);

		const dm = new DiscussionManagerPage(page);
		await dm.expectVisible();
		// submit() creates a discussion titled by the
		// `submission.submit.coverNote` locale ("Comments for the Editor"),
		// with the spec's commentsForEditor as the first message body.
		// Open the discussion and verify the body matches the unique tag.
		const display = await dm.openByTitle('Comments for the Editor');
		await display.expectContains(commentBody);
		await display.close();
	});
});

// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Sanity spec for the scenario-API default Article Text file: every
 * submission seeded via /api/v1/_test/scenarios/submission gets one
 * fileStage = SUBMISSION_FILE_SUBMISSION (= 2) row attached, mirroring
 * what a real wizard upload produces. Inlines a minimal stage-1 spec so
 * the test stays standalone — no cross-repo fixture import.
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

test.describe('Scenario submission — default file', () => {
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
});

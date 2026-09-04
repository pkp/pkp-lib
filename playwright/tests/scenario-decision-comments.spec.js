// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Sanity spec for Step 3 of the scenario-extensions plan: each decision
 * spec accepts optional `toAuthor` / `toReviewers` / `toEditor` fields
 * mirroring the live decision form.
 *
 *   toAuthor / toReviewers — surfaced as email actions on the decision,
 *     consumed by Repo::decision()->add() via the same code path the
 *     form posts to (DecisionType::runAdditionalActions ->
 *     NotifyAuthors / NotifyReviewers traits). Mail::fake() is in
 *     effect so this only exercises the wiring (decision row + email
 *     log entries), not real outbound mail.
 *
 *   toEditor — internal editor-only note inserted into submission_comments
 *     with comment_type = COMMENT_TYPE_EDITOR_DECISION, viewable=0.
 *     The legacy decision form once exposed this field; the current
 *     Vue UI does not, but the constant + storage are still wired so
 *     seeding the row is the cleanest way to replicate the legacy
 *     submission state. The processor surfaces the inserted comment
 *     id + body on the response so this spec can assert without a
 *     dedicated read endpoint.
 *
 * Lives in lib/pkp because both the scenario API and the underlying
 * decision/comment storage are shared between OJS/OMP/OPS.
 */

/** Worker-scoped tag so parallel runs don't collide. */
function uniqueTag(suffix) {
	const workerIndex = test.info().parallelIndex;
	const rand = Math.random().toString(36).slice(2, 8);
	return `sdc-w${workerIndex}-${suffix}-${rand}`;
}

/**
 * Stage-1 → review-stage submission with two completed reviewers in
 * round 1 — the minimum state RequestRevisions needs to ship a
 * notifyReviewers action (the trait filters reviewers by
 * REVIEW_COMPLETE_STATUSES).
 */
function inReviewSpec(tag) {
	return {
		tag,
		journal: 'publicknowledge',
		submitter: 'rvaca',
		section: 'ART',
		locale: 'en',
		participants: [{user: 'dbarnes', role: 'editor'}],
		decisions: [{type: 'sendExternalReview', by: 'dbarnes'}],
		reviewRounds: [
			{
				reviewers: [
					{
						user: 'phudson',
						method: 'anonymous',
						status: 'completed',
						recommendation: 'pendingRevisions',
					},
					{
						user: 'jjanssen',
						method: 'anonymous',
						status: 'completed',
						recommendation: 'pendingRevisions',
					},
				],
			},
		],
		publications: [
			{
				versionStage: 'AO',
				metadata: {
					title: {en: `Decision-comments sanity ${tag}`},
					abstract: {en: '<p>Round-1-with-completed-reviews fixture.</p>'},
				},
				published: false,
			},
		],
	};
}

test.describe('Scenario decision comments — toAuthor / toReviewers / toEditor', () => {
	test('requestRevisions seeds an editor-decision comment + notify actions', async ({
		pkpApi,
		asUser,
	}) => {
		const tag = uniqueTag('all-three');
		const toAuthorBody = `[${tag}] Please address the reviewer comments.`;
		const toReviewersBody = `[${tag}] Thanks for your review — sending revisions back.`;
		const toEditorBody = `[${tag}] Internal note: keep an eye on the methodology.`;

		const spec = inReviewSpec(tag);
		spec.decisions.push({
			type: 'requestRevisions',
			by: 'dbarnes',
			toAuthor: toAuthorBody,
			toReviewers: toReviewersBody,
			toEditor: toEditorBody,
		});

		const response = await pkpApi.createSubmission(spec);
		const {submission} = response;

		// (a) No warnings — RequestRevisions uses both NotifyAuthors and
		// NotifyReviewers traits, and we seeded two completed reviewers.
		expect(response.warnings ?? []).toEqual([]);

		// (b) Decisions endpoint surfaces the requestRevisions row.
		const ctx = await asUser('dbarnes');
		const decisionsRes = await ctx.request.get(
			`/index.php/publicknowledge/api/v1/submissions/${submission.id}/decisions`,
		);
		expect(decisionsRes.ok(), `decisions GET ${decisionsRes.status()}`).toBe(
			true,
		);
		const decisionsBody = await decisionsRes.json();
		const decisions = decisionsBody.items ?? decisionsBody;
		const requestRevisions = decisions.find(
			(d) => d.decision === pkpConst.DECISION_PENDING_REVISIONS,
		);
		expect(
			requestRevisions,
			'requestRevisions decision row should exist',
		).toBeTruthy();

		// (c) The processor surfaces the seeded toEditor comment id + body
		// on the scenario response. Asserting against the response is
		// sufficient — the row is a direct DB insert and the id round-trip
		// proves it landed.
		const requestRevisionsRecord = response.decisions.find(
			(d) => d.type === 'requestRevisions',
		);
		expect(
			requestRevisionsRecord?.toEditorComment,
			'requestRevisions decision should carry a toEditorComment fragment',
		).toBeTruthy();
		expect(requestRevisionsRecord.toEditorComment.body).toBe(toEditorBody);
		expect(
			requestRevisionsRecord.toEditorComment.id,
			'comment id should be a positive int',
		).toBeGreaterThan(0);

		// (d) The workflow page surfaces the requestRevisions decision in
		// its decision-history widget (label: "Revisions Requested"). This
		// is the closest UI signal that the decision + its email actions
		// landed cleanly. We don't assert the to-author body itself — the
		// activity-log decoration is OJS-version-dependent and the
		// decision-row check above is the load-bearing assertion.
		const page = await ctx.newPage();
		await page.goto(
			`/index.php/publicknowledge/en/dashboard/editorial?workflowSubmissionId=${submission.id}`,
		);
		await expect(
			page.getByText('Revisions Requested', {exact: false}).first(),
		).toBeVisible({timeout: 15_000});
	});

	test('skipExternalReview soft-fails toReviewers with a warning', async ({
		pkpApi,
	}) => {
		const tag = uniqueTag('skip');
		const spec = {
			tag,
			journal: 'publicknowledge',
			submitter: 'rvaca',
			section: 'ART',
			locale: 'en',
			participants: [{user: 'dbarnes', role: 'editor'}],
			decisions: [
				{
					type: 'skipExternalReview',
					by: 'dbarnes',
					toAuthor: `[${tag}] Skipping review.`,
					toReviewers: `[${tag}] Should be ignored — no reviewers exist.`,
				},
			],
			publications: [
				{
					versionStage: 'AO',
					metadata: {
						title: {en: `Skip-review sanity ${tag}`},
						abstract: {en: '<p>Skip-external-review with toReviewers.</p>'},
					},
					published: false,
				},
			],
		};

		const response = await pkpApi.createSubmission(spec);
		expect(response.submission.id).toBeTruthy();

		// SkipExternalReview only includes the NotifyAuthors trait — no
		// notifyReviewers step exists, so the processor should drop the
		// action with a warning rather than throwing.
		const warnings = response.warnings ?? [];
		expect(warnings.length, `expected one warning, got ${JSON.stringify(warnings)}`).toBe(1);
		expect(warnings[0]).toMatch(/skipExternalReview/);
		expect(warnings[0]).toMatch(/notifyReviewers/);
	});
});

const pkpConst = {
	DECISION_PENDING_REVISIONS: 4, // Decision::PENDING_REVISIONS
};

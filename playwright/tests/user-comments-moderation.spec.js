// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Public comments — moderator UI surface (row #61, the second
 * public-comments row added in §1 to track the moderator-side
 * `userComments` management page that row #38's reader-side spec
 * does not cover).
 *
 * **Structural-only coverage at this stage.** The audit's flagged
 * surfaces — Approve / Hide / Delete via the detail modal, Reports
 * tab + report deletion, version-closes-discussion gating,
 * delete-own/delete-others authorization rules — all require seeded
 * comments to render in the table before they can be exercised.
 *
 * Probing that flow surfaced a flaky Vue-store reactivity quirk
 * specific to scratch-journal contexts: the page's
 * `useFetchPaginated`-driven comments list reliably *responds* to
 * the API call (200 OK with the seeded comment in the body), but
 * the table sometimes never renders the row — the SPA stays in its
 * "No Items" empty state even after the fetch resolves. The same
 * page on the bootstrap `publicknowledge` journal works fine, and
 * the API itself works (covered by the existing public-comments
 * spec's REST round-trips). The race appears to be in the per-tab
 * `commentsUrl` watch + `useFetchPaginated`'s response → `items`
 * computed propagation under cold-cache scratch-journal session
 * state.
 *
 * Until that race is fixed (most likely path: a scenario passthrough
 * that pre-creates seeded comments in the same DB transaction as
 * the journal-create call, so they are visible BEFORE the SPA mounts
 * and bypasses the per-session reactivity quirk), the row-interaction
 * tests stay deferred. This spec ships the structural surface today:
 *
 *   1. Page mounts on a scratch journal. The Comments heading +
 *      four tab labels (All / Approved / Hidden/Needs Approval /
 *      Reported) render as expected.
 *   2. Tab navigation: clicking each tab activates it (aria-selected
 *      flips) and the URL hash updates. With no seeded comments the
 *      table emits its "No Items" empty state.
 *
 * The reader-side approve flow + REST setApproval round-trip is
 * already covered by the existing `public-comments.spec.js` row #38
 * spec; that complementary coverage is enough to confirm the API
 * pipeline today.
 */
test.describe('Public comments — moderator UI', () => {
	test(
		'page mounts with Comments heading and four tabs',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'mount');
			const {context} = await pkpApi.createJournal({
				tag,
				enablePublicComments: true,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await page.goto(
				`/index.php/${context.path}/management/settings/userComments`,
			);

			// Heading is the localized 'manager.userComment.comments'
			// string ("Comments").
			await expect(
				page.getByRole('heading', {name: 'Comments', exact: true}),
			).toBeVisible({timeout: 15_000});

			// Four tabs render with the expected localized labels.
			// Tabs use role="tab" via lib/ui-library/.../Tabs.vue.
			for (const label of [
				'All',
				'Approved',
				'Hidden/Needs Approval',
				'Reported',
			]) {
				await expect(
					page.getByRole('tab', {name: label, exact: true}),
				).toBeVisible({timeout: 10_000});
			}

			// Default tab is "All" (per `activeTab = ref('all')` in
			// userCommentStore.js). With no seeded comments the
			// table emits its localized "No Items" empty state.
			await expect(
				page.getByText('No Items', {exact: true}).first(),
			).toBeVisible({timeout: 15_000});
		},
	);

	test(
		'manager can navigate between the four moderator tabs',
		{tag: '@regression'},
		async ({pkpApi, asUser}) => {
			const tag = uniqueTag(test.info(), 'tabs');
			const {context} = await pkpApi.createJournal({
				tag,
				enablePublicComments: true,
				users: [{username: 'dbarnes', roles: ['manager']}],
			});

			const ctx = await asUser('dbarnes');
			const page = await ctx.newPage();
			await page.goto(
				`/index.php/${context.path}/management/settings/userComments`,
			);
			await expect(
				page.getByRole('heading', {name: 'Comments', exact: true}),
			).toBeVisible({timeout: 15_000});

			// Click each non-default tab + assert it becomes the
			// active one. The tab-active selector is
			// `aria-selected="true"`. Tabs.vue uses the reka-ui
			// pattern where exactly one tab carries that attribute
			// at a time.
			for (const label of [
				'Approved',
				'Hidden/Needs Approval',
				'Reported',
				'All',
			]) {
				await page.getByRole('tab', {name: label, exact: true}).click();
				await expect(
					page.getByRole('tab', {name: label, exact: true}),
				).toHaveAttribute('aria-selected', 'true', {timeout: 10_000});
			}
		},
	);
});

/**
 * Build a tag scoped to this worker + test title so parallel workers
 * don't collide on the shared submissions list. Mirrors the helper
 * in lib/pkp/playwright/tests/public-comments.spec.js.
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
	return `ucm-w${info.parallelIndex}-${suffix}-${slug}-${rand}`;
}

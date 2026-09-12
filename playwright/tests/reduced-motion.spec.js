// @ts-check
const {test, expect} = require('../support/base-test.js');

/**
 * Reduced-motion smoke check.
 *
 * The config-factory.js `use.contextOptions.reducedMotion` setting forces
 * `prefers-reduced-motion: reduce` on every browser context — both the
 * default one Playwright creates for the `page` fixture AND any manually
 * created context via `browser.newContext(...)`. The lib/ui-library
 * modal/dialog components collocate `@media (prefers-reduced-motion:
 * reduce) { animation: none; }` blocks with their slide/fade keyframes
 * (Modal/SideModal.vue, Modal/SideModalBody.vue, Modal/Dialog.vue), so
 * honouring the preference skips ~300–450 ms of animation per side-modal
 * open or close.
 *
 * Three tests routed through the default `page` fixture (so they
 * exercise the config-driven wiring and respond to the
 * `PLAYWRIGHT_KEEP_ANIMATIONS=1` kill-switch):
 *
 *   1. Default `{page}` reports `matchMedia(reduce)` true and a
 *      synthetic CSS rule with the same media query nullifies a
 *      synthetic animation. Proves the config setting reaches the
 *      default page and the browser engine wires the media query
 *      through to computed styles.
 *
 *   2. Manual `browser.newContext()` (no explicit reducedMotion option)
 *      also reports `matchMedia(reduce)` true. Proves the
 *      `contextOptions` propagation Playwright fixed in
 *      https://github.com/microsoft/playwright/issues/21133 is still
 *      working — guards against an upstream regression that would
 *      silently revert all multi-context specs to animated mode.
 *
 *   3. A real lib/ui-library SideModal (announcements admin) reports
 *      `animation-name: none` on its `.DialogContent`. Proves the
 *      shipped CSS bundle's `@media (prefers-reduced-motion: reduce)`
 *      block wins against the base `sideModalContentEnter*` rules
 *      end-to-end.
 *
 * Kill-switch: `PLAYWRIGHT_KEEP_ANIMATIONS=1` makes config-factory.js
 * drop the `reducedMotion: 'reduce'` setting, so all three tests fail —
 * useful both as the documented opt-out and as confirmation that the
 * env-var wiring still works.
 */

test.use({user: 'dbarnes'});

test.describe('Reduced motion is enabled', () => {
	test(
		'default page: matchMedia + synthetic CSS rule respond',
		{tag: '@smoke'},
		async ({page}) => {
			await page.goto('/');

			const matches = await page.evaluate(
				() => matchMedia('(prefers-reduced-motion: reduce)').matches,
			);
			expect(
				matches,
				'matchMedia must report reduce on the default page fixture',
			).toBe(true);

			const animationName = await page.evaluate(() => {
				const style = document.createElement('style');
				style.textContent = `
					@keyframes __pkpMotionProbe { from {opacity:0} to {opacity:1} }
					#__pkp_motion_probe { animation: __pkpMotionProbe 1s; }
					@media (prefers-reduced-motion: reduce) {
						#__pkp_motion_probe { animation: none; }
					}
				`;
				document.head.appendChild(style);
				const el = document.createElement('div');
				el.id = '__pkp_motion_probe';
				document.body.appendChild(el);
				const computed = getComputedStyle(el).animationName;
				el.remove();
				style.remove();
				return computed;
			});
			expect(
				animationName,
				'CSS @media (prefers-reduced-motion: reduce) override did not apply',
			).toBe('none');
		},
	);

	test(
		'manually-created context inherits reduced motion via contextOptions',
		{tag: '@smoke'},
		async ({browser}) => {
			// Bare newContext — no explicit reducedMotion option. The
			// config-factory.js `use.contextOptions.reducedMotion`
			// setting is the only way this becomes 'reduce'. If this
			// test fails after a Playwright upgrade, the propagation
			// behavior has regressed — revisit the wiring.
			const ctx = await browser.newContext();
			try {
				const page = await ctx.newPage();
				await page.goto('/');
				expect(
					await page.evaluate(
						() => matchMedia('(prefers-reduced-motion: reduce)').matches,
					),
					'browser.newContext() must inherit reducedMotion ' +
						'from use.contextOptions',
				).toBe(true);
			} finally {
				await ctx.close();
			}
		},
	);

	test(
		'lib/ui-library SideModal animation is suppressed in the live app',
		{tag: '@smoke'},
		async ({page}) => {
			// Stronger end-to-end: open a REAL lib/ui-library side modal
			// (Modal/Dialog.vue) and read `animation-name` off its
			// `.DialogContent`. The base rule is
			// `sideModalContentEnter{Desktop,Mobile}`; the
			// `@media (prefers-reduced-motion: reduce)` block in
			// Modal/Dialog.vue:254 nullifies it. Computed `animation-name`
			// of `none` proves the shipped CSS bundle wires through to
			// real components.
			//
			// Trigger: announcements admin → "Add Announcement" opens a
			// SideModal. dbarnes is a manager on the bootstrapped
			// publicknowledge journal. We don't fill the form or save,
			// so the test leaves no state behind.
			await page.goto(
				'/index.php/publicknowledge/management/settings/announcements',
			);
			await page.getByRole('button', {name: 'Add Announcement'}).click();

			const dialogContent = page.locator('.DialogContent').first();
			await expect(dialogContent).toBeVisible({timeout: 10_000});
			await expect(dialogContent).toHaveAttribute('data-state', 'open');

			const animationName = await dialogContent.evaluate(
				(el) => getComputedStyle(el).animationName,
			);
			expect(
				animationName,
				'.DialogContent should have animation-name: none under ' +
					`reduced motion; got "${animationName}"`,
			).toBe('none');
		},
	);
});

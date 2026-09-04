// @ts-check

/**
 * Wait for jQuery to finish all in-flight AJAX requests.
 *
 * `window.jQuery.active` is a counter jQuery maintains for every
 * AJAX request it owns. It increments at request start and decrements
 * on completion (success OR error). When the counter is 0, every
 * jQuery-driven AJAX call has settled, including any chained success
 * callbacks the legacy `AjaxFormHandler` queues to close modals and
 * refresh grids.
 *
 * This is the Playwright counterpart to Cypress's `cy.waitJQuery()`
 * helper at `lib/pkp/cypress/support/commands.js`. Use it after
 * interacting with any legacy jQuery-driven UI (AjaxModal, Smarty
 * grids, FBV forms with AjaxFormHandler) before asserting the next
 * step's state. Modern Vue surfaces don't use jQuery's AJAX, so calling
 * this on a Vue-only flow is a no-op (jQuery is absent or `active`
 * stays at 0) — safe to call defensively.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{timeout?: number, poll?: number}} [opts]
 * @returns {Promise<void>}
 */
exports.waitForJQueryIdle = async function waitForJQueryIdle(
	page,
	{timeout = 10_000, poll = 100} = {},
) {
	await page.waitForFunction(
		() =>
			typeof window.jQuery === 'undefined' || window.jQuery.active === 0,
		null,
		{timeout, polling: poll},
	);
};

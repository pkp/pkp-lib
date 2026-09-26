// @ts-check
const {test, expect} = require('../support/base-test.js');
const submissionInReview = require('../../../../playwright/fixtures/scenarios/submission-in-review.js');

/**
 * Mailpit sanity — proves the round-trip the rest of the suite relies on:
 *
 *   1. clearAll() empties Mailpit's inbox.
 *   2. A real UI action (password-reset for dbarnes) produces SMTP traffic
 *      that flows through OJS's Symfony mailer (configured to talk to
 *      127.0.0.1:1025) into Mailpit, where the pkpMail fixture observes it.
 *   3. clearAll() called again drops the message.
 *
 * Plus the critical guarantee for scenario-driven tests:
 *
 *   4. createSubmission() with a multi-decision review fixture emits a lot
 *      of internal mail during seeding — every Mail::send() inside the
 *      scenario controller is supposed to be intercepted by Mail::fake().
 *      Mailpit must stay empty after such a seed.
 *
 * If (4) fails, the assumption that scenario seeds are mail-silent is wrong
 * and downstream tests asserting on test-action mail will see seeded noise.
 *
 * The spec runs serial because clearAll() is global — interleaving with
 * other mail-touching tests would race each other's inboxes.
 */

test.describe.configure({mode: 'serial'});

test('clearAll empties Mailpit when there is nothing to clear', async ({pkpMail}) => {
	await pkpMail.clearAll();
	// Re-querying for a non-existent recipient should time out fast — use
	// inboxFor with a tiny budget to confirm "empty" rather than waiting
	// for the default 10s.
	await expect(
		pkpMail.inboxFor('nobody-' + Date.now() + '@mailinator.com', {
			timeout: 500,
			poll: 100,
		}),
	).rejects.toThrow(/No mail/);
});

test('password-reset request via the lost-password page lands in Mailpit', async ({
	page,
	pkpMail,
}) => {
	await pkpMail.clearAll();

	// Drive the public lost-password form. The Smarty template uses {csrf},
	// and the form action posts to /login/requestResetPassword. Filling
	// the form via the rendered DOM means CSRF, altcha (off in test
	// config), and rate-limiter (off by default) all get exercised the
	// same way a real browser would do.
	await page.goto('/index.php/index/login/lostPassword');
	await page.locator('input[name="email"]').fill('dbarnes@mailinator.com');
	await page.locator('button[type="submit"]').click();

	// On success OJS renders a generic "instructions sent" page — wait for
	// that nav so we know the POST committed before polling Mailpit.
	await expect(page).toHaveURL(/requestResetPassword|message/);

	const messages = await pkpMail.inboxFor('dbarnes@mailinator.com');
	expect(messages.length).toBeGreaterThan(0);

	// Mailpit fields are PascalCase — verified live (1.29.7).
	const latest = messages[0];
	expect(latest.To.some((addr) => addr.Address === 'dbarnes@mailinator.com')).toBe(true);
	// The default PasswordResetRequested template subject is locale-dependent;
	// just assert it's non-empty rather than pinning to specific copy.
	expect(latest.Subject).toBeTruthy();

	// Round-trip check — fullMessage should return the body, not just a summary.
	const full = await pkpMail.fullMessage(latest.ID);
	expect(full.HTML || full.Text).toBeTruthy();

	// Cleanup so subsequent assertions can verify clearAll() works.
	await pkpMail.clearAll();
	await expect(
		pkpMail.inboxFor('dbarnes@mailinator.com', {timeout: 500, poll: 100}),
	).rejects.toThrow(/No mail/);
});

test('scenario seeding stays mail-faked — Mailpit empty after createSubmission', async ({
	pkpApi,
	pkpMail,
}) => {
	await pkpMail.clearAll();

	// Worker-scoped tag keeps parallel runs isolated.
	const tag = `mailfake-w${test.info().parallelIndex}-${Math.random()
		.toString(36)
		.slice(2, 8)}`;

	// submissionInReview drives sendExternalReview + adds two reviewers.
	// This path internally calls Mail::send() on multiple mailables
	// (submission acknowledgement, editor assignment, reviewer
	// invitations). All of them should be intercepted by Mail::fake() in
	// PKPSubmissionScenarioController; Mailpit must observe nothing.
	await pkpApi.createSubmission(submissionInReview({tag}));

	// Allow a small grace window — if any seeding mail were going to leak
	// it would be in-flight by now (the seed call has already returned).
	await new Promise((r) => setTimeout(r, 1500));

	// Any message in Mailpit at all means a leak.
	const count = await pkpMail.messageCount();
	expect(count).toBe(0);
});

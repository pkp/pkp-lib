// @ts-check
/**
 * @file lib/pkp/playwright/pages/LoginPage.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The site-wide login form. Its selectors are stable ids, which is the one place
 * in the suite where ids beat roles: the labels are localised, the ids are not.
 */

const {BasePage} = require('./BasePage.js');

class LoginPage extends BasePage {
	/** @param {import('@playwright/test').Page} page */
	constructor(page) {
		super(page);
		this.form = page.locator('form#login');
		this.username = page.locator('input#username');
		this.password = page.locator('input#password');
		this.submitButton = page.locator('form#login button[type="submit"]');
		this.lostPasswordLink = page.locator('form#login a[href*="lostPassword"]');
	}

	static url(locale = 'en') {
		return BasePage.siteUrl(`/${locale}/login`);
	}

	async goto(locale = 'en') {
		return this.page.goto(LoginPage.url(locale));
	}

	/**
	 * Fill and submit the form, then wait for the redirect away from /login.
	 *
	 * `waitUntil: 'commit'` on purpose: the post-login landing page fans out a lot
	 * of XHRs, and waiting for 'load' under parallel workers turns a successful
	 * login into a timeout.
	 *
	 * @param {string} username
	 * @param {string} password
	 */
	async login(username, password) {
		await this.goto();
		await this.username.fill(username);
		await this.fillPassword(password);
		await this.submitButton.click();
		await this.page.waitForURL((url) => !url.pathname.includes('/login'), {
			timeout: 15_000,
			waitUntil: 'commit',
		});
	}

	/**
	 * The password input carries maxlength="32" while the roster's rule
	 * (username doubled) produces longer passwords for the section-editor
	 * accounts. The attribute is a client-side cap only — the account really does
	 * have the longer password — so the harness lifts it before typing rather
	 * than weakening the password rule for three users.
	 *
	 * @param {string} password
	 */
	async fillPassword(password) {
		await this.password.evaluate((input, length) => {
			const maxLength = Number(input.getAttribute('maxlength') || 0);

			if (maxLength && maxLength < length) {
				input.removeAttribute('maxlength');
			}
		}, password.length);

		await this.password.fill(password);
	}

	/**
	 * Request a password reset for an email address. Used by the mail
	 * infrastructure checks; it does not change the account's password.
	 *
	 * @param {string} email
	 */
	async requestPasswordReset(email) {
		await this.page.goto(BasePage.siteUrl('/en/login/lostPassword'));
		await this.page.locator('input#email').fill(email);
		await this.page
			.locator('form#lostPasswordForm button[type="submit"]')
			.click();
	}
}

module.exports = {LoginPage};

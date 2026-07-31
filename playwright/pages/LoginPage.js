/**
 * @file lib/pkp/playwright/pages/LoginPage.js
 *
 * The site login form (form#login). Stable-ID selectors.
 *
 * The password input ships maxlength="32" while the sectioneditor.* roster
 * passwords run 34–36 chars — fillPassword() lifts the attribute so specs
 * never see the truncation. (The underlying UI limitation is a product
 * finding for the Login & sessions spec, not something to assert around.)
 */
const {BasePage} = require('./BasePage.js');

exports.LoginPage = class LoginPage extends BasePage {
    constructor(page) {
        super(page);
        this.usernameInput = page.locator('input#username');
        this.passwordInput = page.locator('input#password');
        this.submitButton = page.locator('form#login button[type="submit"]');
    }

    async goto() {
        await this.page.goto(this.siteUrl('/en/login'));
    }

    /**
     * @param {string} password
     */
    async fillPassword(password) {
        await this.passwordInput.evaluate((el) => el.removeAttribute('maxlength'));
        await this.passwordInput.fill(password);
    }

    /**
     * Submit the form and wait for the redirect away from /login.
     * `waitUntil: 'commit'` fires on URL change rather than waiting for the
     * dashboard's XHR fan-out (fragile under parallel load).
     *
     * @param {string} username
     * @param {string} password
     */
    async signIn(username, password) {
        await this.usernameInput.fill(username);
        await this.fillPassword(password);
        await this.submitButton.click();
        await this.page.waitForURL((url) => !url.pathname.includes('/login'), {
            timeout: 15_000,
            waitUntil: 'commit',
        });
    }
};

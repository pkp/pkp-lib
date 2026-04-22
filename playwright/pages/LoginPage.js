// @ts-check
const {BasePage} = require('./BasePage.js');

/**
 * POM for /login. Shared — every app has the same login form.
 * Used by:
 *   - bootstrap's "saves auth storage states" step (see support/auth.js)
 *   - any ad-hoc login spec (rarely; most specs use storageState instead)
 */
exports.LoginPage = class LoginPage extends BasePage {
	constructor(page) {
		super(page);
		// Match the selectors the Cypress suite has used for years
		// (lib/pkp/cypress/support/commands.js:login). Labels vary by
		// locale; the input/form ids are stable.
		this.username = page.locator('input#username');
		this.password = page.locator('input#password');
		this.signIn = page.locator('form#login button');
	}

	async goto() {
		// OJS login URL includes the locale segment. 'en' is always
		// available; specific baseline users can change it post-login.
		await this.page.goto('/index.php/index/en/login');
	}

	async login(username, password) {
		await this.goto();
		await this.username.fill(username);
		await this.password.fill(password);
		await this.signIn.click();
	}
};

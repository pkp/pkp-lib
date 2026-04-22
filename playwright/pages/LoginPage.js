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
		this.username = page.getByLabel('Username');
		this.password = page.getByLabel('Password');
		this.signIn = page.getByRole('button', {name: 'Login'});
	}

	async goto() {
		await this.page.goto('/login');
	}

	async login(username, password) {
		await this.goto();
		await this.username.fill(username);
		await this.password.fill(password);
		await this.signIn.click();
	}
};

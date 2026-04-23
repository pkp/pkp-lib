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

	/**
	 * OJS maintains per-context sessions; baseline users with journal-scoped
	 * roles (editor, reviewer, copyeditor, …) must sign in inside that
	 * journal's login to receive a session that works for its workflow
	 * pages. 'index' is the site-level login — correct for admin and for
	 * users doing cross-context work.
	 *
	 * @param {string} [contextPath='index']
	 */
	async goto(contextPath = 'index') {
		await this.page.goto(`/index.php/${contextPath}/en/login`);
	}

	/**
	 * @param {string} username
	 * @param {string} password
	 * @param {string} [contextPath='index']
	 */
	async login(username, password, contextPath = 'index') {
		await this.goto(contextPath);
		await this.username.fill(username);
		await this.password.fill(password);
		await this.signIn.click();
	}
};

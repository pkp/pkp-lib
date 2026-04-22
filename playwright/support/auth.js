// @ts-check

const fs = require('fs');
const path = require('path');
const {LoginPage} = require('../pages/LoginPage.js');
const {getPassword} = require('../data/users.js');

/**
 * For each baseline user, open a fresh browser context, log in via the
 * shared LoginPage, and persist the resulting cookies + localStorage to
 * <outDir>/<username>.json. Feature specs skip UI login by opting in:
 *
 *   test.use({storageState: 'playwright/.auth/editor1.json'})
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {Array<{username: string, siteAdmin?: boolean, journal?: string}>} users
 * @param {string} outDir
 */
exports.saveAuthStates = async function saveAuthStates(browser, users, outDir) {
	fs.mkdirSync(outDir, {recursive: true});

	for (const user of users) {
		const context = await browser.newContext();
		try {
			const page = await context.newPage();
			const login = new LoginPage(page);
			await login.login(user.username, getPassword(user.username));
			// A successful login redirects away from /login. Wait for the
			// redirect to settle before snapshotting cookies.
			await page.waitForURL((url) => !url.pathname.endsWith('/login'), {
				timeout: 10_000,
			});
			await context.storageState({
				path: path.join(outDir, `${user.username}.json`),
			});
		} finally {
			await context.close();
		}
	}
};

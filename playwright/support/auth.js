// @ts-check

const fs = require('fs');
const path = require('path');
const {LoginPage} = require('../pages/LoginPage.js');
const {baselineUsers, getPassword} = require('../data/users.js');

const AUTH_DIR = 'playwright/.auth';

/**
 * Return the path to a storage-state file for the given user, creating
 * it on first use. Opens a fresh browser context, performs a real UI
 * login via LoginPage, and persists cookies + localStorage to
 * <appRoot>/playwright/.auth/<username>.json. Later calls short-circuit
 * — the file on disk is the cache.
 *
 * Used by the shared `storageState` fixture in support/base-test.js so
 * specs can just declare `test.use({user: 'dbarnes'})` and get a
 * pre-authenticated context on demand, without any upfront bootstrap
 * cost for users they don't touch.
 *
 * Safe under parallel workers: if two workers race on a missing file,
 * both perform a successful login — OJS allows multiple concurrent
 * sessions per user — and the last write wins. Both tests keep working.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {string} username
 * @param {{baseURL?: string, appRoot?: string}=} opts
 *   baseURL  — required for LoginPage's relative page.goto() to resolve;
 *              pass process.env.PLAYWRIGHT_BASE_URL from the fixture.
 *   appRoot  — where playwright/.auth/ lives. Defaults to process.cwd().
 * @returns {Promise<string>}  absolute path to the storage-state JSON
 */
exports.ensureAuthStateFor = async function ensureAuthStateFor(
	browser,
	username,
	{baseURL, appRoot} = {},
) {
	const root = appRoot ?? process.cwd();
	const authPath = path.join(root, AUTH_DIR, `${username}.json`);

	if (fs.existsSync(authPath)) {
		return authPath;
	}

	fs.mkdirSync(path.dirname(authPath), {recursive: true});
	const context = await browser.newContext({baseURL});
	try {
		const page = await context.newPage();
		const login = new LoginPage(page);
		// Baseline users have journal-scoped roles; look up the journal so
		// we sign in at that context's login URL. Admin / unknown users
		// fall back to the site-level 'index' login.
		const user = baselineUsers.find((u) => u.username === username);
		const contextPath = user?.journal ?? 'index';
		await login.login(username, getPassword(username), contextPath);
		// A successful login redirects completely out of the /login tree
		// (to the dashboard or journal home). A failed login re-renders
		// the form at /login/signIn, which still contains "login" in the
		// path — a loose "doesn't end with /login" check would pass for
		// failed logins and silently save an unauthenticated session.
		await page.waitForURL((url) => !url.pathname.includes('/login'), {
			timeout: 10_000,
		});
		await context.storageState({path: authPath});
	} finally {
		await context.close();
	}

	return authPath;
};

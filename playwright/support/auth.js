// @ts-check

const fs = require('fs');
const path = require('path');
const {request: apiRequest} = require('@playwright/test');
const {LoginPage} = require('../pages/LoginPage.js');
const {baselineUsers, getPassword} = require('../data/users.js');

const AUTH_DIR = 'playwright/.auth';

/**
 * Return the path to a storage-state file for the given user, creating
 * it on first use. Opens a fresh browser context, performs a real UI
 * login via LoginPage, and persists cookies + localStorage to
 * <appRoot>/playwright/.auth/<username>.json. Later calls short-circuit
 * — the file on disk is the cache, but only after a cheap HTTP probe
 * confirms the cached cookies still authenticate.
 *
 * Used by the shared `storageState` fixture in support/base-test.js so
 * specs can just declare `test.use({user: 'dbarnes'})` and get a
 * pre-authenticated context on demand, without any upfront bootstrap
 * cost for users they don't touch.
 *
 * Why the validation probe: tests that drive the impersonation flow
 * (LoginHandler::signInAsUser / signOutAsUser) call PKPSessionGuard's
 * `signInAs`/`signOutAs`, both of which migrate the session ID and
 * destroy the previous one. After such a test the cookies persisted in
 * <username>.json point at a session row that no longer exists, and a
 * follow-up test that loads the file lands on /login. We could
 * special-case those tests, but tying liveness to a probe is robust to
 * any future surface that mutates sessions out from under the cache.
 *
 * Safe under parallel workers: if two workers race on a missing or
 * stale file, both perform a successful login — OJS allows multiple
 * concurrent sessions per user — and the last write wins. Both tests
 * keep working.
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

	if (fs.existsSync(authPath) && (await isAuthStateValid(authPath, baseURL))) {
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

/**
 * Probe whether a cached storage-state file still authenticates. Loads
 * the JSON, replays its cookies into a throwaway APIRequestContext, and
 * GETs /index/user/profile with redirects disabled. A 200 means the
 * session is alive; anything else (most commonly a 302 to /login when
 * the session row was destroyed by a prior signInAs/signOutAs) means
 * the cache is stale and the caller should re-login.
 *
 * Kept private — callers should go through ensureAuthStateFor so the
 * stale-cache path automatically falls through to a fresh login.
 *
 * @param {string} authPath
 * @param {string|undefined} baseURL
 * @returns {Promise<boolean>}
 */
async function isAuthStateValid(authPath, baseURL) {
	if (!baseURL) {
		// Without a baseURL we can't probe; assume the file is good and
		// let the test fail loudly if it isn't. This matches the prior
		// behaviour for callers that don't pass a baseURL.
		return true;
	}
	let ctx;
	try {
		ctx = await apiRequest.newContext({baseURL, storageState: authPath});
		const res = await ctx.get('/index.php/index/user/profile', {
			maxRedirects: 0,
			failOnStatusCode: false,
			timeout: 10_000,
		});
		return res.status() === 200;
	} catch {
		return false;
	} finally {
		if (ctx) {
			await ctx.dispose().catch(() => {});
		}
	}
}

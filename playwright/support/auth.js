// @ts-check
/**
 * @file lib/pkp/playwright/support/auth.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Per-user storage-state cache.
 *
 * Logging in through the UI costs a page load and a redirect; multiplied by
 * every test in a suite it is the single largest avoidable cost in the run. So
 * each user is logged in ONCE and the resulting cookies are cached on disk at
 * `<app>/playwright/.auth/<username>.json`, reused by every later test and every
 * later run until the database is reset.
 *
 * A cache like that must be able to be WRONG without being fatal, and it is:
 * impersonation flows (signInAs/signOutAs) migrate the session id and destroy
 * the previous row, so cookies cached before such a test point at a session that
 * no longer exists. Hence the liveness probe — the cached state is only trusted
 * after it demonstrably still authenticates.
 */

const fs = require('fs');
const path = require('path');
const {request} = require('@playwright/test');
const {getPassword} = require('../data/users.js');
const {LoginPage} = require('../pages/LoginPage.js');
const {BasePage} = require('../pages/BasePage.js');

/**
 * A page that requires a session. Signed in it renders; signed out it redirects
 * to the login form — so where the request ENDS is the answer, not its status.
 *
 * Status alone would be wrong: this URL redirects twice even for a signed-in
 * user (to the locale-prefixed form, and on a single-context site into that
 * context), so a `200 means live` probe reports every cached state as dead and
 * quietly re-logs everyone in on every run.
 */
const PROBE_PATH = BasePage.siteUrl('/user/profile');

/**
 * Path of the cached storage state for a user, whether or not it exists.
 *
 * @param {string} authDir
 * @param {string} username
 */
function authStatePath(authDir, username) {
	return path.join(authDir, `${username}.json`);
}

/**
 * Does this cached storage state still authenticate?
 *
 * @param {string} statePath
 * @param {string} baseURL
 */
async function isStateLive(statePath, baseURL) {
	let context;

	try {
		context = await request.newContext({baseURL, storageState: statePath});
		const response = await context.get(PROBE_PATH);

		return response.ok() && !response.url().includes('/login');
	} catch {
		// An unreadable/half-written file (a racing worker) is simply not live.
		return false;
	} finally {
		await context?.dispose();
	}
}

/**
 * Return the path to a storage state authenticated as `username`, logging in
 * through the real UI form if there is no live cached one.
 *
 * Race behaviour under parallel workers: two workers may find the same missing
 * or stale file and both log in. That is harmless — the app allows concurrent
 * sessions for a user — and the write is atomic (write to a unique temp file,
 * then rename), so a third worker can never read a half-written state.
 *
 * @param {import('@playwright/test').Browser} browser
 * @param {string} username
 * @param {{baseURL: string, authDir: string, password?: string}} options
 * @returns {Promise<string>} path to the storage-state file
 */
async function ensureAuthStateFor(browser, username, {baseURL, authDir, password}) {
	const statePath = authStatePath(authDir, username);

	if (fs.existsSync(statePath) && (await isStateLive(statePath, baseURL))) {
		return statePath;
	}

	const context = await browser.newContext({baseURL, storageState: undefined});

	try {
		const page = await context.newPage();
		await new LoginPage(page).login(username, password ?? getPassword(username));

		fs.mkdirSync(authDir, {recursive: true});
		const temporaryPath = `${statePath}.${process.pid}.${Date.now()}.tmp`;
		await context.storageState({path: temporaryPath});
		fs.renameSync(temporaryPath, statePath);
	} finally {
		await context.close();
	}

	return statePath;
}

module.exports = {ensureAuthStateFor, authStatePath, isStateLive, PROBE_PATH};

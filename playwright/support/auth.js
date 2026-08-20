/**
 * @file lib/pkp/playwright/support/auth.js
 *
 * Per-user storage-state cache with a liveness probe.
 *
 * First call for a username performs a real UI login and caches the storage
 * state to <appRoot>/playwright/.auth/<username>.json. Later calls (same run
 * or later runs, until DB reset) short-circuit — but only after a cheap HTTP
 * probe confirms the cached cookies still authenticate: impersonation flows
 * (signInAs/signOutAs) migrate the session id and silently invalidate cached
 * cookies.
 *
 * The probe FOLLOWS redirects and judges by where the request ends
 * (ok() && not /login): even a signed-in profile request redirects twice on
 * this app (locale prefix, then into the single context), so a
 * redirects-disabled status probe would re-login everyone on every run.
 *
 * Under parallel workers two workers may race on a missing/stale file; both
 * log in (concurrent sessions are allowed), last write wins.
 */
const fs = require('fs');
const path = require('path');
const {request} = require('@playwright/test');
const {LoginPage} = require('../pages/LoginPage.js');
const {getPassword} = require('../data/users.js');
const {disableMotion} = require('./motion.js');

/**
 * @param {import('@playwright/test').Browser} browser
 * @param {string} username
 * @param {{baseURL: string}} options
 * @returns {Promise<string>} path to the storage-state JSON file
 */
async function ensureAuthStateFor(browser, username, {baseURL}) {
    const appRoot = process.env.PKP_APP_ROOT;
    const authDir = path.join(appRoot, 'playwright', '.auth');
    const statePath = path.join(authDir, `${username}.json`);

    if (fs.existsSync(statePath)) {
        const probe = await request.newContext({baseURL, storageState: statePath});
        try {
            const response = await probe.get('/index.php/index/user/profile');
            if (response.ok() && !response.url().includes('/login')) {
                return statePath;
            }
        } catch {
            // fall through to a fresh login
        } finally {
            await probe.dispose();
        }
    }

    fs.mkdirSync(authDir, {recursive: true});
    const context = await browser.newContext({
        baseURL,
        storageState: {cookies: [], origins: []},
    });
    await disableMotion(context);
    try {
        const page = await context.newPage();
        const loginPage = new LoginPage(page);
        await loginPage.goto();
        await loginPage.signIn(username, getPassword(username));
        await context.storageState({path: statePath});
    } finally {
        await context.close();
    }
    return statePath;
}

module.exports = {ensureAuthStateFor};

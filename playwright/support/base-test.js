/**
 * @file lib/pkp/playwright/support/base-test.js
 *
 * The extended `test` all suites build on — start here.
 *
 * Fixtures:
 * - user (option): default logged-in user for the file/describe —
 *   `test.use({user: 'editor.diana'})` wires storageState so `page` starts
 *   authenticated. Omit for anonymous flows.
 * - asUser(username): a second, already-authenticated BrowserContext for
 *   multi-actor tests; auto-closes at teardown.
 * - pkpApi: test-API client (bootstrap, createContext, createSubmission).
 * - pkpMail: Mailpit wrapper (recipient-scoped assertions only).
 * - appContext: the app's capability map + seeded-actor roster
 *   (playwright/support/app.context.js). Shared code gates on capabilities
 *   (appContext.capabilities.hasReviewStage), never app names, and resolves
 *   personas through appContext.seed.actors (archetype → username-or-null).
 * - baseURL: per-worker — basePort + parallelIndex, one PHP server each.
 */
const path = require('path');
const base = require('@playwright/test');
const {ensureAuthStateFor} = require('./auth.js');
const {disableMotion} = require('./motion.js');
const {PkpApi} = require('./api.js');
const {PkpMail} = require('./mail.js');

const appRoot = process.env.PKP_APP_ROOT;
if (!appRoot) {
    throw new Error(
        'PKP_APP_ROOT is not set — run tests through the app playwright.config.js (config-factory sets it).'
    );
}
// eslint-disable-next-line import/no-dynamic-require
const appContext = require(path.join(appRoot, 'playwright', 'support', 'app.context.js'));

const test = base.test.extend({
    user: [null, {option: true}],

    appContext: async ({}, use) => {
        await use(appContext);
    },

    baseURL: async ({}, use, testInfo) => {
        const basePort = parseInt(process.env.PLAYWRIGHT_BASE_PORT || '8000', 10);
        await use(`http://127.0.0.1:${basePort + testInfo.parallelIndex}`);
    },

    // Kill animations in the default context (and thus `page`) — see motion.js.
    context: async ({context}, use) => {
        await disableMotion(context);
        await use(context);
    },

    storageState: async ({browser, baseURL, user}, use) => {
        if (!user) {
            await use(undefined);
            return;
        }
        const statePath = await ensureAuthStateFor(browser, user, {baseURL});
        await use(statePath);
    },

    asUser: async ({browser, baseURL}, use) => {
        const openedContexts = [];
        await use(async (username) => {
            const statePath = await ensureAuthStateFor(browser, username, {baseURL});
            const context = await browser.newContext({baseURL, storageState: statePath});
            await disableMotion(context);
            openedContexts.push(context);
            return context;
        });
        await Promise.all(openedContexts.map((context) => context.close().catch(() => {})));
    },

    pkpApi: async ({baseURL}, use) => {
        const api = await PkpApi.create({baseURL});
        await use(api);
        await api.dispose();
    },

    pkpMail: async ({}, use) => {
        await use(new PkpMail());
    },
});

module.exports = {test, expect: base.expect};

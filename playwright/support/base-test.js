// @ts-check
/**
 * @file lib/pkp/playwright/support/base-test.js
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2003-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * The extended `test` every spec in every app builds on. Start here.
 *
 * Shared specs require this file directly; an app's specs require their own
 * `playwright/support/fixtures.js`, which layers that app's fixtures on top.
 *
 * Auth comes in two shapes:
 *
 *   test.use({user: 'editor.diana'})   // single actor: `page` arrives logged in
 *   const ctx = await asUser('reviewer.julia');   // multi-actor: a second context
 *
 * Code in THIS directory is app-agnostic. It gates on capabilities from
 * `appContext.capabilities` and resolves people through `appContext.seed.actors`
 * — never on an app name, because "OJS has issues" is a fact about a capability,
 * not about a repository.
 */

const base = require('@playwright/test');
const {ensureAuthStateFor} = require('./auth.js');
const {PkpApi} = require('./api.js');
const {PkpMail} = require('./mail.js');

/**
 * @typedef {object} PkpServerOption
 * @property {string} host
 * @property {number} basePort port of parallel worker 0
 */

const test = base.test.extend({
	//
	// Options (set by config-factory, overridable with test.use)
	//

	/** Username the default `page` is logged in as; null for an anonymous page. */
	user: [
		/** @type {string|null} */ (null),
		{option: true},
	],

	/** Host and base port of the per-worker PHP servers. */
	pkpServer: [
		/** @type {PkpServerOption} */ ({host: '127.0.0.1', basePort: 8000}),
		{option: true, scope: 'worker'},
	],

	/** Where storage states are cached. */
	authDir: ['', {option: true, scope: 'worker'}],

	/** Absolute path of this app's support/app.context.js. */
	appContextModule: ['', {option: true, scope: 'worker'}],

	/** Shared secret for the /api/v1/_test namespace. */
	testApiKey: ['', {option: true, scope: 'worker'}],

	/** Mailpit's base URL. */
	mailpitUrl: ['http://127.0.0.1:8025', {option: true, scope: 'worker'}],

	/** Command that installs the application schema, run from appRoot. */
	installCommand: ['', {option: true, scope: 'worker'}],

	/** Absolute path of the app repository root. */
	appRoot: ['', {option: true, scope: 'worker'}],

	/** Config file the servers and tools run against. */
	configFile: ['', {option: true, scope: 'worker'}],

	//
	// Derived fixtures
	//

	/**
	 * One PHP server per parallel worker.
	 *
	 * `php -S` handles one request at a time; sharing a single server across
	 * workers serialises the suite and lets one worker's slow request time out
	 * another's page load. `parallelIndex` — not `workerIndex`, which keeps
	 * climbing when a crashed worker is replaced — stays inside [0, workers), so
	 * it maps one-to-one onto the servers config-factory started.
	 */
	baseURL: async ({pkpServer}, use, testInfo) => {
		await use(`http://${pkpServer.host}:${pkpServer.basePort + testInfo.parallelIndex}`);
	},

	/**
	 * This app's capability map, vocabulary and seeded-actor roster.
	 *
	 * Shared code asks this — `if (!appContext.capabilities.hasReviewStage)` —
	 * and never asks which app it is running in.
	 */
	appContext: [
		async ({appContextModule}, use) => {
			if (!appContextModule) {
				throw new Error(
					'No appContextModule configured. The app’s playwright.config.js must be ' +
						'built with definePkpConfig(), which points at playwright/support/app.context.js.',
				);
			}

			await use(require(appContextModule).appContext);
		},
		{scope: 'worker'},
	],

	/**
	 * Storage state for the user named by `test.use({user})`.
	 *
	 * Overriding the built-in option means `page`, `context` and `request` all
	 * arrive authenticated without a spec doing anything.
	 */
	storageState: async ({user, browser, baseURL, authDir}, use) => {
		if (!user) {
			await use(undefined);

			return;
		}

		await use(await ensureAuthStateFor(browser, user, {baseURL, authDir}));
	},

	/**
	 * Open an authenticated browser context as another user — the multi-actor
	 * shape (an editor assigns, a reviewer accepts). Every context opened this
	 * way closes at teardown.
	 */
	asUser: async ({browser, baseURL, authDir}, use) => {
		/** @type {import('@playwright/test').BrowserContext[]} */
		const opened = [];

		await use(async (username) => {
			const statePath = await ensureAuthStateFor(browser, username, {
				baseURL,
				authDir,
			});
			const context = await browser.newContext({
				baseURL,
				storageState: statePath,
			});
			opened.push(context);

			return context;
		});

		for (const context of opened) {
			await context.close();
		}
	},

	/** Client for the test-only seeding API. */
	pkpApi: async ({playwright, baseURL, testApiKey}, use) => {
		const request = await playwright.request.newContext({
			baseURL,
			extraHTTPHeaders: {'X-Test-Key': testApiKey},
		});

		await use(new PkpApi(request));
		await request.dispose();
	},

	/** Mailpit wrapper. Read its header before asserting on mail. */
	pkpMail: async ({playwright, mailpitUrl}, use) => {
		const request = await playwright.request.newContext({baseURL: mailpitUrl});

		await use(new PkpMail(request));
		await request.dispose();
	},
});

module.exports = {test, expect: base.expect};

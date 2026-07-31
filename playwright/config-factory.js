/**
 * @file lib/pkp/playwright/config-factory.js
 *
 * One Playwright config for all three apps. Each app's
 * playwright/playwright.config.js is a three-line call:
 *
 *   const {definePkpConfig} = require('../lib/pkp/playwright/config-factory.js');
 *   module.exports = definePkpConfig({appName: 'ojs', appRoot: ..., basePort: 8000});
 *
 * Because `php -S` serves one request at a time, every parallel worker gets its
 * own PHP server at basePort + parallelIndex (one shared DB and files dir
 * behind them). The per-worker baseURL override lives in support/base-test.js.
 *
 * Projects: setup → {shared, <app>} → <app>-serial. A Playwright project has
 * one testDir, so the shared lib/pkp specs are a sibling project of the app
 * suite; the serial project runs alone at the end (globally-scanning specs).
 */
const path = require('path');
const {defineConfig, devices} = require('@playwright/test');
const {loadEnv} = require('./support/env.js');

function definePkpConfig({appName, appRoot, basePort}) {
    loadEnv(appRoot);

    // base-test.js and subprocesses (installTest, reset) read these.
    process.env.PKP_APP_NAME = appName;
    process.env.PKP_APP_ROOT = appRoot;
    process.env.PKP_CONFIG_FILE =
        process.env.PKP_CONFIG_FILE || path.join(appRoot, 'config.test.inc.php');
    basePort = parseInt(process.env.PLAYWRIGHT_BASE_PORT || String(basePort), 10);
    process.env.PLAYWRIGHT_BASE_PORT = String(basePort);

    const workers = parseInt(process.env.PLAYWRIGHT_WORKERS || '2', 10);
    const sharedTestDir = path.join(__dirname, 'tests');
    const appTestDir = path.join(appRoot, 'playwright', 'tests');

    const serverEnv = {
        PKP_CONFIG_FILE: process.env.PKP_CONFIG_FILE,
        TEST_API_KEY: process.env.TEST_API_KEY || '',
    };

    return defineConfig({
        outputDir: path.join(appRoot, 'playwright', 'test-results'),
        workers,
        fullyParallel: true,
        reporter: [['list']],
        timeout: 60_000,
        expect: {timeout: 10_000},
        use: {
            ...devices['Desktop Chrome'],
            baseURL: `http://127.0.0.1:${basePort}`,
            trace: 'retain-on-failure',
        },
        projects: [
            {
                name: 'setup',
                testDir: sharedTestDir,
                testMatch: /bootstrap\.setup\.js/,
            },
            {
                name: 'shared',
                testDir: sharedTestDir,
                testIgnore: /bootstrap\.setup\.js/,
                dependencies: ['setup'],
            },
            {
                name: appName,
                testDir: appTestDir,
                testIgnore: /serial[\\/]/,
                dependencies: ['setup'],
            },
            {
                name: `${appName}-serial`,
                testDir: path.join(appTestDir, 'serial'),
                workers: 1,
                dependencies: ['shared', appName],
            },
        ],
        // One PHP server per worker; `php -S` is single-threaded. The ready
        // probe is a static file so it answers even before the DB is installed
        // (the setup project handles cold installs itself).
        webServer: Array.from({length: workers}, (_, i) => ({
            command: `php -S 127.0.0.1:${basePort + i} -t "${appRoot}"`,
            url: `http://127.0.0.1:${basePort + i}/README.md`,
            reuseExistingServer: true,
            timeout: 30_000,
            env: serverEnv,
        })),
    });
}

module.exports = {definePkpConfig};

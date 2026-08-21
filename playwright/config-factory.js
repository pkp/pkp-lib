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
const {execSync} = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const {defineConfig, devices} = require('@playwright/test');
const {loadEnv} = require('./support/env.js');

/**
 * Worker auto-detect. The workload is wait-bound (single-threaded php -S +
 * browser waits), and the measured knee on a 10-core (8P+2E) Mac was exactly
 * the performance-core count — the efficiency cores only add overhead. So:
 * performance cores where the OS can tell them apart (Apple Silicon sysctl,
 * Intel hybrid sysfs), cores - 2 on homogeneous machines. CI runners pin
 * PLAYWRIGHT_WORKERS explicitly (a 4-vcpu runner measured best at 4).
 */
function detectWorkers() {
    try {
        if (process.platform === 'darwin') {
            const pCores = parseInt(
                execSync('sysctl -n hw.perflevel0.logicalcpu', {
                    stdio: ['ignore', 'pipe', 'ignore'],
                })
                    .toString()
                    .trim(),
                10,
            );
            if (pCores >= 2) {
                return pCores;
            }
        } else if (process.platform === 'linux') {
            // Intel hybrid exposes the P-core cpu list, e.g. "0-11".
            const list = fs
                .readFileSync('/sys/devices/cpu_core/cpus', 'utf8')
                .trim();
            const pCores = list
                .split(',')
                .reduce((n, range) => {
                    const [lo, hi] = range.split('-').map(Number);
                    return n + (hi >= lo ? hi - lo + 1 : 1);
                }, 0);
            if (pCores >= 2) {
                return pCores;
            }
        }
    } catch {
        // Fall through: no P/E split detectable on this machine.
    }
    return Math.max(2, os.cpus().length - 2);
}

function definePkpConfig({appName, appRoot, basePort}) {
    loadEnv(appRoot);

    // base-test.js and subprocesses (installTest, reset) read these.
    process.env.PKP_APP_NAME = appName;
    process.env.PKP_APP_ROOT = appRoot;
    process.env.PKP_CONFIG_FILE =
        process.env.PKP_CONFIG_FILE || path.join(appRoot, 'config.test.inc.php');
    basePort = parseInt(process.env.PLAYWRIGHT_BASE_PORT || String(basePort), 10);
    process.env.PLAYWRIGHT_BASE_PORT = String(basePort);

    // Playwright's own default (50% of cores) can't be used directly because
    // the server fleet below must match the worker count at config time.
    const workers = parseInt(
        process.env.PLAYWRIGHT_WORKERS || String(detectWorkers()),
        10,
    );
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
            // 'retain-on-failure' records a full trace per test and discards
            // it on pass — ~12% of suite wall time and half the CPU (2026-08-21
            // perf research). With retries 0 'on-first-retry' never records;
            // flip retries on (or set PWDEBUG traces) when hunting a failure.
            trace: 'on-first-retry',
            // Tests never wait on cosmetic motion; support/motion.js injects
            // the matching CSS override into every context the harness opens.
            reducedMotion: 'reduce',
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
        // (the setup project handles cold installs itself). `php -S` logs every
        // request to stderr, which Playwright pipes into the reporter output by
        // default — redirect it to per-server log files instead. A server kept
        // alive by reuseExistingServer holds its old log open, so the file is
        // only truncated when a server actually (re)starts.
        webServer: Array.from({length: workers}, (_, i) => {
            const logDir = path.join(appRoot, 'playwright', '.server-logs');
            fs.mkdirSync(logDir, {recursive: true});
            const logFile = path.join(logDir, `server-${basePort + i}.log`);
            return {
                command: `php -S 127.0.0.1:${basePort + i} -t "${appRoot}" > "${logFile}" 2>&1`,
                url: `http://127.0.0.1:${basePort + i}/README.md`,
                reuseExistingServer: true,
                timeout: 30_000,
                env: serverEnv,
            };
        }),
    });
}

module.exports = {definePkpConfig};

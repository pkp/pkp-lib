#!/usr/bin/env node
/**
 * @file lib/pkp/playwright/serve.js
 *
 * test:e2e:serve — a manual PHP server on the fleet's base port for poking
 * around the test install (same env the Playwright-launched servers get).
 * Stop any Playwright run first: both want the same port.
 *
 * Run from an app root: node lib/pkp/playwright/serve.js
 */
const path = require('path');
const {spawn} = require('child_process');
const {loadEnv} = require('./support/env.js');

const appRoot = process.cwd();
loadEnv(appRoot);

const port = parseInt(process.env.PLAYWRIGHT_BASE_PORT || '8000', 10);
const env = {
    ...process.env,
    PKP_CONFIG_FILE:
        process.env.PKP_CONFIG_FILE || path.join(appRoot, 'config.test.inc.php'),
};

console.log(`serve: php -S 127.0.0.1:${port} -t ${appRoot}`);
console.log(`serve: PKP_CONFIG_FILE=${env.PKP_CONFIG_FILE}`);
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', appRoot], {
    env,
    stdio: 'inherit',
});
server.on('exit', (code) => process.exit(code ?? 0));

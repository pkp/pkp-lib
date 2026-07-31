/**
 * @file lib/pkp/playwright/support/env.js
 *
 * Loads .env.playwright from the app root into process.env.
 * Values already exported in the shell win, so
 * `TEST_API_KEY=other npx playwright test` still overrides the file.
 */
const fs = require('fs');
const path = require('path');

/**
 * @param {string} appRoot Absolute path to the app repo root
 */
function loadEnv(appRoot) {
    const envFile = path.join(appRoot, '.env.playwright');
    if (!fs.existsSync(envFile)) {
        return;
    }
    for (const line of fs.readFileSync(envFile, 'utf8').split('\n')) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) {
            continue;
        }
        const eq = trimmed.indexOf('=');
        if (eq === -1) {
            continue;
        }
        const key = trimmed.slice(0, eq).trim();
        let value = trimmed.slice(eq + 1).trim();
        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }
        if (!(key in process.env)) {
            process.env[key] = value;
        }
    }
}

module.exports = {loadEnv};

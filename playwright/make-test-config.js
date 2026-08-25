#!/usr/bin/env node
/**
 * @file lib/pkp/playwright/make-test-config.js
 *
 * Generate a config.test.inc.php from the app's config.TEMPLATE.inc.php —
 * the same file the installer patches — so the test config can never drift
 * from the shipped template. Writes to stdout:
 *
 *   node lib/pkp/playwright/make-test-config.js > config.test.inc.php
 *
 * Run from an app root. Every test-env decision is one entry in PATCHES
 * below; a key is replaced in place inside its section (active or
 * commented), or appended under the section header when the template does
 * not carry it. Inputs (all optional):
 *
 *   PLAYWRIGHT_BASE_PORT  fleet base port (default 8000)
 *   TEST_DB_NAME          database name, must contain "test" (default ojs_test)
 *   TEST_DB_USERNAME / TEST_DB_PASSWORD   DB credentials (default ojs / ojs)
 *   TEST_FILES_DIR        files dir (default <appRoot>/../files-test)
 *   TEST_PUBLIC_FILES_DIR public files dir (default <appRoot>/public)
 *   TEST_LOCALES          installed_locales (default en,fr_CA)
 *   TEST_APP_KEY          app key (default: random per invocation)
 */
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const appRoot = process.cwd();
const port = process.env.PLAYWRIGHT_BASE_PORT || '8000';
const dbName = process.env.TEST_DB_NAME || 'ojs_test';
if (!/test/i.test(dbName)) {
    console.error(`make-test-config: database "${dbName}" has no "test" in the name — refusing.`);
    process.exit(1);
}
const appKey =
    process.env.TEST_APP_KEY ||
    `base64:${crypto.randomBytes(32).toString('base64')}`;
const filesDir =
    process.env.TEST_FILES_DIR || path.join(appRoot, '..', 'files-test');
const publicFilesDir =
    process.env.TEST_PUBLIC_FILES_DIR || path.join(appRoot, 'public');

// Section → key → value. One entry per deliberate test-env decision.
const PATCHES = {
    general: {
        installed: 'On',
        app_key: `"${appKey}"`,
        base_url: `"http://127.0.0.1:${port}"`,
        session_cookie_name: `${dbName.replace(/[^A-Za-z0-9]/g, '').toUpperCase()}SID`,
        session_lifetime: '30',
        // Only the loopback host may drive the fleet (harness.md).
        allowed_hosts: `"[\\"127.0.0.1\\",\\"127.0.0.1:${port}\\"]"`,
        citation_checking_max_processes: '3',
        enable_minified: 'On',
        enable_beacon: 'Off',
    },
    database: {
        driver: 'postgres9',
        host: '127.0.0.1',
        username: process.env.TEST_DB_USERNAME || 'ojs',
        password: process.env.TEST_DB_PASSWORD || 'ojs',
        name: dbName,
    },
    i18n: {
        installed_locales: process.env.TEST_LOCALES || 'en,fr_CA',
    },
    files: {
        files_dir: filesDir,
        public_files_dir: publicFilesDir,
    },
    security: {
        api_key_secret: '"Api_Key_Secret_For_Testing_Purposes_Only"',
    },
    email: {
        default: 'smtp', // the template's sendmail binary does not exist on CI
        smtp: 'On',
        smtp_server: '127.0.0.1',
        smtp_port: '1025', // Mailpit
    },
    oai: {
        repository_id: `"${dbName.replace(/_/g, '-')}.localhost"`,
    },
    proxy: {
        // Server-side outbound HTTP fails fast at this dead local port so
        // tests never reach real external services (harness.md egress rule).
        http_proxy: '"http://127.0.0.1:9"',
        https_proxy: '"http://127.0.0.1:9"',
    },
    queues: {
        job_runner: 'Off',
    },
    schedule: {
        task_runner: 'Off',
    },
    features: {
        enable_new_submission_listing: 'On',
        enable_review_round_history: 'On',
        enable_body_text_editor: 'On',
        enable_new_discussions: 'On',
    },
};

const template = fs.readFileSync(
    path.join(appRoot, 'config.TEMPLATE.inc.php'),
    'utf8'
);

const out = [];
let section = null;
let pending = {}; // keys of the current section not yet placed
let done = new Set(); // keys already patched in the current section
const flushPending = () => {
    for (const [key, value] of Object.entries(pending)) {
        out.push(`${key} = ${value}`);
    }
    pending = {};
    done = new Set();
};

for (const line of template.split('\n')) {
    const sectionMatch = line.match(/^\[(\w+)\]/);
    if (sectionMatch) {
        flushPending(); // template lacked these keys — append before leaving
        section = sectionMatch[1];
        pending = {...(PATCHES[section] || {})};
        out.push(line);
        continue;
    }
    const keyMatch = line.match(/^(;?)\s*([a-z_]+)\s*=/);
    if (keyMatch && pending[keyMatch[2]] !== undefined) {
        out.push(`${keyMatch[2]} = ${pending[keyMatch[2]]}`);
        done.add(keyMatch[2]);
        delete pending[keyMatch[2]];
        continue;
    }
    // A later ACTIVE assignment of an already-patched key would override
    // the patch (last one wins in ini) — drop it. Commented lines stay.
    if (keyMatch && keyMatch[1] === '' && done.has(keyMatch[2])) {
        continue;
    }
    out.push(line);
}
flushPending();

process.stdout.write(
    '; Generated by lib/pkp/playwright/make-test-config.js — do not edit;\n' +
        '; regenerate from config.TEMPLATE.inc.php instead.\n' +
        out.join('\n')
);

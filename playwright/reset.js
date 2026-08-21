#!/usr/bin/env node
/**
 * @file lib/pkp/playwright/reset.js
 *
 * test:e2e:reset — nuke the test install so the next run performs a cold
 * bootstrap: drop + recreate the test database, wipe the test files dir and
 * the storage-state cache (playwright/.auth/).
 *
 * This is the ONE place allowed to dispatch on the configured DB driver
 * (PRINCIPLES D8): everything else in the harness goes through
 * app services and stays driver-agnostic. The local fleets choose Postgres for
 * its strictness; nothing here may depend on it.
 *
 * Run from an app root: node lib/pkp/playwright/reset.js
 */
const fs = require('fs');
const path = require('path');
const {execFileSync} = require('child_process');
const {loadEnv} = require('./support/env.js');

const appRoot = process.cwd();
loadEnv(appRoot);

const configFile =
    process.env.PKP_CONFIG_FILE || path.join(appRoot, 'config.test.inc.php');
if (!fs.existsSync(configFile)) {
    console.error(`reset: config file not found: ${configFile}`);
    process.exit(1);
}

const config = parseIni(fs.readFileSync(configFile, 'utf8'));
const db = config.database || {};
const filesDir = (config.files || {}).files_dir;

// Refuse to touch anything that doesn't look like a test install.
if (!/test/.test(db.name || '')) {
    console.error(`reset: database "${db.name}" does not look like a test DB (no "test" in the name) — refusing.`);
    process.exit(1);
}

console.log(`reset: dropping and recreating database ${db.name} (${db.driver})`);
recreateDatabase(db);

if (filesDir && /test/.test(path.basename(filesDir)) && fs.existsSync(filesDir)) {
    console.log(`reset: wiping files dir ${filesDir}`);
    for (const entry of fs.readdirSync(filesDir)) {
        fs.rmSync(path.join(filesDir, entry), {recursive: true, force: true});
    }
} else if (filesDir) {
    console.log(`reset: leaving files dir alone (${filesDir} — name does not contain "test" or missing)`);
}

const authDir = path.join(appRoot, 'playwright', '.auth');
if (fs.existsSync(authDir)) {
    console.log(`reset: wiping ${authDir}`);
    fs.rmSync(authDir, {recursive: true, force: true});
}

console.log('reset: done — next run will cold-bootstrap.');

function recreateDatabase({driver, host, username, password, name}) {
    const env = {...process.env};
    switch (true) {
        case /postgres/.test(driver || ''): {
            if (password) {
                env.PGPASSWORD = password;
            }
            const hostArgs = host ? ['-h', host] : [];
            const userArgs = username ? ['-U', username] : [];
            execFileSync('dropdb', ['--if-exists', ...hostArgs, ...userArgs, name], {stdio: 'inherit', env});
            execFileSync('createdb', [...hostArgs, ...userArgs, name], {stdio: 'inherit', env});
            break;
        }
        case /mysql|mariadb/.test(driver || ''): {
            const args = [];
            if (host) {
                args.push('-h', host);
            }
            if (username) {
                args.push('-u', username);
            }
            if (password) {
                args.push(`-p${password}`);
            }
            args.push('-e', `DROP DATABASE IF EXISTS \`${name}\`; CREATE DATABASE \`${name}\` DEFAULT CHARACTER SET utf8mb4;`);
            execFileSync('mysql', args, {stdio: 'inherit', env});
            break;
        }
        default:
            console.error(`reset: unsupported database driver "${driver}"`);
            process.exit(1);
    }
}

/** Minimal INI parser — enough for the [database]/[files] sections. */
function parseIni(text) {
    const result = {};
    let section = null;
    for (const rawLine of text.split('\n')) {
        const line = rawLine.trim();
        if (!line || line.startsWith(';') || line.startsWith('#')) {
            continue;
        }
        const sectionMatch = line.match(/^\[([^\]]+)\]$/);
        if (sectionMatch) {
            section = sectionMatch[1];
            result[section] ??= {};
            continue;
        }
        const eq = line.indexOf('=');
        if (eq === -1 || !section) {
            continue;
        }
        const key = line.slice(0, eq).trim();
        let value = line.slice(eq + 1).trim();
        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }
        result[section][key] = value;
    }
    return result;
}

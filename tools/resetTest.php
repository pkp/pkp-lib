<?php

/**
 * @file lib/pkp/tools/resetTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Reset the Playwright test harness to a clean slate.
 *
 * Drops + recreates the test database, removes config.test.inc.php,
 * clears playwright/.auth/ and the files directory. config.test.inc.php,
 * the DB, and the filesystem artifacts have to stay in sync — resetting
 * just one leaves the others stale and the harness fails in confusing
 * ways. This tool does all four atomically.
 *
 * Refuses to run unless APPLICATION_ENV=test — nothing here should ever
 * touch a production install.
 *
 * Connects via a fresh PDO (not via the full OJS bootstrap) so it works
 * even when the DB is in a mid-broken state that would fail
 * Application::__construct.
 *
 * Reads credentials from env vars (set directly or via .env.playwright).
 * Run via `npm run test:e2e:reset` — shared across OJS / OMP / OPS, so
 * lives in lib/pkp/tools/ rather than an app-local tools/ directory.
 */

// Defense in depth:
// 1. CLI-only — same guard CommandLineTool applies. We can't simply
//    extend CommandLineTool here because its require_once of bootstrap.php
//    triggers Application::__construct, which needs a working DB — the
//    very state this tool is meant to recover from.
// 2. APPLICATION_ENV=test — a DB-destroying tool has no business running
//    outside the test harness, even from the CLI.
if (isset($_SERVER['SERVER_NAME'])) {
    exit('This script can only be executed from the command-line');
}
if (getenv('APPLICATION_ENV') !== 'test') {
    fwrite(STDERR, "resetTest.php refuses to run unless APPLICATION_ENV=test\n");
    exit(1);
}

// This script lives in lib/pkp/tools/ and is shared across OJS/OMP/OPS.
// The consuming app's root is the current working directory — npm scripts
// run from package.json's dir, and manual `php lib/pkp/tools/resetTest.php`
// invocations are expected from the app root too. A sanity check on
// config.TEMPLATE.inc.php catches "ran from the wrong directory".
$appRoot = getcwd();
if (!is_file($appRoot . '/config.TEMPLATE.inc.php')) {
    fwrite(STDERR, "resetTest.php: no config.TEMPLATE.inc.php in {$appRoot}. "
        . "Run this from the app root.\n");
    exit(1);
}

// Load .env.playwright if present so standalone `php lib/pkp/tools/
// resetTest.php` works (outside npm, which would already export the vars).
$envFile = $appRoot . '/.env.playwright';
if (is_readable($envFile)) {
    require_once $appRoot . '/lib/pkp/lib/vendor/autoload.php';
    // createUnsafeImmutable also calls putenv() so getenv() sees the
    // loaded vars. The plain createImmutable variant only populates
    // $_ENV / $_SERVER.
    \Dotenv\Dotenv::createUnsafeImmutable(dirname($envFile), basename($envFile))->safeLoad();
}

$required = ['OJS_DB_DRIVER', 'OJS_DB_HOST', 'OJS_DB_USER', 'OJS_DB_NAME', 'OJS_FILES_DIR'];
foreach ($required as $name) {
    if (getenv($name) === false || getenv($name) === '') {
        fwrite(STDERR, "reset: missing env var {$name}\n");
        exit(1);
    }
}

$driver = getenv('OJS_DB_DRIVER');
$host = getenv('OJS_DB_HOST');
$user = getenv('OJS_DB_USER');
$password = getenv('OJS_DB_PASSWORD') ?: '';
$dbName = getenv('OJS_DB_NAME');
$filesDir = getenv('OJS_FILES_DIR');

/**
 * Drop + recreate the target DB by connecting to the cluster-default
 * "meta" database (postgres for Postgres, no dbname for MySQL). Uses
 * PDO::quote() to escape the database-name identifier-style; DROP/CREATE
 * DATABASE doesn't support parameter binding in either driver.
 */
if (preg_match('/^postgres/i', $driver)) {
    $pdo = new PDO("pgsql:host={$host};dbname=postgres", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $quoted = '"' . str_replace('"', '""', $dbName) . '"';
    $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
    $pdo->exec("CREATE DATABASE {$quoted}");
} elseif (preg_match('/^(mysql|mariadb)/i', $driver)) {
    $pdo = new PDO("mysql:host={$host}", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $quoted = '`' . str_replace('`', '``', $dbName) . '`';
    $pdo->exec("DROP DATABASE IF EXISTS {$quoted}");
    $pdo->exec("CREATE DATABASE {$quoted}");
} else {
    fwrite(STDERR, "reset: unsupported OJS_DB_DRIVER={$driver}\n");
    exit(1);
}
echo "reset: database {$dbName} ({$driver}) dropped and recreated\n";

$configFile = $appRoot . '/config.test.inc.php';
if (is_file($configFile)) {
    unlink($configFile);
    echo "reset: removed {$configFile}\n";
}

/** Recursively delete everything inside $dir, preserving .gitkeep. */
function clearDirContents(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
            continue;
        }
        $target = $dir . '/' . $entry;
        if (is_dir($target) && !is_link($target)) {
            clearDirContents($target);
            rmdir($target);
        } else {
            unlink($target);
        }
    }
}

$authDir = $appRoot . '/playwright/.auth';
clearDirContents($authDir);
echo "reset: cleared {$authDir}\n";

clearDirContents($filesDir);
echo "reset: cleared {$filesDir}\n";

echo "reset: done.\n";

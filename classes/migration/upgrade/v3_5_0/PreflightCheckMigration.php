<?php

/**
 * @file classes/migration/upgrade/v3_5_0/PreflightCheckMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreflightCheckMigration
 *
 * @brief Check for common problems early in the upgrade process.
 */

namespace PKP\migration\upgrade\v3_5_0;

use PKP\config\Config;
use PKP\core\PKPContainer;
use PKP\db\DAORegistry;
use PKP\install\PKPInstall;
use Throwable;

class PreflightCheckMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Non-blocking warning: does not throw, upgrade continues even if mismatched.
        $this->warnConnectionCharsetCollation();

        try {
        } catch (Throwable $e) {
            if ($fallbackVersion = $this->setFallbackVersion()) {
                $this->_installer->log("A pre-flight check failed. The software was successfully upgraded to {$fallbackVersion} but could not be upgraded further (to " . $this->_installer->newVersion->getVersionString() . '). Check and correct the error, then try again.');
            }
            throw $e;
        }
    }

    /**
     * Rollback the migrations.
     */
    public function down(): void
    {
        if ($fallbackVersion = $this->setFallbackVersion()) {
            $this->_installer->log("An upgrade step failed! Fallback set to {$fallbackVersion}. Check and correct the error and try the upgrade again. We recommend restoring from backup, though you may be able to continue without doing so.");
            // Prevent further downgrade migrations from executing.
            $this->_installer->migrations = [];
        }
    }

    /**
     * Store the fallback version in the database, permitting resumption of partial upgrades.
     *
     * @return ?string Fallback version, if one was identified
     */
    protected function setFallbackVersion(): ?string
    {
        if ($fallbackVersion = $this->_attributes['fallback'] ?? null) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var \PKP\site\VersionDAO $versionDao */
            $versionDao->insertVersion(\PKP\site\Version::fromString($fallbackVersion));
            return $fallbackVersion;
        }
        return null;
    }

    /**
     * Warn when [i18n] connection_charset and [database] collation in config.inc.php
     * form an incompatible pair (e.g. utf8 charset with utf8mb4_unicode_ci collation,
     * or utf8mb4 charset with a legacy utf8_* collation).
     *
     * The mismatch is corrected at runtime by PKPInstall::resolveConnectionParams(),
     * so the upgrade proceeds
     *
     * @see https://github.com/pkp/pkp-lib/issues/11563
     */
    protected function warnConnectionCharsetCollation(): void
    {
        // PostgreSQL has no per-connection collation; nothing to check
        $driver = PKPContainer::getDatabaseDriverName();
        if ($driver === 'pgsql') {
            return;
        }

        $rawCharset   = Config::getVar('i18n', 'connection_charset');
        $rawCollation = Config::getVar('database', 'collation');

        ['charset' => $resolvedCharset, 'collation' => $resolvedCollation] =
            PKPInstall::resolveConnectionParams($driver, $rawCharset, $rawCollation);

        $effectiveCharset   = $rawCharset   ?? PKPInstall::LEGACY_CHARSET;
        $effectiveCollation = $rawCollation ?? PKPInstall::LEGACY_MYSQL_COLLATION;

        if ($resolvedCharset === $effectiveCharset && $resolvedCollation === $effectiveCollation) {
            return;
        }

        $this->_installer->log(
            'WARNING: Mismatched charset/collation detected in config.inc.php.'
            . " Config has: [i18n] connection_charset={$effectiveCharset},"
            . " [database] collation={$effectiveCollation}."
            . " These are incompatible — the upgrade will use the corrected values"
            . " (charset={$resolvedCharset}, collation={$resolvedCollation}),"
            . ' but you should update config.inc.php to make the pair explicit:'
            . " set [database] collation={$resolvedCollation}"
            . " and [i18n] connection_charset={$resolvedCharset}."
        );
    }
}

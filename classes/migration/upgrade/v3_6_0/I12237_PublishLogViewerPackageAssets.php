<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12237_PublishLogViewerPackageAssets.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12237_PublishLogViewerPackageAssets
 *
 * @brief Publish log-viewer package assets during upgrade to v3.6.0
 */

namespace PKP\migration\upgrade\v3_6_0;

use PKP\migration\install\PublishPackageAssetsMigration;

class I12237_PublishLogViewerPackageAssets extends PublishPackageAssetsMigration
{
    /**
     * Frozen snapshot of the log-viewer package data for this upgrade migration.
     * This ensures future changes to PublishPackageAssetsMigration::getPublishablePackages()
     * do not affect this upgrade step.
     *
     * @return array<string, array{source: string, destination: string, description: string}>
     */
    public static function getPublishablePackages(): array
    {
        return [
            'log-viewer' => [
                'source' => 'lib/pkp/lib/vendor/opcodesio/log-viewer/public',
                'destination' => 'vendor/log-viewer',
                'description' => 'Log Viewer web interface assets',
            ],
        ];
    }
}

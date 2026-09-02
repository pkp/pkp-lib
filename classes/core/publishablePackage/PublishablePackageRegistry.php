<?php

/**
 * @file classes/core/publishablePackage/PublishablePackageRegistry.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishablePackageRegistry
 *
 * @brief Canonical registry of vendor packages whose frontend assets are
 *   auto-published to public/{destination}. Read by the install migration,
 *   the composer post-install hook, and the publishPackageAssets CLI tool.
 *
 *   The registry is intentionally simple — a static list of core PKP
 *   packages with no extension seam. If a future plugin needs to ship its
 *   own publishable vendor assets, add a 'PublishablePackageRegistry::register'
 *   hook here (with a class_exists(Hook::class) guard so it stays a no-op
 *   in composer pre-bootstrap context).
 */

namespace PKP\core\publishablePackage;

class PublishablePackageRegistry
{
    /**
     * Get all registered publishable packages, keyed by name.
     *
     * @return array<string, PublishablePackage>
     */
    public static function all(): array
    {
        return self::getCorePackages();
    }

    /**
     * Get a single package by name, or null if not registered.
     */
    public static function get(string $name): ?PublishablePackage
    {
        return self::all()[$name] ?? null;
    }

    /**
     * The core (PKP-shipped) publishable packages.
     *
     * @return array<string, PublishablePackage>
     */
    private static function getCorePackages(): array
    {
        return [
            'log-viewer' => new PublishablePackage(
                name: 'log-viewer',
                source: 'lib/pkp/lib/vendor/opcodesio/log-viewer/public',
                destination: 'vendor/log-viewer',
                description: 'Log Viewer web interface assets',
            ),
        ];
    }
}

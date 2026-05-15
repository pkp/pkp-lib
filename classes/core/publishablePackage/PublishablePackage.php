<?php

/**
 * @file classes/core/publishablePackage/PublishablePackage.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishablePackage
 *
 * @brief Immutable value object describing a vendor package whose
 *   frontend assets should be copied to public/{destination}.
 *
 *   - source       — path relative to the application base directory
 *                    (e.g. 'lib/pkp/lib/vendor/opcodesio/log-viewer/public')
 *   - destination  — path relative to the public/ directory
 *                    (e.g. 'vendor/log-viewer' → public/vendor/log-viewer)
 */

namespace PKP\core\publishablePackage;

final class PublishablePackage
{
    public function __construct(
        public readonly string $name,
        public readonly string $source,
        public readonly string $destination,
        public readonly string $description,
    ) {
    }
}

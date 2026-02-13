<?php

/**
 * @file components/CiteComponent.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CiteComponent
 *
 * @ingroup classes_components
 *
 * @brief A class to prepare configurations for PkpCite UI component.
 */

namespace PKP\components;

class CiteComponent
{
    /**
     * Get the locale keys to expose for the PkpCite component.
     */
    public function getLocaleKeys(): array
    {
        return [
            'submission.howToCite',
            'submission.howToCite.citationFormats',
            'submission.howToCite.copyToClipboard',
            'submission.howToCite.selectedFormat',
            'common.copied',
        ];
    }

    /**
     * Get SVG icons used by the PkpCite component.
     */
    public function getSvgIcons(): array
    {
        return [
            'Cancel',
        ];
    }
}

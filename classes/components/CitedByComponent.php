<?php

/**
 * @file components/CitedByComponent.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitedByComponent
 *
 * @ingroup classes_components
 *
 * @brief A class to prepare configurations for PkpCitedBy UI components.
 */

namespace PKP\components;

class CitedByComponent
{
    /**
     * Get the locale keys to expose for the PkpCitedBy component.
     */
    public function getLocaleKeys(): array
    {
        return [
            'plugins.generic.crossref.citedBy.copyCitationDetails',
            'common.close',
            'common.copied',
            'plugins.generic.crossref.citedBy.title',
            'plugins.generic.crossref.registrationAgency.name',
            'plugins.generic.crossref.citedBy.citationCount',
        ];
    }

    /**
     * Get SVG icons used by the PkpCitedBy component.
     */
    public function getSvgIcons(): array
    {
        return [
            'OpenNewTab',
        ];
    }
}

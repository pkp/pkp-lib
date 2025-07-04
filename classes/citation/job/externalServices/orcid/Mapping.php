<?php

/**
 * @file classes/citation/job/externalServices/orcid/Mapping.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Mapping
 *
 * @ingroup citation
 *
 * @brief Mapping of internal data models and external
 *
 * @see https://orcid.org/0000-0002-2013-6920
 * @see https://pub.orcid.org/v2.1/0000-0002-2013-6920
 */

namespace PKP\citation\job\externalServices\orcid;

final class Mapping
{
    /**
     * Authors are people who create works.
     *
     * @return array [ internal => orcid, ... ]
     */
    public static function getAuthor(): array
    {
        return [
            'givenName' => ['person', 'name', 'given-names', 'value'],
            'familyName' => ['person', 'name', 'family-name', 'value']
        ];
    }
}

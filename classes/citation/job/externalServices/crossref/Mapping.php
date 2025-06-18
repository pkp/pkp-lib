<?php

/**
 * @file classes/citation/job/externalServices/crossref/Mapping.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefMapping
 *
 * @ingroup citation
 *
 * @brief Mapping of internal data models and external
 *
 * @see https://api.crossref.org/works/?query.bibliographic=Hauschke C, Cartellieri S, Heller L (2018) Reference implementation for open scientometric indicators (ROSI). Research Ideas and Outcomes 4
 */

namespace PKP\citation\job\externalServices\crossref;

final class Mapping
{
    public static function getWork(): array
    {
        return [
            'doi' => ['message', 'items', 0, 'DOI']
        ];
    }
}

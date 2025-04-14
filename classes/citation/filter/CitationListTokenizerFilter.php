<?php

/**
 * @file classes/citation/filter/CitationListTokenizerFilter.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationListTokenizerFilter
 *
 * @ingroup citation
 *
 * @brief Class that takes an unformatted list of citations and returns an array of raw citation strings.
 */

namespace PKP\citation\filter;

use PKP\filter\Filter;

class CitationListTokenizerFilter extends Filter
{
    public function __construct()
    {
        $this->setDisplayName('Split a reference list into separate citations');

        parent::__construct('primitive::string', 'primitive::string[]');
    }

    /** @copy Filter::process() */
    public function &process(&$input): array
    {
        // The default implementation assumes that raw citations are
        // separated with line endings.
        // 1) Remove empty lines and normalize line endings.
        $input = preg_replace('/[\r\n]+/us', "\n", $input);
        // 2) Remove trailing/leading line breaks.
        $input = trim($input, "\n");
        // 3) Break up at line endings.
        if (empty($input)) {
            $citations = [];
        } else {
            $citations = explode("\n", $input);
        }
        // 4) Remove whitespace from the beginning and the end of each citation.
        $citations = array_map(trim(...), $citations);

        return $citations;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\citation\filter\CitationListTokenizerFilter', '\CitationListTokenizerFilter');
}

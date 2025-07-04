<?php

/**
 * @file classes/citation/CitationListTokenizerFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationListTokenizerFilter
 *
 * @ingroup classes_citation
 *
 * @brief Class that takes an unformatted list of citations
 *  and returns an array of raw citation strings.
 */

namespace PKP\citation;

use PKP\filter\Filter;

class CitationListTokenizerFilter extends Filter
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDisplayName('Split a reference list into separate citations');

        parent::__construct('primitive::string', 'primitive::string[]');
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param string $input
     *
     * @return mixed array
     */
    public function &process(&$input)
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

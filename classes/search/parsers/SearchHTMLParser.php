<?php

/**
 * @file classes/search/SearchHTMLParser.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchHTMLParser
 *
 * @brief Class to extract text from an HTML file.
 */

namespace PKP\search\parsers;

class SearchHTMLParser extends SearchFileParser
{
    public function doRead(): string
    {
        $line = parent::doRead();
        if ($line === false) {
            return $line;
        }

        // strip HTML tags from the read line
        $line = strip_tags($line);

        // convert HTML entities to valid UTF-8 characters
        $line = html_entity_decode($line, ENT_COMPAT, 'UTF-8');

        return $line;
    }
}

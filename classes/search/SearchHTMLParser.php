<?php

/**
 * @file classes/search/SearchHTMLParser.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SearchHTMLParser
 *
 * @ingroup search
 *
 * @brief Class to extract text from an HTML file.
 */

namespace PKP\search;

class SearchHTMLParser extends SearchFileParser
{
    public function doRead()
    {
        // strip HTML tags from the read line
        $line = strip_tags(fgets($this->fp));

        // convert HTML entities to valid UTF-8 characters
        $line = html_entity_decode($line, ENT_COMPAT, 'UTF-8');

        return $line;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\search\SearchHTMLParser', '\SearchHTMLParser');
}

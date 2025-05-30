<?php

/**
 * @file classes/citation/Citation.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Citation
 *
 * @ingroup citation
 *
 * @brief Class representing a citation (bibliographic reference)
 */

namespace PKP\citation;

use PKP\core\DataObject;

class Citation extends DataObject
{
    /**
     * Get the rawCitation.
     */
    public function getRawCitation(): string
    {
        return $this->getData('rawCitation');
    }

    /**
     * Set the rawCitation.
     */
    public function setRawCitation(?string $rawCitation): void
    {
        $rawCitation = $this->cleanCitationString($rawCitation ?? '');
        $this->setData('rawCitation', $rawCitation);
    }

    /**
     * Get the sequence number
     */
    public function getSequence(): int
    {
        return $this->getData('seq');
    }

    /**
     * Set the sequence number
     */
    public function setSequence(int $seq): void
    {
        $this->setData('seq', $seq);
    }

    /**
     * Replace URLs through HTML links, if the citation does not already contain HTML links.
     */
    public function getRawCitationWithLinks(): string
    {
        $rawCitationWithLinks = $this->getRawCitation();
        if (stripos($rawCitationWithLinks, '<a href=') === false) {
            $rawCitationWithLinks = preg_replace_callback(
                '#(http|https|ftp)://[\d\w\.-]+\.[\w\.]{2,6}[^\s\]\[\<\>]*/?#',
                function ($matches) {
                    $trailingDot = in_array($char = substr($matches[0], -1), ['.', ',']);
                    $url = rtrim($matches[0], '.,');
                    return "<a href=\"{$url}\">{$url}</a>" . ($trailingDot ? $char : '');
                },
                $rawCitationWithLinks
            );
        }
        return $rawCitationWithLinks;
    }

    /**
     * Take a citation string and clean/normalize it
     */
    public function cleanCitationString(?string $citationString = null): string|null
    {
        $citationString = trim(stripslashes($citationString));
        return preg_replace('/[\s]+/u', ' ', $citationString);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\citation\Citation', '\Citation');
}

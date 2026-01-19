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
     * This determines if a citation is assumed structured. At least one value in every row has to be non-empty.
     */
    public array $requirementIsStructured = [
        ['doi', 'arxiv', 'handle', 'url', 'urn'],
        ['title'],
        ['authors']
    ];

    /**
     * Get the rawCitation.
     */
    public function getRawCitation(): string
    {
        return $this->getData('rawCitation');
    }

    /**
     * Set the rawCitation.
     * Before set, the raw citation string needs to be processed by CitationListTokenizerFilter.
     */
    public function setRawCitation(string $rawCitation): void
    {
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
     * Get processing status
     */
    public function getProcessingStatus(): int
    {
        return $this->getData('processingStatus');
    }

    /**
     * Set processing status
     */
    public function setProcessingStatus(int $processingStatus): void
    {
        $this->setData('processingStatus', $processingStatus);
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
                    return "<a href='{$url}' target='_blank'>{$url}</a>" . ($trailingDot ? $char : '');
                },
                $rawCitationWithLinks
            );
        }
        return $rawCitationWithLinks;
    }

    /**
     * Determine if citation is structured.
     */
    public function isStructured(): bool
    {
        foreach ($this->requirementIsStructured as $set) {
            $isStructured = false;

            foreach ($set as $item) {
                if (!empty($this->getData($item))) {
                    $isStructured = true;
                    break;
                }
            }

            if (!$isStructured) {
                return false;
            }
        }

        return true;
    }

}

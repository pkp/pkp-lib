<?php

/**
 * @defgroup doi Doi
 * Implements DOI used as persistent identifiers.
 */

/**
 * @file classes/doi/Doi.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Doi
 *
 * @ingroup doi
 *
 * @see DoiDAO
 *
 * @brief Basic class describing a DOI.
 */

namespace PKP\doi;

use PKP\core\DataObject;

class Doi extends DataObject
{
    public const STATUS_UNREGISTERED = 1;
    public const STATUS_SUBMITTED = 2;
    public const STATUS_REGISTERED = 3;
    public const STATUS_ERROR = 4;
    public const STATUS_STALE = 5;

    /**
     * Formats and URL encodes the DOI
     *
     */
    public function getResolvingUrl(): string
    {
        return 'https://doi.org/' . $this->_doiURLEncode($this->getData('doi'));
    }

    //
    // Get/set methods
    //

    /**
     * Get ID of context.
     */
    public function getContextId(): int
    {
        return $this->getData('contextId');
    }

    /**
     * Set ID of context.
     */
    public function setContextId(int $contextId): void
    {
        $this->setData('contextId', $contextId);
    }

    /**
     * Get DOI for this DOI
     *
     * @return string
     */
    public function getDoi()
    {
        return $this->getData('doi');
    }

    /**
     * Set DOI for this DOI
     *
     * @param string $doi
     */
    public function setDoi($doi)
    {
        $this->setData('doi', $doi);
    }

    /**
     * Get status for this DOI
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->getData('status');
    }

    /**
     * Set status for this DOI
     *
     * @param int $status
     */
    public function setStatus($status)
    {
        $this->setData('status', $status);
    }

    /**
     * Encode DOI according to ANSI/NISO Z39.84-2005, Appendix E.
     *
     *
     */
    protected function _doiURLEncode(string $pubId): string
    {
        $search = ['%', '"', '#', ' ', '<', '>', '{'];
        $replace = ['%25', '%22', '%23', '%20', '%3c', '%3e', '%7b'];
        return str_replace($search, $replace, $pubId);
    }

    /**
     * Checks if a string starts with a DOI prefix pattern.
     *
     * This method does not validate the entire DOI format, but only checks if the string
     * begins with a numeric value followed by a period.
     *
     * @param string $text The string to check.
     *
     */
    public static function beginsWithDoiPrefixPattern(string $text): bool
    {
        $pattern = '/^\d+\./i';
        return (bool)preg_match($pattern, $text);
    }
}

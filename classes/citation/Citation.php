<?php

/**
 * @defgroup citation Citation
 */

/**
 * @file classes/citation/Citation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Citation
 *
 * @ingroup citation
 *
 * @brief Class representing a citation (bibliographic reference)
 */

namespace PKP\citation;

use PKP\core\PKPString;

class Citation extends \PKP\core\DataObject
{
    /**
     * Constructor.
     *
     * @param string $rawCitation an unparsed citation string
     */
    public function __construct($rawCitation = null)
    {
        parent::__construct();
        $this->setRawCitation($rawCitation);
    }

    //
    // Getters and Setters
    //

    /**
     * Replace URLs through HTML links, if the citation does not already contain HTML links
     *
     * @return string
     */
    public function getCitationWithLinks()
    {
        $citation = $this->getRawCitation();
        if (stripos($citation, '<a href=') === false) {
            $citation = preg_replace_callback(
                '#(http|https|ftp)://[\d\w\.-]+\.[\w\.]{2,6}[^\s\]\[\<\>]*/?#',
                function ($matches) {
                    $trailingDot = in_array($char = substr($matches[0], -1), ['.', ',']);
                    $url = rtrim($matches[0], '.,');
                    return "<a href=\"{$url}\">{$url}</a>" . ($trailingDot ? $char : '');
                },
                $citation
            );
        }
        return $citation;
    }

    /**
     * Get the rawCitation
     *
     * @return string
     */
    public function getRawCitation()
    {
        return $this->getData('rawCitation');
    }

    /**
     * Set the rawCitation
     *
     * @param string $rawCitation
     */
    public function setRawCitation($rawCitation)
    {
        $rawCitation = $this->_cleanCitationString($rawCitation);
        $this->setData('rawCitation', $rawCitation);
    }

    /**
     * Get the sequence number
     *
     * @return int
     */
    public function getSequence()
    {
        return $this->getData('seq');
    }

    /**
     * Set the sequence number
     *
     * @param int $seq
     */
    public function setSequence($seq)
    {
        $this->setData('seq', $seq);
    }

    //
    // Private methods
    //
    /**
     * Take a citation string and clean/normalize it
     *
     * @param string $citationString
     *
     * @return string
     */
    public function _cleanCitationString($citationString)
    {
        // 1) Strip slashes and whitespace
        $citationString = trim(stripslashes($citationString));

        // 2) Normalize whitespace
        $citationString = PKPString::regexp_replace('/[\s]+/', ' ', $citationString);

        return $citationString;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\citation\Citation', '\Citation');
}

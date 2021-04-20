<?php

/**
 * @file classes/submission/SubmissionAgency.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAgency
 * @ingroup submission
 *
 * @see SubmissionAgencyEntryDAO
 *
 * @brief Basic class describing a submission agency
 */

class SubmissionAgency extends \PKP\controlledVocab\ControlledVocabEntry
{
    //
    // Get/set methods
    //

    /**
     * Get the agency
     *
     * @return string
     */
    public function getAgency()
    {
        return $this->getData('submissionAgency');
    }

    /**
     * Set the agency text
     *
     * @param agency string
     * @param locale string
     */
    public function setAgency($agency, $locale)
    {
        $this->setData('submissionAgency', $agency, $locale);
    }

    public function getLocaleMetadataFieldNames()
    {
        return ['submissionAgency'];
    }
}

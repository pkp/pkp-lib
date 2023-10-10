<?php

/**
 * @file classes/submission/SubmissionAgency.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAgency
 *
 * @ingroup submission
 *
 * @see SubmissionAgencyEntryDAO
 *
 * @brief Basic class describing a submission agency
 */

namespace PKP\submission;

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
     * @param string $agency
     * @param string $locale
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

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionAgency', '\SubmissionAgency');
}

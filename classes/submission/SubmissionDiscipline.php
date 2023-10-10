<?php

/**
 * @file classes/submission/SubmissionDiscipline.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDiscipline
 *
 * @ingroup submission
 *
 * @see SubmissionDisciplineEntryDAO
 *
 * @brief Basic class describing a submission discipline
 */

namespace PKP\submission;

class SubmissionDiscipline extends \PKP\controlledVocab\ControlledVocabEntry
{
    //
    // Get/set methods
    //

    /**
     * Get the discipline
     *
     * @return string
     */
    public function getDiscipline()
    {
        return $this->getData('submissionDiscipline');
    }

    /**
     * Set the discipline text
     *
     * @param string $discipline
     * @param string $locale
     */
    public function setDiscipline($discipline, $locale)
    {
        $this->setData('submissionDiscipline', $discipline, $locale);
    }

    public function getLocaleMetadataFieldNames()
    {
        return ['submissionDiscipline'];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionDiscipline', '\SubmissionDiscipline');
}

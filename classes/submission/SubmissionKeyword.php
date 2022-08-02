<?php

/**
 * @file classes/submission/SubmissionKeyword.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionKeyword
 * @ingroup submission
 *
 * @see SubmissionKeywordEntryDAO
 *
 * @brief Basic class describing a submission keyword
 */

namespace PKP\submission;

class SubmissionKeyword extends \PKP\controlledVocab\ControlledVocabEntry
{
    //
    // Get/set methods
    //

    /**
     * Get the keyword
     *
     * @return string
     */
    public function getKeyword()
    {
        return $this->getData('submissionKeyword');
    }

    /**
     * Set the keyword text
     *
     * @param string $keyword
     * @param string $locale
     */
    public function setKeyword($keyword, $locale)
    {
        $this->setData('submissionKeyword', $keyword, $locale);
    }

    public function getLocaleMetadataFieldNames()
    {
        return ['submissionKeyword'];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionKeyword', '\SubmissionKeyword');
}

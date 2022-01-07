<?php

/**
 * @file classes/submission/SubmissionLanguage.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLanguage
 * @ingroup submission
 *
 * @see SubmissionLanguageEntryDAO
 *
 * @brief Basic class describing a submission language
 */

namespace PKP\submission;

class SubmissionLanguage extends \PKP\controlledVocab\ControlledVocabEntry
{
    //
    // Get/set methods
    //

    /**
     * Get the language
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->getData('submissionLanguage');
    }

    /**
     * Set the language text
     *
     * @param string $language
     * @param string $locale
     */
    public function setLanguage($language, $locale)
    {
        $this->setData('submissionLanguage', $language, $locale);
    }

    /**
     * @copydoc \PKP\controlledVocab\ControlledVocabEntry::getLocaleMetadataFieldNames()
     */
    public function getLocaleMetadataFieldNames()
    {
        return ['submissionLanguage'];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionLanguage', '\SubmissionLanguage');
}

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
     * @param language string
     * @param locale string
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

<?php

/**
 * @file classes/submission/SubmissionLanguageDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLanguageDAO
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned languages
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocabDAO;
use PKP\db\DAORegistry;

class SubmissionLanguageDAO extends ControlledVocabDAO
{
    public const CONTROLLED_VOCAB_SUBMISSION_LANGUAGE = 'submissionLanguage';

    /**
     * Build/fetch and return a controlled vocabulary for languages.
     *
     * @param int $publicationId
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     *
     * @return ControlledVocab
     */
    public function build($publicationId, $assocType = ASSOC_TYPE_PUBLICATION)
    {
        // may return an array of ControlledVocabs
        return parent::_build(self::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE, $assocType, $publicationId);
    }

    /**
     * Get the list of localized additional fields to store.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['submissionLanguage'];
    }

    /**
     * Get Languages for a submission.
     *
     * @param int $publicationId
     * @param array $locales
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
     *
     * @return array
     */
    public function getLanguages($publicationId, $locales = [], $assocType = ASSOC_TYPE_PUBLICATION)
    {
        $result = [];

        $languages = $this->build($publicationId, $assocType);
        $submissionLanguageEntryDao = DAORegistry::getDAO('SubmissionLanguageEntryDAO'); /** @var SubmissionLanguageEntryDAO $submissionLanguageEntryDao */
        $submissionLanguages = $submissionLanguageEntryDao->getByControlledVocabId($languages->getId());
        while ($languageEntry = $submissionLanguages->next()) {
            $language = $languageEntry->getLanguage();
            foreach ($language as $locale => $value) {
                if (empty($locales) || in_array($locale, $locales)) {
                    if (!array_key_exists($locale, $result)) {
                        $result[$locale] = [];
                    }
                    $result[$locale][] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get an array of all of the submission's Languages
     *
     * @return array
     */
    public function getAllUniqueLanguages()
    {
        $result = $this->retrieve('SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', [self::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE]);

        $languages = [];
        foreach ($result as $row) {
            $languages[] = $row->setting_value;
        }
        return $languages;
    }

    /**
     * Add an array of languages
     *
     * @param array $languages
     * @param int $publicationId
     * @param bool $deleteFirst
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     *
     * @return int
     */
    public function insertLanguages($languages, $publicationId, $deleteFirst = true, $assocType = ASSOC_TYPE_PUBLICATION)
    {
        $languageDao = DAORegistry::getDAO('SubmissionLanguageDAO'); /** @var SubmissionLanguageDAO $languageDao */
        $submissionLanguageEntryDao = DAORegistry::getDAO('SubmissionLanguageEntryDAO'); /** @var SubmissionLanguageEntryDAO $submissionLanguageEntryDao */
        $currentLanguages = $this->build($publicationId, $assocType);

        if ($deleteFirst) {
            $existingEntries = $languageDao->enumerate($currentLanguages->getId(), self::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE);

            foreach ($existingEntries as $id => $entry) {
                $entry = trim($entry);
                $submissionLanguageEntryDao->deleteObjectById($id);
            }
        }
        if (is_array($languages)) { // localized, array of arrays

            foreach ($languages as $locale => $list) {
                if (is_array($list)) {
                    $list = array_unique($list); // Remove any duplicate Languages
                    $i = 1;
                    foreach ($list as $language) {
                        $languageEntry = $submissionLanguageEntryDao->newDataObject();
                        $languageEntry->setControlledVocabId($currentLanguages->getId());
                        $languageEntry->setLanguage(urldecode($language), $locale);
                        $languageEntry->setSequence($i);
                        $i++;
                        $submissionLanguageEntryDao->insertObject($languageEntry);
                    }
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionLanguageDAO', '\SubmissionLanguageDAO');
    define('CONTROLLED_VOCAB_SUBMISSION_LANGUAGE', \SubmissionLanguageDAO::CONTROLLED_VOCAB_SUBMISSION_LANGUAGE);
}

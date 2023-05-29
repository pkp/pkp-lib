<?php

/**
 * @file classes/submission/SubmissionKeywordDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionKeywordDAO
 *
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned keywords
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabDAO;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

class SubmissionKeywordDAO extends ControlledVocabDAO
{
    public const CONTROLLED_VOCAB_SUBMISSION_KEYWORD = 'submissionKeyword';

    /**
     * Build/fetch and return a controlled vocabulary for keywords.
     *
     * @param int $publicationId
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     *
     * @return ControlledVocab
     */
    public function build($publicationId, $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        // may return an array of ControlledVocabs
        return parent::_build(self::CONTROLLED_VOCAB_SUBMISSION_KEYWORD, $assocType, $publicationId);
    }

    /**
     * Get the list of localized additional fields to store.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['submissionKeyword'];
    }

    /**
     * Get keywords for a submission.
     *
     * @param int $publicationId
     * @param array $locales
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
     *
     * @return array
     */
    public function getKeywords($publicationId, $locales = [], $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        $result = [];

        $keywords = $this->build($publicationId, $assocType);
        $submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO'); /** @var SubmissionKeywordEntryDAO $submissionKeywordEntryDao */
        $submissionKeywords = $submissionKeywordEntryDao->getByControlledVocabId($keywords->getId());
        while ($keywordEntry = $submissionKeywords->next()) {
            $keyword = $keywordEntry->getKeyword();
            foreach ($keyword as $locale => $value) {
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
     * Get an array of all of the submission's keywords
     *
     * @return array
     */
    public function getAllUniqueKeywords()
    {
        $result = $this->retrieve('SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', [self::CONTROLLED_VOCAB_SUBMISSION_KEYWORD]);

        $keywords = [];
        foreach ($result as $row) {
            $keywords[] = $row->setting_value;
        }
        return $keywords;
    }

    /**
     * Add an array of keywords
     *
     * @param array $keywords
     * @param int $publicationId
     * @param bool $deleteFirst
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     */
    public function insertKeywords($keywords, $publicationId, $deleteFirst = true, $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        $submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO'); /** @var SubmissionKeywordEntryDAO $submissionKeywordEntryDao */

        if ($deleteFirst) {
            $currentKeywords = $this->deleteByPublicationId($publicationId);
        } else {
            $currentKeywords = $this->build($publicationId, $assocType);
        }
        if (is_array($keywords)) { // localized, array of arrays
            foreach ($keywords as $locale => $list) {
                if (is_array($list)) {
                    $list = array_unique($list); // Remove any duplicate keywords
                    $i = 1;
                    foreach ($list as $keyword) {
                        $keywordEntry = $submissionKeywordEntryDao->newDataObject();
                        $keywordEntry->setControlledVocabId($currentKeywords->getId());
                        $keywordEntry->setKeyword($keyword, $locale);
                        $keywordEntry->setSequence($i);
                        $i++;
                        $submissionKeywordEntryDao->insertObject($keywordEntry);
                    }
                }
            }
        }
    }

    /**
     * Delete keywords by publication ID
     *
     * @return ControlledVocab Controlled Vocab
     */
    public function deleteByPublicationId($publicationId)
    {
        $keywordDao = DAORegistry::getDAO('SubmissionKeywordDAO'); /** @var SubmissionKeywordDAO $keywordDao */
        $submissionKeywordEntryDao = DAORegistry::getDAO('SubmissionKeywordEntryDAO'); /** @var SubmissionKeywordEntryDAO $submissionKeywordEntryDao */
        $currentKeywords = $this->build($publicationId);

        $existingEntries = $keywordDao->enumerate($currentKeywords->getId(), self::CONTROLLED_VOCAB_SUBMISSION_KEYWORD);
        foreach ($existingEntries as $id => $entry) {
            $entry = trim($entry);
            $entryObj = $submissionKeywordEntryDao->getById($id);
            $submissionKeywordEntryDao->deleteObjectById($id);
        }

        return $currentKeywords;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionKeywordDAO', '\SubmissionKeywordDAO');
    define('CONTROLLED_VOCAB_SUBMISSION_KEYWORD', SubmissionKeywordDAO::CONTROLLED_VOCAB_SUBMISSION_KEYWORD);
}

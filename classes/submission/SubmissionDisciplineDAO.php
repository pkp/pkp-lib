<?php

/**
 * @file classes/submission/SubmissionDisciplineDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDisciplineDAO
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned
 * disciplines
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabDAO;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

class SubmissionDisciplineDAO extends ControlledVocabDAO
{
    public const CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE = 'submissionDiscipline';

    /**
     * Build/fetch a publication's discipline controlled vocabulary.
     *
     * @param int $publicationId
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     *
     * @return ControlledVocab
     */
    public function build($publicationId, $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        return parent::_build(self::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE, $assocType, $publicationId);
    }

    /**
     * Get the list of localized additional fields to store.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['submissionDiscipline'];
    }

    /**
     * Get disciplines for a submission.
     *
     * @param int $publicationId
     * @param array $locales
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
     *
     * @return array
     */
    public function getDisciplines($publicationId, $locales = [], $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        $result = [];

        $disciplines = $this->build($publicationId, $assocType);
        $submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /** @var SubmissionDisciplineEntryDAO $submissionDisciplineEntryDao */
        $submissionDisciplines = $submissionDisciplineEntryDao->getByControlledVocabId($disciplines->getId());
        while ($disciplineEntry = $submissionDisciplines->next()) {
            $discipline = $disciplineEntry->getDiscipline();
            foreach ($discipline as $locale => $value) {
                if (empty($locales) || in_array($locale, $locales)) {
                    $result[$locale][] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get an array of all of the submission's disciplines
     *
     * @return array
     */
    public function getAllUniqueDisciplines()
    {
        $result = $this->retrieve('SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', [self::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE]);

        $disciplines = [];
        foreach ($result as $row) {
            $disciplines[] = $row->setting_value;
        }
        return $disciplines;
    }

    /**
     * Add an array of disciplines
     *
     * @param array $disciplines
     * @param int $publicationId
     * @param bool $deleteFirst
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     */
    public function insertDisciplines($disciplines, $publicationId, $deleteFirst = true, $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        $disciplineDao = DAORegistry::getDAO('SubmissionDisciplineDAO'); /** @var SubmissionDisciplineDAO $disciplineDao */
        $submissionDisciplineEntryDao = DAORegistry::getDAO('SubmissionDisciplineEntryDAO'); /** @var SubmissionDisciplineEntryDAO $submissionDisciplineEntryDao */
        $currentDisciplines = $this->build($publicationId, $assocType);

        if ($deleteFirst) {
            $existingEntries = $disciplineDao->enumerate($currentDisciplines->getId(), self::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE);

            foreach ($existingEntries as $id => $entry) {
                $entry = trim($entry);
                $submissionDisciplineEntryDao->deleteObjectById($id);
            }
        }
        if (is_array($disciplines)) { // localized, array of arrays

            foreach ($disciplines as $locale => $list) {
                if (is_array($list)) {
                    $list = array_unique($list); // Remove any duplicate keywords
                    $i = 1;
                    foreach ($list as $discipline) {
                        $disciplineEntry = $submissionDisciplineEntryDao->newDataObject();
                        $disciplineEntry->setControlledVocabId($currentDisciplines->getId());
                        $disciplineEntry->setDiscipline($discipline, $locale);
                        $disciplineEntry->setSequence($i);
                        $i++;
                        $submissionDisciplineEntryDao->insertObject($disciplineEntry);
                    }
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionDisciplineDAO', '\SubmissionDisciplineDAO');
    define('CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE', SubmissionDisciplineDAO::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE);
}

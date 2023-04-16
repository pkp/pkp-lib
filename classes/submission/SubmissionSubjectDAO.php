<?php

/**
 * @file classes/submission/SubmissionSubjectDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubjectDAO
 *
 * @ingroup submission
 *
 * @see Submission
 *
 * @brief Operations for retrieving and modifying a submission's assigned subjects
 */

namespace PKP\submission;

use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabDAO;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

class SubmissionSubjectDAO extends ControlledVocabDAO
{
    public const CONTROLLED_VOCAB_SUBMISSION_SUBJECT = 'submissionSubject';

    /**
     * Build/fetch and return a controlled vocabulary for subjects.
     *
     * @param int $publicationId
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     *
     * @return ControlledVocab
     */
    public function build($publicationId, $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        // may return an array of ControlledVocabs
        return parent::_build(SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT, $assocType, $publicationId);
    }

    /**
     * Get the list of localized additional fields to store.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return ['submissionSubject'];
    }

    /**
     * Get Subjects for a submission.
     *
     * @param int $publicationId
     * @param array $locales
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
     *
     * @return array
     */
    public function getSubjects($publicationId, $locales = [], $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        $result = [];

        $subjects = $this->build($publicationId, $assocType);
        $submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO'); /** @var SubmissionSubjectEntryDAO $submissionSubjectEntryDao */
        $submissionSubjects = $submissionSubjectEntryDao->getByControlledVocabId($subjects->getId());
        /** @var SubmissionSubject */
        foreach ($submissionSubjects->toIterator() as $subjectEntry) {
            $subject = $subjectEntry->getSubject();
            foreach ($subject as $locale => $value) {
                if (empty($locales) || in_array($locale, $locales)) {
                    $result[$locale][] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get an array of all of the submission's Subjects
     *
     * @return array
     */
    public function getAllUniqueSubjects()
    {
        $result = $this->retrieve('SELECT DISTINCT setting_value FROM controlled_vocab_entry_settings WHERE setting_name = ?', [SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT]);

        $subjects = [];
        foreach ($result as $row) {
            $subjects[] = $row->setting_value;
        }
        return $subjects;
    }

    /**
     * Add an array of subjects
     *
     * @param array $subjects
     * @param int $publicationId
     * @param bool $deleteFirst
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     */
    public function insertSubjects($subjects, $publicationId, $deleteFirst = true, $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION)
    {
        $subjectDao = DAORegistry::getDAO('SubmissionSubjectDAO'); /** @var SubmissionSubjectDAO $subjectDao */
        $submissionSubjectEntryDao = DAORegistry::getDAO('SubmissionSubjectEntryDAO'); /** @var SubmissionSubjectEntryDAO $submissionSubjectEntryDao */
        $currentSubjects = $this->build($publicationId, $assocType);

        if ($deleteFirst) {
            $existingEntries = $subjectDao->enumerate($currentSubjects->getId(), SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT);

            foreach ($existingEntries as $id => $entry) {
                $entry = trim($entry);
                $submissionSubjectEntryDao->deleteObjectById($id);
            }
        }
        if (is_array($subjects)) { // localized, array of arrays
            foreach ($subjects as $locale => $list) {
                if (is_array($list)) {
                    $list = array_unique($list); // Remove any duplicate Subjects
                    $i = 1;
                    foreach ($list as $subject) {
                        $subjectEntry = $submissionSubjectEntryDao->newDataObject();
                        $subjectEntry->setControlledVocabId($currentSubjects->getId());
                        $subjectEntry->setSubject($subject, $locale);
                        $subjectEntry->setSequence($i);
                        $i++;
                        $submissionSubjectEntryDao->insertObject($subjectEntry);
                    }
                }
            }
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\SubmissionSubjectDAO', '\SubmissionSubjectDAO');
    define('CONTROLLED_VOCAB_SUBMISSION_SUBJECT', SubmissionSubjectDAO::CONTROLLED_VOCAB_SUBMISSION_SUBJECT);
}

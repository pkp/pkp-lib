<?php

/**
 * @file classes/submission/SubmissionSubjectVocab.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubjectVocab
 *
 * @brief Operations for retrieving and modifying a submission's assigned subjects
 */

namespace PKP\submission;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\PKPApplication;

class SubmissionSubjectVocab extends ControlledVocab
{
    public const CONTROLLED_VOCAB_SUBMISSION_SUBJECT = 'submissionSubject';

    /**
     * Get the list of localized additional fields to store.
     */
    public function scopeGetLocaleFieldNames(): array
    {
        return ['submissionSubject'];
    }

    /**
     * Get Subjects for a submission.
     *
     * @param int $publicationId
     * @param array $locales
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
     */
    public function scopeGetSubjects(
        Builder $query,
        int $publicationId,
        array $locales = [],
        int $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION
    ): array
    {
        return Repo::controlledVocab()->getBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
            $assocType,
            $publicationId,
            $locales
        );
    }

    /**
     * Get an array of all of the submission's Subjects
     */
    public function scopeGetAllUniqueSubjects(Builder $query): array
    {
        return Repo::controlledVocab()->getAllUniqueBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_SUBJECT
        );
    }

    /**
     * Add an array of subjects
     *
     * @param array $subjects
     * @param int $publicationId
     * @param bool $deleteFirst
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     */
    public function scopeInsertSubjects(
        Builder $query,
        array $subjects,
        int $publicationId,
        bool $deleteFirst = true,
        int $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION
    ): void
    {
        Repo::controlledVocab()->insertBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
            $subjects,
            $assocType,
            $publicationId,
            $deleteFirst
        );
    }
}

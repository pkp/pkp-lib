<?php

/**
 * @file classes/submission/SubmissionDisciplineVocab.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionDisciplineVocab
 *
 * @brief Operations for retrieving and modifying a submission's assigned disciplines
 * 
 */

namespace PKP\submission;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\PKPApplication;

class SubmissionDisciplineVocab extends ControlledVocab
{
    public const CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE = 'submissionDiscipline';

    /**
     * Get the list of localized additional fields to store.
     */
    public function scopeGetLocaleFieldNames(Builder $query): array
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
    public function scopeGetDisciplines(
        Builder $query,
        int $publicationId,
        array $locales = [],
        int $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION
    ): array
    {
        return Repo::controlledVocab()->getBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
            $assocType,
            $publicationId,
            $locales
        );
    }

    /**
     * Get an array of all of the submission's disciplines     
     */
    public function scopeGetAllUniqueDisciplines(Builder $query): array
    {
        return Repo::controlledVocab()->getAllUniqueBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE
        );
    }

    /**
     * Add an array of disciplines
     *
     * @param array $disciplines
     * @param int $publicationId
     * @param bool $deleteFirst
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     */
    public function scopeInsertDisciplines(
        Builder $query,
        array $disciplines,
        int $publicationId,
        bool $deleteFirst = true,
        int $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION): void
    {
        Repo::controlledVocab()->insertBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
            $disciplines,
            $assocType,
            $publicationId,
            $deleteFirst
        );
    }
}

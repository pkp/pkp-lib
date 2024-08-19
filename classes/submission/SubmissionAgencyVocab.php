<?php

/**
 * @file classes/submission/SubmissionAgencyVocab.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAgencyVocab
 *
 * @brief Operations for retrieving and modifying a submission's assigned agencies
 */

namespace PKP\submission;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\PKPApplication;


class SubmissionAgencyVocab extends ControlledVocab
{
    public const CONTROLLED_VOCAB_SUBMISSION_AGENCY = 'submissionAgency';

    /**
     * Get the list of localized additional fields to store.
     */
    public function scopeGetLocaleFieldNames(Builder $query): array
    {
        return ['submissionAgency'];
    }

    /**
     * Get agencies for a specified submission ID.
     *
     * @param int $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#6213
     * 
     */
    public function scopeGetAgencies(
        Builder $query,
        int $publicationId,
        array $locales = [],
        int $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION
    ): array
    {
        return Repo::controlledVocab()->getBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_AGENCY,
            $assocType,
            $publicationId,
            $locales
        );
    }

    /**
     * Get an array of all of the submission's agencies
     */
    public function scopeGetAllUniqueAgencies(Builder $query):array
    {
        return Repo::controlledVocab()->getAllUniqueBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_AGENCY
        );
    }

    /**
     * Add an array of agencies
     *
     * @param array $agencies List of agencies.
     * @param int   $publicationId Submission ID.
     * @param bool  $deleteFirst True iff existing agencies should be removed first.
     * @param int   $assocType DO NOT USE: For <3.1 to 3.x migration pkp/pkp-lib#3572 pkp/pkp-lib#6213
     */
    public function scopeInsertAgencies(
        Builder $query,
        array $agencies,
        int $publicationId,
        bool $deleteFirst = true,
        int $assocType = PKPApplication::ASSOC_TYPE_PUBLICATION
    ): void
    {
        Repo::controlledVocab()->insertBySymbolic(
            static::CONTROLLED_VOCAB_SUBMISSION_AGENCY,
            $agencies,
            $assocType,
            $publicationId,
            $deleteFirst
        );
    }   
}

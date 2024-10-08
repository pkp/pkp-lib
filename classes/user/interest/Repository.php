<?php

/**
 * @file classes/user/userInterest/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to user interest
 */

namespace PKP\user\interest;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\user\interest\UserInterest;
use PKP\controlledVocab\ControlledVocabEntry;
use Throwable;

class Repository
{
    /**
     * Update a user's set of interests
     */
    public function setUserInterests(array $interests, int $userId): void
    {
        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST
        );

        $currentInterests = ControlledVocabEntry::query()
            ->whereHas(
                'controlledVocab',
                fn($query) => $query
                    ->withSymbolic(UserInterest::CONTROLLED_VOCAB_INTEREST)
                    ->withAssoc(0, 0)
            )
            ->withLocale('')
            ->withSetting(UserInterest::CONTROLLED_VOCAB_INTEREST, $interests)
            ->get();
        
        try {

            DB::beginTransaction();

            // Delete the existing interests association.
            UserInterest::query()->withUserId($userId)->delete();
            
            $newInterestIds = collect(
                    array_diff(
                        $interests,
                        $currentInterests->pluck(UserInterest::CONTROLLED_VOCAB_INTEREST)->toArray()
                    )
                )
                ->map(fn (string $interest): string => trim($interest))
                ->unique()
                ->map(
                    fn (string $interest) => ControlledVocabEntry::create([
                        'controlledVocabId' => $controlledVocab->id,
                        UserInterest::CONTROLLED_VOCAB_INTEREST => [
                            '' => $interest
                        ],
                    ])->id
                );
            
            // TODO: Investigate the impact of applied patch from https://github.com/pkp/pkp-lib/issues/10423
            collect($currentInterests->pluck('id'))
                ->merge($newInterestIds)
                ->each(fn ($interestId) => UserInterest::create([
                    'userId' => $userId,
                    'controlledVocabEntryId' => $interestId,
                ]));
            
            Repo::controlledVocab()->resequence($controlledVocab->id);
            
            DB::commit();

        } catch (Throwable $exception) {

            DB::rollBack();

            throw $exception;
        }
    }
}

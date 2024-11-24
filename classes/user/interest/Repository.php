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
use PKP\user\User;
use PKP\user\interest\UserInterest;
use PKP\controlledVocab\ControlledVocabEntry;

class Repository
{
    /**
     * Get all interests for all users in the system
     */
    public function getAllInterests(?string $filter = null): array
    {
        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST,
            UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_TYPE,
            UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_ID,
            UserInterest::CONTROLLED_VOCAB_INTEREST_CONTEXT_ID
        );

        return ControlledVocabEntry::query()
            ->withControlledVocabId($controlledVocab->id)
            ->when(
                $filter,
                fn ($query) => $query->withSetting(
                    UserInterest::CONTROLLED_VOCAB_INTEREST,
                    $filter
                )
            )
            ->get()
            ->sortBy(UserInterest::CONTROLLED_VOCAB_INTEREST)
            ->pluck(UserInterest::CONTROLLED_VOCAB_INTEREST)
            ->toArray();
    }

    /**
     * Get user reviewing interests. (Cached in memory for batch fetches.)
     */
    public function getInterestsForUser(User $user): array
    {
        return ControlledVocabEntry::query()
            ->whereHas(
                "controlledVocab",
                fn ($query) => $query
                    ->withSymbolic(UserInterest::CONTROLLED_VOCAB_INTEREST)
                    ->withAssoc(
                        UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_TYPE,
                        UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_ID
                    )
            )
            ->whereHas("userInterest", fn ($query) => $query->withUserId($user->getId()))
            ->get()
            ->pluck(UserInterest::CONTROLLED_VOCAB_INTEREST, 'id')
            ->toArray();
    }

    /**
     * Returns a comma separated string of a user's interests
     */
    public function getInterestsString(User $user): string
    {
        $interests = $this->getInterestsForUser($user);

        return implode(', ', $interests);
    }

    /**
     * Set a user's interests
     */
    public function setInterestsForUser(User $user, string|array|null $interests = null): void
    {
        $interests = is_array($interests)
            ? $interests
            : (empty($interests) ? [] : explode(',', $interests));

        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST,
            UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_TYPE,
            UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_ID,
            UserInterest::CONTROLLED_VOCAB_INTEREST_CONTEXT_ID
        );

        $currentInterests = ControlledVocabEntry::query()
            ->whereHas(
                'controlledVocab',
                fn ($query) => $query
                    ->withSymbolic(UserInterest::CONTROLLED_VOCAB_INTEREST)
                    ->withAssoc(
                        UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_TYPE,
                        UserInterest::CONTROLLED_VOCAB_INTEREST_ASSOC_ID
                    )
            )
            ->withLocales([''])
            ->withSettings(UserInterest::CONTROLLED_VOCAB_INTEREST, $interests)
            ->get();
        
        // Delete the existing interests association.
        UserInterest::query()->withUserId($user->getId())->delete();
            
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
        
        collect($currentInterests->pluck('id'))
            ->merge($newInterestIds)
            ->each(fn ($interestId) => UserInterest::create([
                'userId' => $user->getId(),
                'controlledVocabEntryId' => $interestId,
            ]));
        
        Repo::controlledVocab()->resequence($controlledVocab->id);
    }
}
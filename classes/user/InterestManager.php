<?php

/**
 * @file classes/user/InterestManager.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InterestManager
 *
 * @brief Handle user interest functions.
 */

namespace PKP\user;

use APP\facades\Repo;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\user\User;
use PKP\user\interest\UserInterest;

class InterestManager
{
    /**
     * Get all interests for all users in the system
     */
    public function getAllInterests(?string $filter = null): array
    {
        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST
        );

        return ControlledVocabEntry::query()
            ->withControlledVocabId($controlledVocab->id)
            ->when(
                $filter,
                fn($query) => $query->withSetting(
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
                fn($query) => $query
                    ->withSymbolic(UserInterest::CONTROLLED_VOCAB_INTEREST)
                    ->withAssoc(0, 0)
            )
            ->whereHas("userInterest", fn($query) => $query->withUserId($user->getId()))
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

        Repo::userInterest()->setUserInterests($interests, $user->getId());
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\InterestManager', '\InterestManager');
}

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
use PKP\db\DAORegistry;
use PKP\user\User;
use PKP\user\UserInterest;

class InterestManager
{
    /**
     * Get all interests for all users in the system
     */
    public function getAllInterests(?string $filter = null): array
    {
        $interests = UserInterest::getAllInterests($filter);

        $interestReturner = [];
        while ($interest = $interests->next()) {
            $interestReturner[] = $interest->getInterest();
        }

        return $interestReturner;
    }

    /**
     * Get user reviewing interests. (Cached in memory for batch fetches.)
     */
    public function getInterestsForUser(User $user): array
    {
        static $interestsCache = [];
        $interests = [];
        $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /** @var InterestEntryDAO $interestEntryDao */
        $controlledVocab = Repo::controlledVocab()->build(UserInterest::CONTROLLED_VOCAB_INTEREST);

        foreach (UserInterest::getUserInterestIds($user->getId()) as $interestEntryId) {
            /** @var InterestEntry */
            $interestEntry = $interestsCache[$interestEntryId] ??= $interestEntryDao->getById(
                $interestEntryId,
                $controlledVocab->id
            );
            if ($interestEntry) {
                $interests[] = $interestEntry->getInterest();
            }
        }

        return $interests;
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

        Repo::controlledVocab()->setUserInterests($interests, $user->getId());
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\InterestManager', '\InterestManager');
}

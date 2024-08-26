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
 * @brief 
 */

 namespace PKP\user\interest;

use APP\facades\Repo;
use PKP\db\DAORegistry;
use PKP\user\InterestEntry;
use PKP\user\InterestEntryDAO;
use PKP\core\ArrayItemIterator;
use Illuminate\Support\Facades\DB;
use PKP\user\interest\UserInterest;
use Illuminate\Database\Query\JoinClause;

class Repository
{

    /**
     * Get a list of controlled vocabulary entry IDs (corresponding to interest keywords) 
     * attributed to a user
     */
    public function getUserInterestIds(int $userId): array
    {
        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST
        );

        return DB::table('controlled_vocab_entries AS cve')
            ->select(['cve.controlled_vocab_entry_id'])
            ->join(
                'user_interests AS ui', 
                fn (JoinClause $join) => $join
                    ->on('cve.controlled_vocab_entry_id', '=', 'ui.controlled_vocab_entry_id')
                    ->where('ui.user_id', $userId)
            )
            ->where('controlled_vocab_id', $controlledVocab->id)
            ->get()
            ->pluck('controlled_vocab_entry_id')
            ->toArray();
    }

    /**
     * Get a list of user IDs attributed to an interest
     */
    public function getUserIdsByInterest(string $interest): array
    {
        return DB::table('user_interests AS ui')
            ->select('ui.user_id')
            ->join(
                'controlled_vocab_entry_settings AS cves',
                fn (JoinClause $join) => $join
                    ->on('cves.controlled_vocab_entry_id', '=', 'ui.controlled_vocab_entry_id')
                    ->where('cves.setting_name', UserInterest::CONTROLLED_VOCAB_INTEREST)
                    ->where(DB::raw('LOWER(cves.setting_value)'), trim(strtolower($interest)))
            )
            ->get()
            ->pluck('user_id')
            ->toArray();
    }


    /**
     * Get all user's interests
     */
    public function getAllInterests(?string $filter = null): object
    {
        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST
        );

        $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /** @var InterestEntryDAO $interestEntryDao */
        $iterator = $interestEntryDao->getByControlledVocabId($controlledVocab->id, null, $filter);

        // Sort by name.
        $interests = $iterator->toArray();
        usort($interests, function ($s1, $s2) {
            return strcmp($s1->getInterest(), $s2->getInterest());
        });

        // Turn back into an iterator.
        return new ArrayItemIterator($interests);
    }

    /**
     * Update a user's set of interests
     */
    public function setUserInterests(array $interests, int $userId): void
    {
        $controlledVocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST
        );

        /** @var InterestEntryDAO $interestEntryDao */
        $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO');

        DB::beginTransaction();

        // Delete the existing interests association.
        UserInterest::withUserId($userId)->delete();

        collect($interests)
            ->map(fn (string $interest): string => trim($interest))
            ->unique()
            ->each(function (string $interest) use ($controlledVocab, $interestEntryDao, $userId): void {
                $interestEntry = $interestEntryDao->getBySetting(
                    $interest,
                    $controlledVocab->symbolic,
                    $controlledVocab->assocId,
                    $controlledVocab->assocType,
                    $controlledVocab->symbolic
                );

                if (!$interestEntry) {
                    $interestEntry = $interestEntryDao->newDataObject(); /** @var InterestEntry $interestEntry */
                    $interestEntry->setInterest($interest);
                    $interestEntry->setControlledVocabId($controlledVocab->id);
                    $interestEntry->setId($interestEntryDao->insertObject($interestEntry));
                }

                UserInterest::create([
                    'userId' => $userId,
                    'controlledVocabEntryId' => $interestEntry->getId(),
                ]);
            });
        
        DB::commit();
    }
}

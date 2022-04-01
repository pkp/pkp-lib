<?php

/**
 * @file classes/user/InterestDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InterestDAO
 * @ingroup user
 *
 * @see User
 *
 * @brief Operations for retrieving and modifying a user's review interests.
 */

namespace PKP\user;

use PKP\controlledVocab\ControlledVocabDAO;
use PKP\core\ArrayItemIterator;
use PKP\db\DAORegistry;

class InterestDAO extends ControlledVocabDAO
{
    public const CONTROLLED_VOCAB_INTEREST = 'interest';
    /**
     * Create or return the Controlled Vocabulary for interests
     *
     * @return ControlledVocab
     */
    public function build()
    {
        return parent::_build(self::CONTROLLED_VOCAB_INTEREST);
    }

    /**
     * Get a list of controlled vocabulary entry IDs (corresponding to interest keywords) attributed to a user
     *
     * @param int $userId
     *
     * @return array
     */
    public function getUserInterestIds($userId)
    {
        $controlledVocab = $this->build();
        $result = $this->retrieveRange(
            'SELECT cve.controlled_vocab_entry_id FROM controlled_vocab_entries cve, user_interests ui WHERE cve.controlled_vocab_id = ? AND ui.controlled_vocab_entry_id = cve.controlled_vocab_entry_id AND ui.user_id = ?',
            [(int) $controlledVocab->getId(), (int) $userId]
        );

        $ids = [];
        foreach ($result as $row) {
            $ids[] = $row->controlled_vocab_entry_id;
        }
        return $ids;
    }

    /**
     * Get a list of user IDs attributed to an interest
     *
     * @return array
     */
    public function getUserIdsByInterest($interest)
    {
        $result = $this->retrieve(
            '
			SELECT ui.user_id
			FROM user_interests ui
				INNER JOIN controlled_vocab_entry_settings cves ON (ui.controlled_vocab_entry_id = cves.controlled_vocab_entry_id)
			WHERE cves.setting_name = ? AND cves.setting_value = ?',
            ['interest', $interest]
        );

        $returner = [];
        foreach ($result as $row) {
            $returner[] = $row->user_id;
        }
        return $returner;
    }

    /**
     * Get all user's interests
     *
     * @param string $filter (optional)
     *
     * @return object
     */
    public function getAllInterests($filter = null)
    {
        $controlledVocab = $this->build();
        $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /** @var InterestEntryDAO $interestEntryDao */
        $iterator = $interestEntryDao->getByControlledVocabId($controlledVocab->getId(), null, $filter);

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
     *
     * @param array $interests
     * @param int $userId
     */
    public function setUserInterests($interests, $userId)
    {
        // Remove duplicates
        $interests ??= [];
        $interests = array_unique($interests);

        // Trim whitespace
        $interests = array_map('trim', $interests);

        // Delete the existing interests association.
        $this->update(
            'DELETE FROM user_interests WHERE user_id = ?',
            [(int) $userId]
        );

        $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /** @var InterestEntryDAO $interestEntryDao */
        $controlledVocab = $this->build();

        // Store the new interests.
        foreach ((array) $interests as $interest) {
            $interestEntry = $interestEntryDao->getBySetting(
                $interest,
                $controlledVocab->getSymbolic(),
                $controlledVocab->getAssocId(),
                $controlledVocab->getAssocType(),
                $controlledVocab->getSymbolic()
            );

            if (!$interestEntry) {
                $interestEntry = $interestEntryDao->newDataObject(); /** @var InterestEntry $interestEntry */
                $interestEntry->setInterest($interest);
                $interestEntry->setControlledVocabId($controlledVocab->getId());
                $interestEntry->setId($interestEntryDao->insertObject($interestEntry));
            }

            $this->update(
                'INSERT INTO user_interests (user_id, controlled_vocab_entry_id) VALUES (?, ?)',
                [(int) $userId, (int) $interestEntry->getId()]
            );
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\InterestDAO', '\InterestDAO');
    define('CONTROLLED_VOCAB_INTEREST', \InterestDAO::CONTROLLED_VOCAB_INTEREST);
}

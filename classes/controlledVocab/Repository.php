<?php

namespace PKP\controlledVocab;

use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\user\UserInterest;
use PKP\user\InterestEntry;
use PKP\user\InterestEntryDAO;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntryDAO;

class Repository
{
    /**
     * Fetch a Controlled Vocab by symbolic info, building it if needed.
     */
    public function build(string $symbolic, int $assocType = 0, int $assocId = 0): ControlledVocab
    {
        return ControlledVocab::withSymbolic($symbolic)
            ->withAssoc($assocType, $assocId)
            ->firstOr(fn() => ControlledVocab::create([
                'assocType' => $assocType,
                'assocId' => $assocId,
                'symbolic' => $symbolic,
            ]));
    }

    /**
     * Return the Controlled Vocab Entry DAO for this Controlled Vocab.
     * Can be subclassed to provide extended DAOs.
     * 
     * Will be removed once the eloquent based settings table relations task completes.
     */
    public function getEntryDAO(): ControlledVocabEntryDAO
    {
        return DAORegistry::getDAO('ControlledVocabEntryDAO');
    }

    public function getEntryDaoBySymbolic(string $symbolic): ControlledVocabEntryDAO
    {
        return DAORegistry::getDAO(ucfirst($symbolic) . 'EntryDAO');
    }

    public function getBySymbolic(
        string $symbolic,
        int $assocType,
        int $assocId,
        array $locales = [],
        ?string $entryDaoClassName = null
    ): array
    {
        $result = [];

        $controlledVocab = $this->build($symbolic, $assocType, $assocId);
        
        /** @var  ControlledVocabEntryDAO $entryDao */
        $entryDao = $entryDaoClassName
            ? DAORegistry::getDAO($entryDaoClassName)
            : $this->getEntryDaoBySymbolic($symbolic);

        $controlledVocabEntries = $entryDao->getByControlledVocabId($controlledVocab->id);

        while ($vocabEntry = $controlledVocabEntries->next()) {
            $vocabs = $vocabEntry->getData($symbolic);
            foreach ($vocabs as $locale => $value) {
                if (empty($locales) || in_array($locale, $locales)) {
                    $result[$locale][] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get an array of all of the vocabs for given symbolic
     */
    public function getAllUniqueBySymbolic(string $symbolic): array
    {
        return DB::table('controlled_vocab_entry_settings')
            ->select('setting_value')
            ->where('setting_name', $symbolic)
            ->distinct()
            ->get()
            ->pluck('setting_value')
            ->toArray();
    }

    /**
     * Add an array of vocabs
     */
    public function insertBySymbolic(
        string $symbolic,
        array $vocabs,
        int $assocType,
        int $assocId,
        bool $deleteFirst = true,
        ?string $entryDaoClassName = null
    ): void
    {
        /** @var  ControlledVocabEntryDAO $entryDao */
        $entryDao = $entryDaoClassName
            ? DAORegistry::getDAO($entryDaoClassName)
            : $this->getEntryDaoBySymbolic($symbolic);

        $currentControlledVocab = $this->build($symbolic, $assocType, $assocId);

        if ($deleteFirst) {
            collect($currentControlledVocab->enumerate( $symbolic))
                ->keys()
                ->each(fn (int $id) => $entryDao->deleteObjectById($id));
        }

        if (is_array($vocabs)) { // localized, array of arrays
            foreach ($vocabs as $locale => $list) {
                if (is_array($list)) {
                    $list = array_unique($list); // Remove any duplicate keywords
                    $i = 1;
                    foreach ($list as $vocab) {
                        $vocabEntry = $entryDao->newDataObject();
                        $vocabEntry->setControlledVocabId($currentControlledVocab->id);
                        $vocabEntry->setData($symbolic, $vocab, $locale);
                        $vocabEntry->setSequence($i);
                        $i++;
                        $entryDao->insertObject($vocabEntry);
                    }
                }
            }
        }
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

<?php

/**
 * @file classes/controlledVocab/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to controlled vocab
 */

namespace PKP\controlledVocab;

use APP\core\Application;
use Illuminate\Support\Arr;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\publication\PKPPublication;

class Repository
{
    const AS_ENTRY_DATA = true;

    /**
     * Fetch a Controlled Vocab by symbolic info, building it if needed.
     */
    public function build(
        string $symbolic,
        int $assocType,
        ?int $assocId
    ): ControlledVocab
    {
        return ControlledVocab::query()
            ->withSymbolics([$symbolic])
            ->withAssoc($assocType, $assocId)
            ->firstOr(fn() => ControlledVocab::create([
                'assocType' => $assocType,
                'assocId' => $assocId,
                'symbolic' => $symbolic,
            ]));
    }

    /**
     * Get localized entry data
     */
    public function getBySymbolic(
        string $symbolic,
        int $assocType,
        ?int $assocId,
        ?array $locales = [],
        bool $asEntryData = !Repository::AS_ENTRY_DATA
    ): array
    {
        $result = [];

        ControlledVocabEntry::query()
            ->whereHas(
                'controlledVocab',
                fn ($query) => $query->withSymbolics([$symbolic])->withAssoc($assocType, $assocId)
            )
            ->when(!empty($locales), fn ($query) => $query->withLocales($locales))
            ->get()
            ->each(function ($entry) use (&$result, $asEntryData) {
                foreach ($entry->name as $locale => $value) {
                    $result[$locale][] = $asEntryData ? $entry->getEntryData($locale) : $value;
                }
            });
        
        return $result;
    }

    /**
     * Add an array of vocabs
     */
    public function insertBySymbolic(
        string $symbolic,
        array $vocabs,
        int $assocType,
        ?int $assocId,
        bool $deleteFirst = true,
    ): void
    {
        $controlledVocab = $this->build($symbolic, $assocType, $assocId);
        $controlledVocabEntry = new ControlledVocabEntry;
        $controlledVocabEntrySettings = $controlledVocabEntry->getSettings();
        $multilingualProps = array_flip($controlledVocabEntry->getMultilingualProps());
        $idKey = ControlledVocabEntry::CONTROLLED_VOCAB_ENTRY_IDENTIFIER;
        $srcKey = ControlledVocabEntry::CONTROLLED_VOCAB_ENTRY_SOURCE;

        if ($deleteFirst) {
            ControlledVocabEntry::query()->withControlledVocabId($controlledVocab->id)->delete();
        }

        collect($vocabs)
            ->each(
                fn (array|string $entries, string $locale) => collect(array_values(Arr::wrap($entries)))
                    ->reject(fn (string|array $vocab) => is_array($vocab) && isset($vocab[$idKey]) && !isset($vocab[$srcKey])) // Remove vocabs that have id but not source
                    ->unique(fn (string|array $vocab): string => ($vocab[$idKey] ?? '') . ($vocab[$srcKey] ?? '') . ($vocab['name'] ?? $vocab))
                    ->each(
                        fn (array|string $vocab, int $index) => 
                            ControlledVocabEntry::create([
                                'controlledVocabId' => $controlledVocab->id,
                                'seq' => $index + 1,
                                ...is_array($vocab)
                                    ? collect($vocab)
                                        ->only($controlledVocabEntrySettings)
                                        ->whereNotNull()
                                        ->map(fn ($prop, string $propName) => isset($multilingualProps[$propName])
                                            ? [$locale => $prop]
                                            : $prop
                                        )
                                        ->toArray()
                                    : ['name' => [$locale => $vocab]],
                            ]) 
                    )
            );

        // Only resequence at the time of insert when `$deleteFirst` set to false
        // Otherwise all existing data will be deleted and sequenced at the time of insertion.
        if (!$deleteFirst) {
            $this->resequence($controlledVocab->id);
        }
    }

    /**
     * Resequence controlled vocab entries for a given controlled vocab id
     */
    public function resequence(int $controlledVocabId): void
    {
        $seq = 1;

        ControlledVocabEntry::query()
            ->withControlledVocabId($controlledVocabId)
            ->each(function ($controlledVocabEntry) use (&$seq) {
                $controlledVocabEntry->update([
                    'seq' => $seq++,
                ]);
            });
    }

    /**
     * Hydrate controlled vocab entries as entry data for a publication which will
     * include other meta information(e.g. source & identifier) in vocabs
     */
    public function hydrateVocabsAsEntryData(PKPPublication $publication): PKPPublication
    {
        $mappings = [
            'keywords' => ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            'subjects' => ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
            'disciplines' => ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
            'supportingAgencies' => ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_AGENCY,
        ];

        foreach ($mappings as $dataKey => $symbolic) {
            if (empty($publication->getData($dataKey))) {
                continue;
            }
            $publication->setData(
                $dataKey,
                $this->getBySymbolic(
                    $symbolic,
                    Application::ASSOC_TYPE_PUBLICATION,
                    $publication->getId(),
                    [],
                    static::AS_ENTRY_DATA
                )
            );
        }

        return $publication;
    }
}

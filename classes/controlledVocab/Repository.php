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

use Illuminate\Support\Arr;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntry;

class Repository
{
    /**
     * Fetch a Controlled Vocab by symbolic info, building it if needed.
     */
    public function build(
        string $symbolic,
        int $assocType,
        int $assocId
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
        int $assocId,
        ?array $locales = []
    ): array
    {
        $result = [];

        ControlledVocabEntry::query()
            ->whereHas(
                "controlledVocab",
                fn ($query) => $query->withSymbolics([$symbolic])->withAssoc($assocType, $assocId)
            )
            ->when(!empty($locales), fn ($query) => $query->withLocales($locales))
            ->get()
            ->each(function ($entry) use (&$result, $symbolic) {
                foreach ($entry->{$symbolic} as $locale => $value) {
                    $result[$locale][] = $value;
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
        int $assocId,
        bool $deleteFirst = true,
    ): void
    {
        $controlledVocab = $this->build($symbolic, $assocType, $assocId);
        $controlledVocab->load('controlledVocabEntries');

        if ($deleteFirst) {
            ControlledVocabEntry::query()
                ->whereIn(
                    (new ControlledVocabEntry)->getKeyName(),
                    $controlledVocab->controlledVocabEntries->pluck('id')->toArray()
                )
                ->delete();
        }

        collect($vocabs)
            ->each(
                fn (array|string $entries, string $locale) => collect(array_values(Arr::wrap($entries)))
                    ->each(
                        fn (string $vocab, int $index) => 
                            ControlledVocabEntry::create([
                                'controlledVocabId' => $controlledVocab->id,
                                'seq' => $index + 1,
                                "{$symbolic}" => [
                                    $locale => $vocab
                                ],
                            ]) 
                    )
            );
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
}

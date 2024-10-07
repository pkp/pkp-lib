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
use Illuminate\Support\Facades\DB;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntry;
use Throwable;

class Repository
{
    /**
     * Fetch a Controlled Vocab by symbolic info, building it if needed.
     */
    public function build(string $symbolic, int $assocType = 0, int $assocId = 0): ControlledVocab
    {
        return ControlledVocab::query()
            ->withSymbolic($symbolic)
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
        array $locales = []
    ): array
    {
        $result = [];

        ControlledVocabEntry::query()
            ->whereHas(
                "controlledVocab",
                fn($query) => $query->withSymbolic($symbolic)->withAssoc($assocType, $assocId)
            )
            ->withLocale($locales)
            ->get()
            ->each(function ($entry) use (&$result, $symbolic) {
                foreach ($entry->{$symbolic} as $locale => $value) {
                    $result[$locale][] = $value;
                }
            });
        
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
    ): bool
    {
        $controlledVocab = $this->build($symbolic, $assocType, $assocId);
        $controlledVocab->load('controlledVocabEntries');

        try {

            DB::beginTransaction();

            if ($deleteFirst) {
                ControlledVocabEntry::whereIn(
                    'id',
                    $controlledVocab->controlledVocabEntries->pluck('id')->toArray()
                )->delete();
            }

            collect($vocabs)
                ->each(
                    fn (array|string $entries, string $locale) => collect(Arr::wrap($entries))
                        ->each(
                            fn (string $vocab, $seq = 1) => 
                                ControlledVocabEntry::create([
                                    'controlledVocabId' => $controlledVocab->id,
                                    'seq' => $seq,
                                    "{$symbolic}" => [
                                        $locale => $vocab
                                    ],
                                ]) 
                        )
                );
            
            // TODO: Should Resequence?
                            
            DB::commit();

            return true;

        } catch (Throwable $exception) {

            DB::rollBack();
        }

        return false;
    }

    /**
     * Resequence controlled vocab entries for a given controlled vocab id
     */
    public function resequence(int $controlledVocabId): void
    {
        ControlledVocabEntry::query()
            ->withControlledVocabId($controlledVocabId)
            ->each(
                fn ($controlledVocabEntry, $seq = 1) => $controlledVocabEntry->update([
                    'seq' => $seq,
                ])
            );
    }
}

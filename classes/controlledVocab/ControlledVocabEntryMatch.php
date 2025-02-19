<?php

/**
 * @file classes/controlledVocab/ControlledVocabEntryMatch.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ControlledVocabEntryMatch
 *
 * @brief Enum class to define how the entry match will be performed (exact or partial match)
 */

namespace PKP\controlledVocab;

use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;

enum ControlledVocabEntryMatch
{
    case EXACT;
    case PARTIAL;

    public function operator(): string
    {
        return match ($this) {
            static::EXACT => '=',
            static::PARTIAL => DB::connection() instanceof PostgresConnection ? 'ILIKE' : 'LIKE'
        };
    }

    public function searchKeyword(string $keyword): string
    {
        return match ($this) {
            static::EXACT => $keyword,
            static::PARTIAL => "%{$keyword}%"
        };
    }
}

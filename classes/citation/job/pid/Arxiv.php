<?php

/**
 * @file classes/citation/job/pid/Arxiv.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Arxiv
 *
 * @ingroup citation
 *
 * @brief Arxiv class
 */

namespace PKP\citation\job\pid;

class Arxiv extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regex = '%\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))%s';

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'https://arxiv.org/abs';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const prefixInCorrect = [
        'arxiv:'
    ];

    /** @copydoc AbstractPid::extractFromString() */
    public static function extractFromString(?string $string): string
    {
        $string = parent::extractFromString($string);

        $class = get_called_class();

        // check if prefix found in extracted string
        $prefixes = $class::prefixInCorrect;
        $prefixes[] = $class::prefix;

        foreach($prefixes as $prefix){
            if(str_contains($string, $prefix)) return $string;
        }

        return '';
    }
}

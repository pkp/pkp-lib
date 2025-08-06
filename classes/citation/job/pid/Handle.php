<?php

/**
 * @file classes/citation/job/pid/Handle.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Handle
 *
 * @ingroup citation
 *
 * @brief Handle class
 */

namespace PKP\citation\job\pid;

class Handle extends BasePid
{
    /** @copydoc AbstractPid::regex */
    public const regex = '%\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))%s';

    /** @copydoc AbstractPid::prefix */
    public const prefix = 'https://hdl.handle.net';

    /** @copydoc AbstractPid::prefixInCorrect */
    public const prefixInCorrect = [
        'handle:'
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

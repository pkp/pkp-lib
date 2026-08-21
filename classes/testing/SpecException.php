<?php

/**
 * @file classes/testing/SpecException.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SpecException
 *
 * @brief A scenario/bootstrap spec is invalid. Carries the dotted key path of
 * the offending spec field so the API can answer 400 with a precise pointer.
 * An unsupported spec key must THROW, never be silently dropped (a
 * silently-ignored key once cost a real investigation — PRINCIPLES
 * D4).
 */

namespace PKP\testing;

class SpecException extends \Exception
{
    public function __construct(
        public readonly string $specKey,
        string $message
    ) {
        parent::__construct($message);
    }
}

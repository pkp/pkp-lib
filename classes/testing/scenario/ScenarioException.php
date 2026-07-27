<?php

/**
 * @file classes/testing/scenario/ScenarioException.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScenarioException
 *
 * @ingroup testing
 *
 * @brief A scenario spec could not be built. Carries the offending spec key so
 *   the test author sees which part of their payload the builder rejected, and
 *   never a silently dropped key.
 */

namespace PKP\testing\scenario;

use Exception;
use Illuminate\Http\Response;

class ScenarioException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $specKey = null,
        public readonly int $status = Response::HTTP_BAD_REQUEST,
        public readonly array $details = [],
    ) {
        parent::__construct($message, $status);
    }

    public function toArray(): array
    {
        return array_filter([
            'error' => $this->getMessage(),
            'specKey' => $this->specKey,
            'details' => $this->details ?: null,
        ], fn ($value) => $value !== null);
    }
}

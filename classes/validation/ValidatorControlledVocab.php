<?php

/**
 * @file classes/validation/ValidatorControlledVocab.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorControlledVocab
 *
 * @ingroup validation
 *
 * @brief Validation check that checks if value is within a certain set retrieved
 *  from the database.
 */

namespace PKP\validation;

use PKP\controlledVocab\ControlledVocab;

class ValidatorControlledVocab extends Validator
{
    public array $acceptedValues;

    /**
     * Constructor
     */
    public function __construct(string $symbolic, int $assocType, int $assocId)
    {
        $controlledVocab = ControlledVocab::withSymbolic($symbolic)
            ->withAssoc($assocType, $assocId)
            ->first();

        $this->acceptedValues = array_keys($controlledVocab?->enumerate() ?? []);
    }

    //
    // Implement abstract methods from Validator
    //
    
    /**
     * Value is valid if it is empty and optional or is in the set of accepted values.
     * @see Validator::isValid()
     */
    public function isValid($value): bool
    {
        return in_array($value, $this->acceptedValues);
    }
}

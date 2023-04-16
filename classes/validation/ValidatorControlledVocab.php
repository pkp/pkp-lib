<?php

/**
 * @file classes/validation/ValidatorControlledVocab.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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

use PKP\db\DAORegistry;

class ValidatorControlledVocab extends Validator
{
    /** @var array */
    public $_acceptedValues;

    /**
     * Constructor.
     *
     * @param string $symbolic
     * @param int $assocType
     * @param int $assocId
     */
    public function __construct($symbolic, $assocType, $assocId)
    {
        $controlledVocabDao = DAORegistry::getDAO('ControlledVocabDAO'); /** @var ControlledVocabDAO $controlledVocabDao */
        $controlledVocab = $controlledVocabDao->getBySymbolic($symbolic, $assocType, $assocId);
        if ($controlledVocab) {
            $this->_acceptedValues = array_keys($controlledVocab->enumerate());
        } else {
            $this->_acceptedValues = [];
        }
    }


    //
    // Implement abstract methods from Validator
    //
    /**
     * @see Validator::isValid()
     * Value is valid if it is empty and optional or is in the set of accepted values.
     *
     * @return bool
     */
    public function isValid($value)
    {
        return in_array($value, $this->_acceptedValues);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\validation\ValidatorControlledVocab', '\ValidatorControlledVocab');
}

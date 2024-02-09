<?php

/**
 * @file classes/validation/ValidatorTypeDescription.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorTypeDescription
 *
 * @ingroup filter
 *
 * @brief Class that describes a string input/output type that passes
 *  additional validation (via standard validator classes).
 */

namespace PKP\validation;

use Illuminate\Support\Str;
use PKP\filter\PrimitiveTypeDescription;

class ValidatorTypeDescription extends PrimitiveTypeDescription
{
    /** @var string the validator class name */
    public $_validatorClassName;

    /** @var array arguments to be passed to the validator constructor */
    public $_validatorArgs;


    //
    // Setters and Getters
    //
    /**
     * @see TypeDescription::getNamespace()
     */
    public function getNamespace()
    {
        return \PKP\filter\TypeDescriptionFactory::TYPE_DESCRIPTION_NAMESPACE_VALIDATOR;
    }


    //
    // Implement abstract template methods from TypeDescription
    //
    /**
     * @see TypeDescription::parseTypeName()
     */
    public function parseTypeName($typeName)
    {
        // Standard validators are based on string input.
        parent::parseTypeName('string');

        // Split the type name into validator name and arguments.
        $typeNameParts = explode('(', $typeName, 2);
        switch (count($typeNameParts)) {
            case 1:
                // no argument
                $this->_validatorArgs = [];
                break;

            case 2:
                // parse arguments (no UTF8-treatment necessary)
                if (substr($typeNameParts[1], -1) != ')') {
                    return false;
                }
                $validatorArgsPart = substr($typeNameParts[1], 0, -1);
                $this->_validatorArgs = json_decode('[' . $validatorArgsPart . ']');
                break;
        }

        // Validator name must start with a lower case letter
        // and may contain only alphanumeric letters.
        if (!preg_match('/^[a-z][a-zA-Z0-9]+$/', $typeNameParts[0])) {
            return false;
        }

        // Translate the validator name into a validator class name.
        $this->_validatorClassName = 'Validator' . Str::ucfirst($typeNameParts[0]);

        return true;
    }

    /**
     * @see TypeDescription::checkType()
     */
    public function checkType($object)
    {
        // Check primitive type.
        if (!parent::checkType($object)) {
            return false;
        }

        // Instantiate and call validator
        $validatorFQCN = '\PKP\validation\\' . $this->_validatorClassName;
        assert(class_exists($validatorFQCN));
        $validator = new $validatorFQCN(...$this->_validatorArgs);
        assert($validator instanceof \PKP\validation\Validator);

        // Validate the object
        if (!$validator->isValid($object)) {
            return false;
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\validation\ValidatorTypeDescription', '\ValidatorTypeDescription');
}

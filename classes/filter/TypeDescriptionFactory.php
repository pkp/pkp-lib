<?php

/**
 * @file classes/filter/TypeDescriptionFactory.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TypeDescriptionFactory
 *
 * @ingroup filter
 *
 * @brief A factory class that takes a plain text type descriptor
 *  and instantiates the correct type description object based on
 *  the descriptor's namespace.
 */

namespace PKP\filter;

use PKP\core\Registry;
use PKP\validation\ValidatorTypeDescription;
use PKP\xslt\XMLTypeDescription;
use PKP\metadata\MetadataTypeDescription;
use PKP\filter\ClassTypeDescription;
use PKP\filter\PrimitiveTypeDescription;

class TypeDescriptionFactory
{
    // Currently supported type descriptor namespaces
    public const TYPE_DESCRIPTION_NAMESPACE_PRIMITIVE = 'primitive';
    public const TYPE_DESCRIPTION_NAMESPACE_CLASS = 'class';
    public const TYPE_DESCRIPTION_NAMESPACE_METADATA = 'metadata';
    public const TYPE_DESCRIPTION_NAMESPACE_XML = 'xml';
    public const TYPE_DESCRIPTION_NAMESPACE_VALIDATOR = 'validator';

    /**
     * Constructor
     *
     * NB: Should not be called directly!
     * Always use getInstance().
     */
    public function __construct()
    {
    }

    //
    // Public static method
    //
    /**
     * Return an instance of the session manager.
     *
     * @return TypeDescriptionFactory
     */
    public static function getInstance()
    {
        $instance = & Registry::get('typeDescriptionFactory', true, null);

        if (is_null($instance)) {
            // Implicitly set type description factory by ref in the registry
            $instance = new TypeDescriptionFactory();
        }

        return $instance;
    }

    //
    // Public methods
    //
    /**
     * Takes a plain text type descriptor, identifies the namespace
     * and instantiates the corresponding type description object.
     *
     * @param string $typeDescription A plain text type description.
     *
     *  Type descriptions consist of two parts:
     *  * a type namespace
     *  * a type name (optionally including parameters like cardinality, etc.)
     *
     *  Example:
     *    primitive::string[5]
     *    -> namespace: primitive - type name: string[5]
     *
     *  Each namespace will be mapped to one subclass of the TypeDescription
     *  class which will then be responsible to parse the given type name.
     *
     * @return TypeDescription|null if the type description is invalid.
     */
    public function instantiateTypeDescription($typeDescription): ?TypeDescription
    {
        // Identify the namespace
        $typeDescriptionParts = explode('::', $typeDescription);
        if (count($typeDescriptionParts) != 2) {
            return null;
        }

        // Map the namespace to a type description class
        $typeDescriptionClass = $this->_namespaceMap($typeDescriptionParts[0]);
        if (is_null($typeDescriptionClass)) {
            return null;
        }

        return new $typeDescriptionClass($typeDescriptionParts[1]);
    }


    //
    // Private helper methods
    //
    /**
     * Map a namespace to a fully qualified type descriptor
     * class name.
     *
     * FIXME: Move this map to the Application object.
     */
    public function _namespaceMap(string $namespace): ?string
    {
        return match($namespace) {
            self::TYPE_DESCRIPTION_NAMESPACE_PRIMITIVE => PrimitiveTypeDescription::class,
            self::TYPE_DESCRIPTION_NAMESPACE_CLASS => ClassTypeDescription::class,
            self::TYPE_DESCRIPTION_NAMESPACE_METADATA => MetadataTypeDescription::class,
            self::TYPE_DESCRIPTION_NAMESPACE_XML => XMLTypeDescription::class,
            self::TYPE_DESCRIPTION_NAMESPACE_VALIDATOR => ValidatorTypeDescription::class,
            default => null
        };
    }
}

<?php
/**
 * @file classes/filter/TypeDescriptionFactory.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TypeDescriptionFactory
 * @ingroup filter
 *
 * @brief A factory class that takes a plain text type descriptor
 *  and instantiates the correct type description object based on
 *  the descriptor's namespace.
 */

namespace PKP\filter;

use PKP\core\Registry;

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
    public function &instantiateTypeDescription($typeDescription)
    {
        $nullVar = null;

        // Identify the namespace
        $typeDescriptionParts = explode('::', $typeDescription);
        if (count($typeDescriptionParts) != 2) {
            return $nullVar;
        }

        // Map the namespace to a type description class
        $typeDescriptionClass = $this->_namespaceMap($typeDescriptionParts[0]);
        if (is_null($typeDescriptionClass)) {
            return $nullVar;
        }

        // Instantiate and return the type description object
        $typeDescriptionObject = & instantiate($typeDescriptionClass, 'TypeDescription', null, null, $typeDescriptionParts[1]);
        if (!is_object($typeDescriptionObject)) {
            return $nullVar;
        }

        return $typeDescriptionObject;
    }


    //
    // Private helper methods
    //
    /**
     * Map a namespace to a fully qualified type descriptor
     * class name.
     *
     * FIXME: Move this map to the Application object.
     *
     * @param string $namespace
     *
     * @return string
     */
    public function _namespaceMap($namespace)
    {
        static $namespaceMap = [
            self::TYPE_DESCRIPTION_NAMESPACE_PRIMITIVE => 'lib.pkp.classes.filter.PrimitiveTypeDescription',
            self::TYPE_DESCRIPTION_NAMESPACE_CLASS => 'lib.pkp.classes.filter.ClassTypeDescription',
            self::TYPE_DESCRIPTION_NAMESPACE_METADATA => 'lib.pkp.classes.metadata.MetadataTypeDescription',
            self::TYPE_DESCRIPTION_NAMESPACE_XML => 'lib.pkp.classes.xslt.XMLTypeDescription',
            self::TYPE_DESCRIPTION_NAMESPACE_VALIDATOR => 'lib.pkp.classes.validation.ValidatorTypeDescription'
        ];
        return $namespaceMap[$namespace] ?? null;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\TypeDescriptionFactory', '\TypeDescriptionFactory');
    foreach ([
        'TYPE_DESCRIPTION_NAMESPACE_PRIMITIVE',
        'TYPE_DESCRIPTION_NAMESPACE_CLASS',
        'TYPE_DESCRIPTION_NAMESPACE_METADATA',
        'TYPE_DESCRIPTION_NAMESPACE_XML',
        'TYPE_DESCRIPTION_NAMESPACE_VALIDATOR',
    ] as $constantName) {
        define($constantName, constant('\TypeDescriptionFactory::' . $constantName));
    }
}

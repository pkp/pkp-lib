<?php
/**
 * @file classes/filter/ClassTypeDescription.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ClassTypeDescription
 *
 * @ingroup filter
 *
 * @brief A type description that validates objects by class type.
 *
 * Example type identifier: 'class::lib.pkp.classes.submission.PKPSubmission'
 */

namespace PKP\filter;

class ClassTypeDescription extends TypeDescription
{
    /** @var string a valid class name */
    public $_className;

    /** @var string a valid package name */
    public $_packageName;

    //
    // Setters and Getters
    //
    /**
     * @see TypeDescription::getNamespace()
     */
    public function getNamespace()
    {
        return \PKP\filter\TypeDescriptionFactory::TYPE_DESCRIPTION_NAMESPACE_CLASS;
    }


    //
    // Implement abstract template methods from TypeDescription
    //
    /**
     * @see TypeDescription::parseTypeName()
     */
    public function parseTypeName($typeName)
    {
        $splitName = $this->splitClassName($typeName);
        if ($splitName === false) {
            return false;
        }
        [$this->_packageName, $this->_className] = $splitName;

        // FIXME: Validate package and class to reduce the risk of
        // code injection, e.g. check that the package is within given limits/folders,
        // don't allow empty package parts, etc.

        return true;
    }

    /**
     * @see TypeDescription::checkType()
     */
    public function checkType($object)
    {
        // We expect an object
        if (!is_object($object)) {
            return false;
        }

        // Check the object's class
        if (!$object instanceof $this->_className) {
            return false;
        }

        return true;
    }


    //
    // Protected helper methods
    //
    /**
     * Splits a fully qualified class name into
     * a package and a class name string.
     *
     * @param string $typeName the type name to be split up.
     *
     * @return array an array with the package name
     *  as its first entry and the class name as its
     *  second entry.
     */
    public function splitClassName($typeName)
    {
        // This should be a class - identify package and class name
        $typeNameParts = explode('.', $typeName);

        $className = array_pop($typeNameParts);

        // If using a PSR-style FQCN (preferred), the package will be empty.
        $packageName = implode('.', $typeNameParts);

        return [$packageName, $className];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\filter\ClassTypeDescription', '\ClassTypeDescription');
}

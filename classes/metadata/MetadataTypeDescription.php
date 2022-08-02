<?php
/**
 * @file classes/metadata/MetadataTypeDescription.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataTypeDescription
 * @ingroup metadata
 *
 * @brief Type validator for metadata input/output.
 *
 * This type description accepts descriptors of the following form:
 *   metadata::fully.qualified.MetadataSchema(ASSOC)
 *
 * The assoc form must be the final part of a ASSOC_TYPE_* definition.
 * It can be '*' to designate any assoc type.
 */

namespace PKP\metadata;

use PKP\filter\ClassTypeDescription;

class MetadataTypeDescription extends ClassTypeDescription
{
    public const ASSOC_TYPE_ANY = -1;

    /** @var string the expected meta-data schema package */
    public $_metadataSchemaPackageName;

    /** @var string the expected meta-data schema class */
    public $_metadataSchemaClassName;

    /** @var int the expected assoc type of the meta-data description */
    public $_assocType;


    //
    // Setters and Getters
    //
    /**
     * @see TypeDescription::getNamespace()
     */
    public function getNamespace()
    {
        return \PKP\filter\TypeDescriptionFactory::TYPE_DESCRIPTION_NAMESPACE_METADATA;
    }

    /**
     * @return string the fully qualified class name of the meta-data schema.
     */
    public function getMetadataSchemaClass()
    {
        return $this->_metadataSchemaPackageName . '.' . $this->_metadataSchemaClassName;
    }

    /**
     * @return int
     */
    public function getAssocType()
    {
        return $this->_assocType;
    }


    //
    // Implement abstract template methods from TypeDescription
    //
    /**
     * @see TypeDescription::parseTypeName()
     */
    public function parseTypeName($typeName)
    {
        // Configure the parent class type description
        // with the expected meta-data class.
        parent::parseTypeName('lib.pkp.classes.metadata.MetadataDescription');

        // Split the type name into class name and assoc type.
        $typeNameParts = explode('(', $typeName);
        if (!count($typeNameParts) == 2) {
            return false;
        }

        // The meta-data schema class must be
        // a fully qualified class name.
        $splitMetadataSchemaClass = $this->splitClassName($typeNameParts[0]);
        if ($splitMetadataSchemaClass === false) {
            return false;
        }
        [$this->_metadataSchemaPackageName, $this->_metadataSchemaClassName] = $splitMetadataSchemaClass;

        // Identify the assoc type.
        $assocTypeString = trim($typeNameParts[1], ')');
        if ($assocTypeString == '*') {
            $this->_assocType = self::ASSOC_TYPE_ANY;
        } else {
            // Make sure that the given assoc type exists.
            $assocTypeString = 'ASSOC_TYPE_' . $assocTypeString;
            if (!defined($assocTypeString)) {
                return false;
            }
            $this->_assocType = constant($assocTypeString);
        }

        return true;
    }

    /**
     * @see TypeDescription::checkType()
     */
    public function checkType(&$object)
    {
        // First of all check whether this is a
        // meta-data description at all.
        if (!parent::checkType($object)) {
            return false;
        }

        // Check the meta-data schema.
        $metadataSchema = & $object->getMetadataSchema();
        if (!$metadataSchema instanceof $this->_metadataSchemaClassName) {
            return false;
        }

        // Check the assoc type
        if ($this->_assocType != self::ASSOC_TYPE_ANY) {
            if ($object->getAssocType() != $this->_assocType) {
                return false;
            }
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\metadata\MetadataTypeDescription', '\MetadataTypeDescription');
    define('ASSOC_TYPE_ANY', \MetadataTypeDescription::ASSOC_TYPE_ANY);
}

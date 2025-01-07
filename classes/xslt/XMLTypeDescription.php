<?php

/**
 * @file classes/xslt/XMLTypeDescription.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class XMLTypeDescription
 *
 * @ingroup xslt
 *
 * @brief Class that describes an XML input/output type.
 *
 *  Type descriptors follow the syntax:
 *   xml::validation-schema(http://url.to.the/file.{xsd|dtd|rng})
 *
 *  Example:
 *   xml::schema(http://www.crossref.org/schema/queryResultSchema/crossref_query_output2.0.xsd)
 *
 *  XML input/output can be either represented as a string or as a DOMDocument object.
 */

namespace PKP\xslt;

use DOMDocument;
use Exception;
use PKP\filter\TypeDescription;

class XMLTypeDescription extends TypeDescription
{
    public const XML_TYPE_DESCRIPTION_VALIDATE_NONE = '*';
    public const XML_TYPE_DESCRIPTION_VALIDATE_SCHEMA = 'schema';
    public const XML_TYPE_DESCRIPTION_VALIDATE_DTD = 'dtd';
    public const XML_TYPE_DESCRIPTION_VALIDATE_RELAX_NG = 'relax-ng';

    /** @var string a validation strategy, see the XML_TYPE_DESCRIPTION_VALIDATE_* constants */
    public $_validationStrategy = self::XML_TYPE_DESCRIPTION_VALIDATE_SCHEMA;

    /** @var string a validation document as string or filename pointer (xsd or rng only) */
    public $_validationSource;


    //
    // Setters and Getters
    //
    /**
     * @see TypeDescription::getNamespace()
     */
    public function getNamespace()
    {
        return \PKP\filter\TypeDescriptionFactory::TYPE_DESCRIPTION_NAMESPACE_XML;
    }

    /**
     * Set the validation strategy
     *
     * @param string $validationStrategy XML_TYPE_DESCRIPTION_VALIDATE_...
     */
    public function setValidationStrategy($validationStrategy)
    {
        $this->_validationStrategy = $validationStrategy;
    }

    //
    // Implement abstract template methods from TypeDescription
    //
    /**
     * @copydoc TypeDescription::parseTypeName()
     */
    public function parseTypeName(string $typeName): bool
    {
        // We expect a validation strategy and an optional validation argument
        $typeNameParts = explode('(', $typeName);
        switch (count($typeNameParts)) {
            case 1:
                // No argument present (only dtd or no validation)
                $validationStrategy = $typeName;
                if ($validationStrategy != self::XML_TYPE_DESCRIPTION_VALIDATE_NONE
                        && $validationStrategy != self::XML_TYPE_DESCRIPTION_VALIDATE_DTD) {
                    return false;
                }
                $validationSource = null;
                break;

            case 2:
                // We have an argument (only available for schema and relax-ng)
                $validationStrategy = $typeNameParts[0];
                if ($validationStrategy != self::XML_TYPE_DESCRIPTION_VALIDATE_SCHEMA
                        && $validationStrategy != self::XML_TYPE_DESCRIPTION_VALIDATE_RELAX_NG) {
                    return false;
                }
                $validationSource = trim($typeNameParts[1], ')');
                break;

            default:
                return false;
        }

        $this->_validationStrategy = $validationStrategy;
        $this->_validationSource = $validationSource;

        return true;
    }

    /**
     * @copydoc TypeDescription::checkType()
     */
    public function checkType($object): bool
    {
        // We only accept DOMDocument objects and source strings.
        if (!$object instanceof DOMDocument && !is_string($object)) {
            return false;
        }

        // No validation...
        if ($this->_validationStrategy == self::XML_TYPE_DESCRIPTION_VALIDATE_NONE) {
            return true;
        }

        // Validation - requires DOMDocument
        if (is_string($object)) {
            $xmlDom = new DOMDocument('1.0', 'utf-8');
            $xmlDom->loadXML($object);
        } else {
            $xmlDom = & $object;
        }

        switch ($this->_validationStrategy) {
            // We have to suppress validation errors, otherwise the script
            // will stop when validation errors occur.
            case self::XML_TYPE_DESCRIPTION_VALIDATE_DTD:
                if (!$xmlDom->validate()) {
                    return false;
                }
                break;

            case self::XML_TYPE_DESCRIPTION_VALIDATE_SCHEMA:
                libxml_use_internal_errors(true);
                if (!$xmlDom->schemaValidate($this->_validationSource)) {
                    error_log(new Exception("XML validation failed with:\n" . print_r(libxml_get_errors(), true)));
                    return false;
                }

                break;

            case self::XML_TYPE_DESCRIPTION_VALIDATE_RELAX_NG:
                if (!$xmlDom->relaxNGValidate($this->_validationSource)) {
                    return false;
                }
                break;

            default:
                assert(false);
        }

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\xslt\XMLTypeDescription', '\XMLTypeDescription');
    foreach (['XML_TYPE_DESCRIPTION_VALIDATE_NONE', 'XML_TYPE_DESCRIPTION_VALIDATE_SCHEMA', 'XML_TYPE_DESCRIPTION_VALIDATE_DTD', 'XML_TYPE_DESCRIPTION_VALIDATE_RELAX_NG'] as $constantName) {
        define($constantName, constant('\XMLTypeDescription::' . $constantName));
    }
}

<?php

/**
 * @file classes/metadata/MetadataProperty.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataProperty
 *
 * @ingroup metadata
 *
 * @see MetadataSchema
 * @see MetadataRecord
 *
 * @brief Class representing metadata properties. It specifies type and cardinality
 *  of a meta-data property (=term, field, ...) and whether the property can
 *  be internationalized. It also provides a validator to test whether input
 *  conforms to the property specification.
 *
 *  In the DCMI abstract model, this class specifies a property together with its
 *  allowed range and cardinality.
 *
 *  We also define the resource types (application entities, association types)
 *  that can be described with the property. This allows us to check that only
 *  valid resource associations are made. It also allows us to prepare property
 *  entry forms or displays for a given resource type and integrate these in the
 *  work-flow of the resource. By dynamically adding or removing assoc types,
 *  end users will be able to configure the meta-data fields that they wish to
 *  make available, persist or enter in their application.
 */

namespace PKP\metadata;

use InvalidArgumentException;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\core\PKPString;
use PKP\validation\ValidatorControlledVocab;
use PKP\validation\ValidatorFactory;

class MetadataProperty
{
    // literal values (plain)
    public const METADATA_PROPERTY_TYPE_STRING = 1;

    // literal values (typed)
    public const METADATA_PROPERTY_TYPE_DATE = 2; // This is W3CDTF encoding without time (YYYY[-MM[-DD]])!
    public const METADATA_PROPERTY_TYPE_INTEGER = 3;

    // non-literal value string from a controlled vocabulary
    public const METADATA_PROPERTY_TYPE_VOCABULARY = 4;

    // non-literal value URI
    public const METADATA_PROPERTY_TYPE_URI = 5;

    // non-literal value pointing to a separate description set instance (=another MetadataRecord object)
    public const METADATA_PROPERTY_TYPE_COMPOSITE = 6;

    // allowed cardinality of statements for a given property type in a meta-data schema
    public const METADATA_PROPERTY_CARDINALITY_ONE = 1;
    public const METADATA_PROPERTY_CARDINALITY_MANY = 2;

    /** @var string property name */
    public $_name;

    /** @var string a translation id */
    public $_displayName;

    /** @var int the resource types that can be described with this property */
    public $_assocTypes;

    /** @var array allowed property types */
    public $_allowedTypes;

    /** @var bool flag that defines whether the property can be translated */
    public $_translated;

    /** @var int property cardinality */
    public $_cardinality;

    /** @var string validation message */
    public $_validationMessage;

    /** @var bool */
    public $_mandatory;

    /**
     * Constructor
     *
     * @param string $name the unique name of the property within a meta-data schema (can be a property URI)
     * @param array $assocTypes an array of integers that define the application entities that can
     *  be described with this property.
     * @param mixed $allowedTypes must be a scalar or an array with the supported types, default: METADATA_PROPERTY_TYPE_STRING
     * @param bool $translated whether the property may have various language versions, default: false
     * @param int $cardinality must be on of the supported cardinalities, default: METADATA_PROPERTY_CARDINALITY_ONE
     * @param string $displayName
     * @param string $validationMessage A string that can be displayed in case a user tries to set an invalid value for this property.
     * @param bool $mandatory Is this a mandatory property within the schema?
     */
    public function __construct(
        $name,
        $assocTypes = [],
        $allowedTypes = self::METADATA_PROPERTY_TYPE_STRING,
        $translated = false,
        $cardinality = self::METADATA_PROPERTY_CARDINALITY_ONE,
        $displayName = null,
        $validationMessage = null,
        $mandatory = false
    ) {
        // Validate name and assoc type array
        if (!is_string($name)) {
            throw new InvalidArgumentException('$name should be a string.');
        }
        if (!is_array($assocTypes)) {
            throw new InvalidArgumentException('$assocTypes should be an array.');
        }

        // A single type will be transformed to an
        // array of types so that we can handle them
        // uniformly.
        if (is_scalar($allowedTypes) || count($allowedTypes) == 1) {
            $allowedTypes = [$allowedTypes];
        }

        // Validate types
        $canonicalizedTypes = [];
        foreach ($allowedTypes as $allowedType) {
            if (is_array($allowedType)) {
                // We expect an array with a single entry
                // of the form "type => additional parameter".
                assert(count($allowedType) == 1);
                // Reset the array, just in case...
                reset($allowedType);
                // Extract the type and the additional parameter
                $allowedTypeId = key($allowedType);
                $allowedTypeParam = current($allowedType);
            } else {
                // No additional parameter has been set.
                $allowedTypeId = $allowedType;
                $allowedTypeParam = null;
            }

            // Validate type
            if (!in_array($allowedTypeId, MetadataProperty::getSupportedTypes())) {
                throw new InvalidArgumentException('Allowed types must be supported types!');
            }

            // Transform the type array in a
            // structure that is easy to handle
            // in for loops.
            $canonicalizedTypes[$allowedTypeId][] = $allowedTypeParam;

            // Validate additional type parameter.
            switch ($allowedTypeId) {
                case self::METADATA_PROPERTY_TYPE_COMPOSITE:
                    // Validate the assoc id of the composite.
                    if (!is_integer($allowedTypeParam)) {
                        throw new InvalidArgumentException('Allowed type parameter should be an integer.');
                    }
                    // Properties that allow composite types cannot be translated.
                    if ($translated) {
                        throw new InvalidArgumentException('Properties that allow composite types cannot be translated.');
                    }
                    break;

                case self::METADATA_PROPERTY_TYPE_VOCABULARY:
                    // Validate the symbolic name of the vocabulary.
                    if (!is_string($allowedTypeParam)) {
                        throw new InvalidArgumentException('Allowed type parameter should be a string.');
                    }
                    break;

                default:
                    // No other types support an additional parameter
                    if (!is_null($allowedTypeParam)) {
                        throw new InvalidArgumentException('An additional parameter was supplied for an unsupported metadata property type.');
                    }
            }
        }

        // Validate translation and cardinality
        if (!is_bool($translated)) {
            throw new InvalidArgumentException('$translated must be a boolean');
        }
        if (!in_array($cardinality, MetadataProperty::getSupportedCardinalities())) {
            throw new InvalidArgumentException('$cardinality must be a supported cardinality.');
        }

        // Default display name
        if (is_null($displayName)) {
            $displayName = 'metadata.property.displayName.' . $name;
        }
        if (!is_string($displayName)) {
            throw new InvalidArgumentException('$displayName must be a string.');
        }

        // Default validation message
        if (is_null($validationMessage)) {
            $validationMessage = 'metadata.property.validationMessage.' . $name;
        }
        if (!is_string($validationMessage)) {
            throw new InvalidArgumentException('$validationMessage must be a string.');
        }


        // Initialize the class
        $this->_name = (string)$name;
        $this->_assocTypes = & $assocTypes;
        $this->_allowedTypes = & $canonicalizedTypes;
        $this->_translated = (bool)$translated;
        $this->_cardinality = (int)$cardinality;
        $this->_displayName = (string)$displayName;
        $this->_validationMessage = (string)$validationMessage;
        $this->_mandatory = (bool)$mandatory;
    }

    /**
     * Get the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Returns a canonical form of the property
     * name ready to be used as a property id in an
     * external context (e.g. Forms or Templates).
     *
     * @return string
     */
    public function getId()
    {
        // Replace special characters in XPath-like names
        // as 'person-group[@person-group-type="author"]'.
        $from = [
            '[', ']', '@', '"', '='
        ];
        $to = [
            '-', '', '', '', '-'
        ];
        $propertyId = trim(str_replace($from, $to, $this->getName()), '-');
        $propertyId = PKPString::camelize($propertyId);
        return $propertyId;
    }

    /**
     * Get the translation id representing
     * the display name of the property.
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->_displayName;
    }

    /**
     * Get the allowed association types
     * (resources that can be described
     * with this property)
     *
     * @return array a list of integers representing
     *  association types.
     */
    public function &getAssocTypes()
    {
        return $this->_assocTypes;
    }

    /**
     * Get the allowed type
     *
     * @return int
     */
    public function getAllowedTypes()
    {
        return $this->_allowedTypes;
    }

    /**
     * Is this property translated?
     *
     * @return bool
     */
    public function getTranslated()
    {
        return $this->_translated;
    }

    /**
     * Get the cardinality
     *
     * @return int
     */
    public function getCardinality()
    {
        return $this->_cardinality;
    }

    /**
     * Get the validation message
     *
     * @return string
     */
    public function getValidationMessage()
    {
        return $this->_validationMessage;
    }

    /**
     * Is this property mandatory?
     *
     * @return bool
     */
    public function getMandatory()
    {
        return $this->_mandatory;
    }


    //
    // Public methods
    //
    /**
     * Validate a given input against the property specification
     *
     * The given value must validate against at least one of the
     * allowed types. The first allowed type id will be returned as
     * validation result. If the given value fits none of the allowed
     * types, then we'll return 'false'.
     *
     * @param mixed $value the input to be validated
     * @param string $locale the locale to be used for validation
     *
     * @return array|boolean an array with a single entry of the format
     *  "type => additional type parameter" against which the value
     *  validated or boolean false if not validated at all.
     */
    public function isValid($value, $locale = null)
    {
        // We never accept null values or arrays.
        if (is_null($value) || is_array($value)) {
            return false;
        }

        // Translate the locale.
        if (is_null($locale)) {
            $locale = '';
        }

        // MetadataProperty::getSupportedTypes() returns an ordered
        // list of possible meta-data types with the most specific
        // type coming first so that we always correctly identify
        // specializations (e.g. a date is a specialized string).
        $allowedTypes = $this->getAllowedTypes();
        foreach (MetadataProperty::getSupportedTypes() as $testedType) {
            if (isset($allowedTypes[$testedType])) {
                foreach ($allowedTypes[$testedType] as $allowedTypeParam) {
                    // Type specific validation
                    switch ($testedType) {
                        case self::METADATA_PROPERTY_TYPE_COMPOSITE:
                            // Composites can either be represented by a meta-data description
                            // or by a string of the form AssocType:AssocId if the composite
                            // has already been persisted in the database.
                            switch (true) {
                                // Test for MetadataDescription format
                                case $value instanceof \PKP\metadata\MetadataDescription:
                                    $assocType = $value->getAssocType();
                                    break;

                                    // Test for AssocType:AssocId format
                                case is_string($value):
                                    $valueParts = explode(':', $value);
                                    if (count($valueParts) != 2) {
                                        break 2;
                                    } // break the outer switch
                                    [$assocType, $assocId] = $valueParts;
                                    if (!(is_numeric($assocType) && is_numeric($assocId))) {
                                        break 2;
                                    } // break the outer switch
                                    $assocType = (int)$assocType;
                                    break;

                                default:
                                    // None of the allowed types
                                    break;
                            }

                            // Check that the association type matches
                            // with the allowed association type (which
                            // is configured as an additional type parameter).
                            if (isset($assocType) && $assocType === $allowedTypeParam) {
                                return [self::METADATA_PROPERTY_TYPE_COMPOSITE => $assocType];
                            }
                            break;

                        case self::METADATA_PROPERTY_TYPE_VOCABULARY:
                            // Interpret the type parameter of this type like this:
                            // symbolic[:assoc-type:assoc-id]. If no assoc type/id are
                            // given then we assume :0:0 to represent site-wide vocabs.
                            $vocabNameParts = explode(':', $allowedTypeParam);
                            $vocabNamePartsCount = count($vocabNameParts);
                            switch ($vocabNamePartsCount) {
                                case 1:
                                    // assume a site-wide vocabulary
                                    $symbolic = $allowedTypeParam;
                                    $assocType = $assocId = 0;
                                    break;

                                case 3:
                                    // assume a context-specific vocabulary
                                    [$symbolic, $assocType, $assocId] = $vocabNameParts;
                                    break;

                                default:
                                    // Invalid configuration
                                    assert(false);
                            }

                            if (is_string($value)) {
                                // Try to translate the string value into a controlled vocab entry
                                $entry = ControlledVocabEntry::query()
                                    ->whereHas(
                                        'controlledVocab',
                                        fn ($query) => $query->withSymbolic($symbolic)->withAssoc($assocType, $assocId)
                                    )
                                    ->withLocale($locale)
                                    ->withSetting($symbolic, $value)
                                    ->first();
        
                                if (!is_null($entry)) {
                                    // The string was successfully translated so mark it as "valid".
                                    return [self::METADATA_PROPERTY_TYPE_VOCABULARY => $allowedTypeParam];
                                }
                            }

                            if (is_integer($value)) {
                                // Validate with controlled vocabulary validator
                                $validator = new ValidatorControlledVocab($symbolic, $assocType, $assocId);
                                if ($validator->isValid($value)) {
                                    return [self::METADATA_PROPERTY_TYPE_VOCABULARY => $allowedTypeParam];
                                }
                            }

                            break;

                        case self::METADATA_PROPERTY_TYPE_URI:
                            $validator = ValidatorFactory::make(
                                ['uri' => $value],
                                ['uri' => 'url']
                            );
                            if (!$validator->fails()) {
                                return [self::METADATA_PROPERTY_TYPE_URI => null];
                            }
                            break;

                        case self::METADATA_PROPERTY_TYPE_DATE:
                            // We allow the following patterns:
                            // YYYY-MM-DD, YYYY-MM and YYYY
                            $datePattern = '/^[0-9]{4}(-[0-9]{2}(-[0-9]{2})?)?$/';
                            if (!preg_match($datePattern, $value)) {
                                break;
                            }

                            // Check whether the given string is really a valid date
                            $dateParts = explode('-', $value);
                            // Set the day and/or month to 1 if not set
                            $dateParts = array_pad($dateParts, 3, 1);
                            // Extract the date parts
                            [$year, $month, $day] = $dateParts;
                            // Validate the date (only leap days will pass unnoticed ;-) )
                            // Who invented this argument order?
                            if (checkdate($month, $day, $year)) {
                                return [self::METADATA_PROPERTY_TYPE_DATE => null];
                            }
                            break;

                        case self::METADATA_PROPERTY_TYPE_INTEGER:
                            if (is_integer($value)) {
                                return [self::METADATA_PROPERTY_TYPE_INTEGER => null];
                            }
                            break;

                        case self::METADATA_PROPERTY_TYPE_STRING:
                            if (is_string($value)) {
                                return [self::METADATA_PROPERTY_TYPE_STRING => null];
                            }
                            break;

                        default:
                            // Unknown type. As we validate type in the setter, this
                            // should be unreachable code.
                            assert(false);
                    }
                }
            }
        }

        // Return false if the value didn't validate against any
        // of the allowed types.
        return false;
    }

    //
    // Public static methods
    //
    /**
     * Return supported meta-data property types
     *
     * NB: These types are sorted from most specific to
     * most general and will be validated in this order
     * so that we'll always identify more specific types
     * as such (see MetadataProperty::isValid() for more
     * details).
     *
     * @return array supported meta-data property types
     */
    public static function getSupportedTypes()
    {
        static $_supportedTypes = [
            self::METADATA_PROPERTY_TYPE_COMPOSITE,
            self::METADATA_PROPERTY_TYPE_VOCABULARY,
            self::METADATA_PROPERTY_TYPE_URI,
            self::METADATA_PROPERTY_TYPE_DATE,
            self::METADATA_PROPERTY_TYPE_INTEGER,
            self::METADATA_PROPERTY_TYPE_STRING
        ];
        return $_supportedTypes;
    }

    /**
     * Return supported cardinalities
     *
     * @return array supported cardinalities
     */
    public static function getSupportedCardinalities()
    {
        static $_supportedCardinalities = [
            self::METADATA_PROPERTY_CARDINALITY_ONE,
            self::METADATA_PROPERTY_CARDINALITY_MANY
        ];
        return $_supportedCardinalities;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\metadata\MetadataProperty', '\MetadataProperty');
    foreach ([
        'METADATA_PROPERTY_TYPE_STRING',
        'METADATA_PROPERTY_TYPE_DATE',
        'METADATA_PROPERTY_TYPE_INTEGER',
        'METADATA_PROPERTY_TYPE_VOCABULARY',
        'METADATA_PROPERTY_TYPE_URI',
        'METADATA_PROPERTY_TYPE_COMPOSITE',
        'METADATA_PROPERTY_CARDINALITY_ONE',
        'METADATA_PROPERTY_CARDINALITY_MANY',
    ] as $constantName) {
        define($constantName, constant('\MetadataProperty::' . $constantName));
    }
}

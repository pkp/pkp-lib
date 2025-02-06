<?php

/**
 * @file classes/services/PKPSchemaService.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSchemaService
 *
 * @ingroup services
 *
 * @brief Helper class for loading schemas, using them to sanitize and
 *  validate objects, and installing default data.
 */

namespace PKP\services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;
use PKP\core\DataObject;
use PKP\core\maps\Schema;
use PKP\plugins\Hook;

/**
 * @template T of DataObject
 */
class PKPSchemaService
{
    public const SCHEMA_AFFILIATION = 'affiliation';
    public const SCHEMA_ANNOUNCEMENT = 'announcement';
    public const SCHEMA_AUTHOR = 'author';
    public const SCHEMA_CATEGORY = 'category';
    public const SCHEMA_CONTEXT = 'context';
    public const SCHEMA_DOI = 'doi';
    public const SCHEMA_DECISION = 'decision';
    public const SCHEMA_EMAIL_TEMPLATE = 'emailTemplate';
    public const SCHEMA_GALLEY = 'galley';
    public const SCHEMA_HIGHLIGHT = 'highlight';
    public const SCHEMA_INSTITUTION = 'institution';
    public const SCHEMA_ISSUE = 'issue';
    public const SCHEMA_PUBLICATION = 'publication';
    public const SCHEMA_REVIEW_ASSIGNMENT = 'reviewAssignment';
    public const SCHEMA_REVIEW_ROUND = 'reviewRound';
    public const SCHEMA_ROR = 'ror';
    public const SCHEMA_SECTION = 'section';
    public const SCHEMA_SITE = 'site';
    public const SCHEMA_SUBMISSION = 'submission';
    public const SCHEMA_SUBMISSION_FILE = 'submissionFile';
    public const SCHEMA_USER = 'user';
    public const SCHEMA_USER_GROUP = 'userGroup';
    public const SCHEMA_EVENT_LOG = 'eventLog';
    public const SCHEMA_EMAIL_LOG = 'emailLog';

    /** @var array cache of schemas that have been loaded */
    private $_schemas = [];

    /**
     * Get a schema
     *
     * - Loads the schema file and transforms it into an object
     * - Passes schema through hook
     * - Returns pre-loaded schemas on request
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param bool $forceReload Optional. Compile the schema again from the
     *  source files, bypassing any cached version.
     *
     * @return object
     *
     * @hook Schema::get::(schemaName) [[schema]]
     * @hook Schema::get::
     * @hook Schema::get::before::
     * @hook Schema::get::before::
     * @hook Schema::get::before::
     */
    public function get($schemaName, $forceReload = false)
    {
        Hook::run('Schema::get::before::' . $schemaName, [&$forceReload]);

        if (!$forceReload && array_key_exists($schemaName, $this->_schemas)) {
            return $this->_schemas[$schemaName];
        }

        $schemaFile = sprintf('%s/lib/pkp/schemas/%s.json', BASE_SYS_DIR, $schemaName);
        if (file_exists($schemaFile)) {
            $schema = json_decode(file_get_contents($schemaFile));
            if (!$schema) {
                throw new Exception('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $schemaFile . '. Last JSON error: ' . json_last_error());
            }
        } else {
            // allow plugins to create a custom schema and load it via hook
            $schema = new \stdClass();
        }

        // Merge an app-specific schema file if it exists
        $appSchemaFile = sprintf('%s/schemas/%s.json', BASE_SYS_DIR, $schemaName);
        if (file_exists($appSchemaFile)) {
            $appSchema = json_decode(file_get_contents($appSchemaFile));
            if (!$appSchema) {
                throw new Exception('Schema failed to decode. This usually means it is invalid JSON. Requested: ' . $appSchemaFile . '. Last JSON error: ' . json_last_error());
            }
            $schema = $this->merge($schema, $appSchema);
        }

        Hook::call('Schema::get::' . $schemaName, [&$schema]);

        $this->_schemas[$schemaName] = $schema;

        return $schema;
    }

    /**
     * Merge two schemas
     *
     * Merges the properties of two schemas, updating the title, description,
     * and properties definitions.
     *
     * If both schemas contain definitions for the same property, the property
     * definition in the additional schema will override the base schema.
     *
     * @param object $baseSchema The base schema
     * @param object $additionalSchema The additional schema properties to apply
     *  to $baseSchema.
     *
     * @return object
     */
    public function merge($baseSchema, $additionalSchema)
    {
        $newSchema = clone $baseSchema;
        if (!empty($additionalSchema->title)) {
            $newSchema->title = $additionalSchema->title;
        }
        if (!empty($additionalSchema->description)) {
            $newSchema->description = $additionalSchema->description;
        }
        if (!empty($additionalSchema->required)) {
            $required = property_exists($baseSchema, 'required')
                ? $baseSchema->required
                : [];
            $newSchema->required = array_unique(array_merge($required, $additionalSchema->required));
        }
        if (!empty($additionalSchema->properties)) {
            if (empty($newSchema->properties)) {
                $newSchema->properties = new \stdClass();
            }
            foreach ($additionalSchema->properties as $propName => $propSchema) {
                $newSchema->properties->{$propName} = $propSchema;
            }
        }

        return $newSchema;
    }

    /**
     * Get the summary properties of a schema
     *
     * Gets the properties of a schema which are considered part of the summary
     * view presented in an API.
     *
     * @param string $schemaName One of the SCHEMA_... constants
     *
     * @return array List of property names
     */
    public function getSummaryProps($schemaName)
    {
        $schema = $this->get($schemaName);
        $props = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!empty($propSchema->apiSummary) && empty($propSchema->writeOnly)) {
                $props[] = $propName;
            }
        }

        return $props;
    }

    /**
     * Get all properties of a schema
     *
     * Gets the complete list of properties of a schema which are considered part
     * of the full view presented in an API.
     *
     * @param string $schemaName One of the SCHEMA_... constants
     *
     * @return array List of property names
     */
    public function getFullProps($schemaName)
    {
        $schema = $this->get($schemaName);

        $propNames = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (empty($propSchema->writeOnly)) {
                $propNames[] = $propName;
            }
        }

        return $propNames;
    }

    /**
     * Get required properties of a schema
     *
     * @param string $schemaName One of the SCHEMA_... constants
     *
     * @return array List of property names
     */
    public function getRequiredProps($schemaName)
    {
        $schema = $this->get($schemaName);

        if (!empty($schema->required)) {
            return $schema->required;
        }
        return [];
    }

    /**
     * Get multilingual properties of a schema
     *
     * @param string $schemaName One of the SCHEMA_... constants
     *
     * @return array List of property names
     */
    public function getMultilingualProps($schemaName)
    {
        $schema = $this->get($schemaName);

        $multilingualProps = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!empty($propSchema->multilingual)) {
                $multilingualProps[] = $propName;
            }
        }

        return $multilingualProps;
    }

    /**
     * Retrieves properties of the schema of certain origin
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param string $attributeOrigin one of the Schema::ATTRIBUTE_ORIGIN_* constants
     *
     * @return array List of property names
     */
    public function getPropsByAttributeOrigin(string $schemaName, string $attributeOrigin): array
    {
        $schema = $this->get($schemaName);

        $propsByOrigin = [];
        foreach ($schema->properies as $propName => $propSchema) {
            if (!empty($propSchema->origin) && $propSchema->origin == $attributeOrigin) {
                $propsByOrigin[] = $propName;
            }
        }

        return $propsByOrigin;
    }

    /**
     * Groups properties by their origin, see Schema::ATTRIBUTE_ORIGIN_* constants
     *
     * @return array<string, array<string>>, e.g. ['primary' => ['assocId', 'assocType']]
     */
    public function groupPropsByOrigin(string $schemaName, bool $excludeReadOnly = false): array
    {
        $schema = $this->get($schemaName);
        $propsByOrigin = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (empty($propSchema->origin)) {
                continue;
            }

            // Exclude readonly if specified
            if ($excludeReadOnly && !empty($propSchema->readOnly) && $propSchema->readOnly) {
                continue;
            }

            switch ($propSchema->origin) {
                case Schema::ATTRIBUTE_ORIGIN_SETTINGS:
                    $propsByOrigin[Schema::ATTRIBUTE_ORIGIN_SETTINGS][] = $propName;
                    break;
                case Schema::ATTRIBUTE_ORIGIN_COMPOSED:
                    $propsByOrigin[Schema::ATTRIBUTE_ORIGIN_COMPOSED][] = $propName;
                    break;
                case Schema::ATTRIBUTE_ORIGIN_MAIN:
                default:
                    $propsByOrigin[Schema::ATTRIBUTE_ORIGIN_MAIN][] = $propName;
                    break;
            }
        }

        return $propsByOrigin;
    }


    /**
     * Sanitize properties according to a schema
     *
     * This method coerces properties to their appropriate type, and strips out
     * properties that are not specified in the schema.
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param array $props Properties to be sanitized
     *
     * @return array The sanitized props
     */
    public function sanitize($schemaName, $props)
    {
        $schema = $this->get($schemaName);
        $cleanProps = [];

        foreach ($props as $propName => $propValue) {
            if (empty($schema->properties->{$propName})
                || empty($schema->properties->{$propName}->type)
                || !empty($schema->properties->{$propName}->readOnly)) {
                continue;
            }
            $propSchema = $schema->properties->{$propName};
            if (!empty($propSchema->multilingual)) {
                $values = [];
                foreach ((array) $propValue as $localeKey => $localeValue) {
                    $values[$localeKey] = $this->coerce($localeValue, $propSchema->type, $propSchema);
                }
                if (!empty($values)) {
                    $cleanProps[$propName] = $values;
                }
            } else {
                $cleanProps[$propName] = $this->coerce($propValue, $propSchema->type, $propSchema);
            }
        }

        return $cleanProps;
    }

    /**
     * Coerce a value to a variable type
     *
     * It will leave null values alone.
     *
     * @param string $type boolean, integer, number, string, array, object
     * @param object $schema A schema defining this property
     *
     * @return mixed The value coerced to type
     */
    public function coerce($value, $type, $schema)
    {
        if (is_null($value)) {
            return $value;
        }
        switch ($type) {
            case 'boolean':
                return (bool) $value;
            case 'integer':
                return (int) $value;
            case 'number':
                return (float) $value;
            case 'string':
                if (is_object($value) || is_array($value)) {
                    $value = serialize($value);
                }
                return (string) $value;
            case 'array':
                $newArray = [];
                if (is_array($schema->items)) {
                    foreach ($schema->items as $i => $itemSchema) {
                        $newArray[$i] = $this->coerce($value[$i], $itemSchema->type, $itemSchema);
                    }
                } elseif (is_array($value)) {
                    foreach ($value as $i => $v) {
                        $newArray[$i] = $this->coerce($v, $schema->items->type, $schema->items);
                    }
                } else {
                    $newArray[] = serialize($value);
                }
                return $newArray;
            case 'object':
                $newObject = []; // we handle JSON objects as assoc arrays in PHP

                if (isValidJson($value)) {
                    $value = json_decode($value, true);
                }

                foreach ($schema->properties as $propName => $propSchema) {
                    if (!isset($value[$propName]) || !empty($propSchema->readOnly)) {
                        continue;
                    }
                    $newObject[$propName] = $this->coerce($value[$propName], $propSchema->type, $propSchema);
                }
                return $newObject;
        }
        throw new Exception('Requested variable coercion for a type that was not recognized: ' . $type);
    }

    /**
     * Get the validation rules for the properties of a schema
     *
     * These validation rules are returned in a format that is ready to be passed
     * into ValidatorFactory::make().
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param array $allowedLocales List of allowed locale keys.
     *
     * @return array List of validation rules for each property
     */
    public function getValidationRules($schemaName, $allowedLocales)
    {
        $schema = $this->get($schemaName);

        $rules = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!empty($propSchema->multilingual)) {
                foreach ($allowedLocales as $localeKey) {
                    $rules = $this->addPropValidationRules($rules, $propName . '.' . $localeKey, $propSchema);
                }
            } else {
                $rules = $this->addPropValidationRules($rules, $propName, $propSchema);
            }
        }

        return $rules;
    }

    /**
     * Compile the validation rules for a single property's schema
     *
     * @param object $propSchema The property schema
     *
     * @return array List of Laravel-formatted validation rules
     */
    public function addPropValidationRules($rules, $ruleKey, $propSchema)
    {
        if (!empty($propSchema->readOnly)) {
            return $rules;
        }
        switch ($propSchema->type) {
            case 'boolean':
            case 'integer':
            case 'numeric':
            case 'string':
                $rules[$ruleKey] = [$propSchema->type];
                if (!empty($propSchema->validation)) {
                    $rules[$ruleKey] = array_merge($rules[$ruleKey], $propSchema->validation);
                }
                break;
            case 'array':
                if ($propSchema->items->type === 'object') {
                    $rules = $this->addPropValidationRules($rules, $ruleKey . '.*', $propSchema->items);
                } else {
                    $rules[$ruleKey] = ['array'];
                    if (!empty($propSchema->validation)) {
                        $rules[$ruleKey] = array_merge($rules[$ruleKey], $propSchema->validation);
                    }
                    $rules[$ruleKey . '.*'] = [$propSchema->items->type];
                    if (!empty($propSchema->items->validation)) {
                        $rules[$ruleKey . '.*'] = array_merge($rules[$ruleKey . '.*'], $propSchema->items->validation);
                    }
                }
                break;
            case 'object':
                foreach ($propSchema->properties ?? [] as $subPropName => $subPropSchema) {
                    $rules = $this->addPropValidationRules($rules, $ruleKey . '.' . $subPropName, $subPropSchema);
                }
                break;
        }

        return $rules;
    }

    /**
     * Format validation errors
     *
     * This method receives a (Laravel) MessageBag object and formats an error
     * array to match the entity's schema. It converts Laravel's dot notation for
     * objects and arrays:
     *
     * [
     *   foo.en: ['Error message'],
     *   foo.fr_CA: ['Error message'],
     *   bar.0.baz: ['Error message'],
     * ]
     *
     * Into an assoc array, collapsing subproperty errors into their parent prop:
     *
     * [
     *   foo: [
     *     en: ['Error message'],
     *     fr_CA: ['Error message'],
     *   ],
     *   bar: ['Error message'],
     * ]
     */
    public function formatValidationErrors(MessageBag $errorBag): array
    {
        $formatted = [];
        foreach ($errorBag->getMessages() as $ruleKey => $messages) {
            Arr::set($formatted, $ruleKey, $messages);
        }
        return $formatted;
    }

    /**
     * Set default values for an object
     *
     * Get default values from an object's schema and set them for the passed
     * object.
     *
     * localeParams are used to populate translation strings where default values
     * rely on them. For example, a locale string like the following:
     *
     * "This email was sent on behalf of {$contextName}."
     *
     * Will expect a $localeParams value like this:
     *
     * ['contextName' => 'Journal of Public Knowledge']
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param T $object The object to be modified
     * @param array $supportedLocales List of locale keys that should receive
     *  default content. Example: ['en', 'fr_CA']
     * @param string $primaryLocale Example: `en`
     * @param array $localeParams Key/value params for the translation strings
     *
     * @return T
     */
    public function setDefaults($schemaName, $object, $supportedLocales, $primaryLocale, $localeParams = [])
    {
        $schema = $this->get($schemaName);
        foreach ($schema->properties as $propName => $propSchema) {
            // Don't override existing values
            if (!is_null($object->getData($propName))) {
                continue;
            }
            if (!property_exists($propSchema, 'default') && !property_exists($propSchema, 'defaultLocaleKey')) {
                continue;
            }
            if (!empty($propSchema->multilingual)) {
                $value = [];
                foreach ($supportedLocales as $localeKey) {
                    $value[$localeKey] = $this->getDefault($propSchema, $localeParams, $localeKey);
                }
            } else {
                $value = $this->getDefault($propSchema, $localeParams, $primaryLocale);
            }
            $object->setData($propName, $value);
        }

        return $object;
    }

    /**
     * Get the default values for a specific locale
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param string $locale The locale key to get values for. Example: `en`
     * @param array $localeParams Key/value params for the translation strings
     *
     * @return array Key/value of property defaults for the specified locale
     */
    public function getLocaleDefaults($schemaName, $locale, $localeParams)
    {
        $schema = $this->get($schemaName);
        $defaults = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (empty($propSchema->multilingual) || empty($propSchema->defaultLocaleKey)) {
                continue;
            }
            $defaults[$propName] = $this->getDefault($propSchema, $localeParams, $locale);
        }

        return $defaults;
    }

    /**
     * Get a default value for a property based on the schema
     *
     * @param object $propSchema The schema definition for this property
     * @param array|null $localeParams Optional. Key/value params for the translation strings
     * @param string|null $localeKey Optional. The locale to translate into
     *
     * @return mixed Will return null if no default value is available
     */
    public function getDefault($propSchema, $localeParams = null, $localeKey = null)
    {
        $localeParams ??= [];
        switch ($propSchema->type) {
            case 'boolean':
            case 'integer':
            case 'number':
            case 'string':
                if (property_exists($propSchema, 'default')) {
                    return $propSchema->default;
                } elseif (property_exists($propSchema, 'defaultLocaleKey')) {
                    return __($propSchema->defaultLocaleKey, $localeParams, $localeKey);
                }
                break;
            case 'array':
                $value = [];
                foreach ($propSchema->default as $default) {
                    $itemSchema = $propSchema->items;
                    $itemSchema->default = $default;
                    $value[] = $this->getDefault($itemSchema, $localeParams, $localeKey);
                }
                return $value;
            case 'object':
                $value = [];
                foreach ($propSchema->properties ?? [] as $subPropName => $subPropSchema) {
                    if (!property_exists($propSchema->default, $subPropName)) {
                        continue;
                    }
                    $defaultSubProp = $propSchema->default->{$subPropName};
                    // If a prop is expected to be a string but the default value is an
                    // object with a `defaultLocaleKey` property, then we render that
                    // translation. Otherwise, we assign the values as-is and do not
                    // recursively check for nested objects/arrays inside of objects.
                    if ($subPropSchema->type === 'string' && is_object($defaultSubProp) && property_exists($defaultSubProp, 'defaultLocaleKey')) {
                        $value[$subPropName] = __($defaultSubProp->defaultLocaleKey, $localeParams, $localeKey);
                    } else {
                        $value[$subPropName] = $defaultSubProp;
                    }
                }
                return $value;
        }
        return null;
    }

    /**
     * Add multilingual props for missing values
     *
     * This method will take a set of property values and add empty entries for
     * any locales that are missing. Given the following:
     *
     * $values = [
     *	'title' => [
     *		'en' => 'The Journal of Public Knowledge',
     *	]
     * ]
     *
     * If the locales en and fr_CA are requested, it will return the following:
     *
     * $values = [
     *	'title' => [
     *		'en' => 'The Journal of Public Knowledge',
     *		'fr_CA' => '',
     *	]
     * ]
     *
     * This is primarily used to ensure API responses present a consistent data
     * structure regardless of which properties have values.
     *
     * @param string $schemaName One of the SCHEMA_... constants
     * @param array $values Key/value list of entity properties
     *
     * @return array
     */
    public function addMissingMultilingualValues($schemaName, $values, $localeKeys)
    {
        $schema = $this->get($schemaName);
        $multilingualProps = $this->getMultilingualProps($schemaName);
        foreach ($values as $key => $value) {
            if (!in_array($key, $multilingualProps)) {
                continue;
            }
            foreach ($localeKeys as $localeKey) {
                if (is_array($value) && array_key_exists($localeKey, $value)) {
                    continue;
                }
                switch ($schema->properties->{$key}->type) {
                    case 'string':
                        $values[$key][$localeKey] = '';
                        break;
                    case 'array':
                        $values[$key][$localeKey] = [];
                        break;
                    default:
                        $values[$key][$localeKey] = null;
                }
            }
        }

        return $values;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\services\PKPSchemaService', '\PKPSchemaService');
    foreach ([
        'SCHEMA_ANNOUNCEMENT',
        'SCHEMA_AUTHOR',
        'SCHEMA_CONTEXT',
        'SCHEMA_EMAIL_TEMPLATE',
        'SCHEMA_GALLEY',
        'SCHEMA_ISSUE',
        'SCHEMA_PUBLICATION',
        'SCHEMA_REVIEW_ASSIGNMENT',
        'SCHEMA_REVIEW_ROUND',
        'SCHEMA_SECTION',
        'SCHEMA_SITE',
        'SCHEMA_SUBMISSION',
        'SCHEMA_SUBMISSION_FILE',
        'SCHEMA_USER',
        'SCHEMA_USER_GROUP',
    ] as $constantName) {
        if (!defined($constantName)) {
            define($constantName, constant('PKPSchemaService::' . $constantName));
        }
    }
}

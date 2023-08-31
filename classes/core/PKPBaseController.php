<?php

namespace PKP\core;

use APP\core\Application;
use APP\core\Services;
use PKP\core\PKPRequest;
use Illuminate\Routing\Controller;
use PKP\validation\ValidatorFactory;
use PKP\statistics\PKPStatisticsHelper;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class PKPBaseController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * The PKP request object 
     */
    protected ?PKPRequest $_request = null;

    abstract public function getHandlerPath(): string;

    abstract public function getRouteGroupMiddleware(): array;

    abstract public function getGroupRoutes(): void;

    public function getPathPattern(): ?string
    {
        return null;
    }

    public function isSiteWide(): bool
    {
        return false;
    }

    public function getRequest(): PKPRequest
    {
        if (!$this->_request) {
            $this->_request = Application::get()->getRequest();
        }

        return $this->_request;
    }

    /**
     * Convert string values in boolean, integer and number parameters to their
     * appropriate type when the string is in a recognizable format.
     *
     * Converted booleans: False: "0", "false". True: "true", "1"
     * Converted integers: Anything that passes ctype_digit()
     * Converted floats: Anything that passes is_numeric()
     *
     * Empty strings will be converted to null.
     *
     * @param string $schema One of the SCHEMA_... constants
     * @param array $params Key/value parameters to be validated
     *
     * @return array Converted parameters
     * 
     * FIXME#7698: may need MODIFICATION as per need to facilitate pkp/pkp-lib#7698
     */
    public function convertStringsToSchema(string $schema, array $params): array
    {
        $schema = Services::get('schema')->get($schema);

        foreach ($params as $paramName => $paramValue) {
            if (!property_exists($schema->properties, $paramName)) {
                continue;
            }
            if (!empty($schema->properties->{$paramName}->multilingual)) {
                foreach ($paramValue as $localeKey => $localeValue) {
                    $params[$paramName][$localeKey] = $this->_convertStringsToSchema(
                        $localeValue,
                        $schema->properties->{$paramName}->type,
                        $schema->properties->{$paramName}
                    );
                }
            } else {
                $params[$paramName] = $this->_convertStringsToSchema(
                    $paramValue,
                    $schema->properties->{$paramName}->type,
                    $schema->properties->{$paramName}
                );
            }
        }

        return $params;
    }

    /**
     * Helper function to convert a string to a specified type if it meets
     * certain conditions.
     *
     * This function can be called recursively on nested objects and arrays.
     *
     * @see self::convertStringsToTypes
     *
     * @param string $type One of boolean, integer or number
     * 
     * FIXME#7698: May need MODIFICATION as per need to facilitate pkp/pkp-lib#7698
     */
    private function _convertStringsToSchema(mixed $value, mixed $type, object $schema): mixed
    {
        // Convert all empty strings to null except arrays (see note below)
        if (is_string($value) && !strlen($value) && $type !== 'array') {
            return null;
        }
        switch ($type) {
            case 'boolean':
                if (is_string($value)) {
                    if ($value === 'true' || $value === '1') {
                        return true;
                    } elseif ($value === 'false' || $value === '0') {
                        return false;
                    }
                }
                break;
            case 'integer':
                if (is_string($value) && ctype_digit($value)) {
                    return (int) $value;
                }
                break;
            case 'number':
                if (is_string($value) && is_numeric($value)) {
                    return floatval($value);
                }
                break;
            case 'array':
                if (is_array($value)) {
                    $newArray = [];
                    if (is_array($schema->items)) {
                        foreach ($schema->items as $i => $itemSchema) {
                            $newArray[$i] = $this->_convertStringsToSchema($value[$i], $itemSchema->type, $itemSchema);
                        }
                    } else {
                        foreach ($value as $i => $v) {
                            $newArray[$i] = $this->_convertStringsToSchema($v, $schema->items->type, $schema->items);
                        }
                    }
                    return $newArray;

                // An empty string is accepted as an empty array. This addresses the
                // issue where browsers strip empty arrays from post data before sending.
                // See: https://bugs.jquery.com/ticket/6481
                } elseif (is_string($value) && !strlen($value)) {
                    return [];
                }
                break;
            case 'object':
                if (is_array($value)) {
                    // In some cases a property may be defined as an object but it may not
                    // contain specific details about that object's properties. In these cases,
                    // leave the properties alone.
                    if (!property_exists($schema, 'properties')) {
                        return $value;
                    }
                    $newObject = [];
                    foreach ($schema->properties as $propName => $propSchema) {
                        if (!isset($value[$propName])) {
                            continue;
                        }
                        $newObject[$propName] = $this->_convertStringsToSchema($value[$propName], $propSchema->type, $propSchema);
                    }
                    return $newObject;
                }
                break;
        }
        return $value;
    }

    /**
     * A helper method to validate start and end date params for stats in API controllers
     *
     * 1. Checks the date formats
     * 2. Ensures a start date is not earlier than PKPStatisticsHelper::STATISTICS_EARLIEST_DATE
     * 3. Ensures an end date is no later than yesterday
     * 4. Ensures the start date is not later than the end date
     *
     * @param array     $params             The params to validate
     * @param string    $dateStartParam     Where the find the start date in the array of params
     * @param string    $dateEndParam       Where to find the end date in the array of params
     *
     * @return bool|string  True if they validate, or a string which
     *                      contains the locale key of an error message.
     * 
     * FIXME#7698: May need MODIFICATION as per need to facilitate pkp/pkp-lib#7698
     */
    protected function _validateStatDates(array $params, string $dateStartParam = 'dateStart', string $dateEndParam = 'dateEnd'): bool|string
    {
        $validator = ValidatorFactory::make(
            $params,
            [
                $dateStartParam => [
                    'date_format:Y-m-d',
                    'after_or_equal:' . PKPStatisticsHelper::STATISTICS_EARLIEST_DATE,
                    'before_or_equal:' . $dateEndParam,
                ],
                $dateEndParam => [
                    'date_format:Y-m-d',
                    'before_or_equal:yesterday',
                    'after_or_equal:' . $dateStartParam,
                ],
            ],
            [
                '*.date_format' => 'invalidFormat',
                $dateStartParam . '.after_or_equal' => 'tooEarly',
                $dateEndParam . '.before_or_equal' => 'tooLate',
                $dateStartParam . '.before_or_equal' => 'invalidRange',
                $dateEndParam . '.after_or_equal' => 'invalidRange',
            ]
        );

        if ($validator->fails()) {
            $errors = $validator->errors()->getMessages();
            if ((!empty($errors[$dateStartParam]) && in_array('invalidFormat', $errors[$dateStartParam]))
                    || (!empty($errors[$dateEndParam]) && in_array('invalidFormat', $errors[$dateEndParam]))) {
                return 'api.stats.400.wrongDateFormat';
            }
            if (!empty($errors[$dateStartParam]) && in_array('tooEarly', $errors[$dateStartParam])) {
                return 'api.stats.400.earlyDateRange';
            }
            if (!empty($errors[$dateEndParam]) && in_array('tooLate', $errors[$dateEndParam])) {
                return 'api.stats.400.lateDateRange';
            }
            if ((!empty($errors[$dateStartParam]) && in_array('invalidRange', $errors[$dateStartParam]))
                    || (!empty($errors[$dateEndParam]) && in_array('invalidRange', $errors[$dateEndParam]))) {
                return 'api.stats.400.wrongDateRange';
            }
        }

        return true;
    }
}
<?php

/**
 * @file includes/functions.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup index
 *
 * @brief Contains definitions for common functions used system-wide.
 * Any frequently-used functions that cannot be put into an appropriate class should be added here.
 */


/*
 * Constants for expressing human-readable data sizes in their respective number of bytes.
 */
define('KB_IN_BYTES', 1024);
define('MB_IN_BYTES', 1024 * KB_IN_BYTES);
define('GB_IN_BYTES', 1024 * MB_IN_BYTES);
define('TB_IN_BYTES', 1024 * GB_IN_BYTES);
define('PB_IN_BYTES', 1024 * TB_IN_BYTES);
define('EB_IN_BYTES', 1024 * PB_IN_BYTES);
define('ZB_IN_BYTES', 1024 * EB_IN_BYTES);
define('YB_IN_BYTES', 1024 * ZB_IN_BYTES);

/**
 * Instantiates an object for a given fully qualified
 * class name after executing several checks on the class.
 *
 * The checks prevent certain vulnerabilities when
 * instantiating classes generically.
 *
 * NB: We currently only support one constructor
 * argument. If we need arbitrary arguments later
 * we can do that via func_get_args() which allows us
 * to handle an arbitrary number of optional
 * constructor arguments. The $constructorArg
 * parameter needs to be last in the parameter list
 * to be forward compatible with this potential use
 * case.
 *
 * @deprecated 3.4.0 pkp/pkp-lib#8186
 *
 * @param string $fullyQualifiedClassName
 * @param string|array $expectedTypes the class
 * 	must conform to at least one of the given types.
 * @param string|array $expectedPackages the class
 *  must be part of at least one of the given packages.
 * @param string|array $expectedMethods names of methods
 *  that must all be present for the requested class.
 * @param mixed $constructorArg constructor argument
 *
 * @return object|boolean the instantiated object or false
 *  if the class instantiation didn't result in the expected
 *  type.
 */
function &instantiate($fullyQualifiedClassName, $expectedTypes = null, $expectedPackages = null, $expectedMethods = null, $constructorArg = null)
{
    $errorFlag = false;
    // Validate the class name
    if (!preg_match('/^[a-zA-Z0-9_.]+$/', $fullyQualifiedClassName)) {
        return $errorFlag;
    }

    // Validate the class package
    if (!is_null($expectedPackages)) {
        if (is_scalar($expectedPackages)) {
            $expectedPackages = [$expectedPackages];
        }
        $validPackage = false;
        foreach ($expectedPackages as $expectedPackage) {
            // No need to use String class here as class names are always US-ASCII
            if (substr($fullyQualifiedClassName, 0, strlen($expectedPackage) + 1) == $expectedPackage . '.') {
                $validPackage = true;
                break;
            }
        }

        // Raise a fatal error if the class does not belong
        // to any of the expected packages. This is to prevent
        // certain types of code inclusion attacks.
        if (!$validPackage) {
            // Construct meaningful error message.
            $expectedPackageCount = count($expectedPackages);
            $separator = '';
            $expectedPackageString = '';
            foreach ($expectedPackages as $expectedPackageIndex => $expectedPackage) {
                if ($expectedPackageIndex > 0) {
                    $separator = ($expectedPackageIndex == $expectedPackageCount - 1 ? ' or ' : ', ');
                }
                $expectedPackageString .= $separator . '"' . $expectedPackage . '"';
            }
            throw new Exception('Trying to instantiate class "' . $fullyQualifiedClassName . '" which is not in any of the expected packages ' . $expectedPackageString . '.');
        }
    }

    // Import the requested class
    $filePath = BASE_SYS_DIR . '/' . str_replace('.', '/', $fullyQualifiedClassName) . '.php';
    if (file_exists($filePath)) {
        include_once($filePath);
    }

    // Identify the class name
    $fullyQualifiedClassNameParts = explode('.', $fullyQualifiedClassName);
    $className = array_pop($fullyQualifiedClassNameParts);

    // Type check I: The requested class should be declared by now.
    if (!class_exists($className)) {
        throw new Exception('Cannot instantiate class. Class "' . $className . '" is not declared in "' . $fullyQualifiedClassName . '".');
    }

    // Ensure all expected methods are declared.
    $expectedMethods = (array) $expectedMethods; // Possibly scalar or null; ensure array
    $declaredMethods = get_class_methods($className);
    if (count(array_intersect($expectedMethods, $declaredMethods)) != count($expectedMethods)) {
        return $errorFlag;
    }

    // Instantiate the requested class
    if (is_null($constructorArg)) {
        $classInstance = new $className();
    } else {
        $classInstance = new $className($constructorArg);
    }

    // Type check II: The object must conform to the given interface (if any).
    if (!is_null($expectedTypes)) {
        if (is_scalar($expectedTypes)) {
            $expectedTypes = [$expectedTypes];
        }
        $validType = false;
        foreach ($expectedTypes as $expectedType) {
            if (is_a($classInstance, $expectedType)) {
                $validType = true;
                break;
            }
        }
        if (!$validType) {
            return $errorFlag;
        }
    }

    return $classInstance;
}

/**
 * Recursively strip HTML from a (multidimensional) array.
 *
 * @param array $values
 *
 * @return array the cleansed array
 */
function stripAssocArray($values)
{
    foreach ($values as $key => $value) {
        if (is_scalar($value)) {
            $values[$key] = strip_tags($values[$key]);
        } else {
            $values[$key] = stripAssocArray($values[$key]);
        }
    }
    return $values;
}

/**
 * @copydoc Core::cleanFileVar
 * Warning: Call this function from Core class. It is only exposed here to make
 * it available early in bootstrapping.
 */
function cleanFileVar($var)
{
    return preg_replace('/[^\w\-]/u', '', $var);
}

/**
 * Translates a pluralized locale key
 */
function __p(string $key, int $number, array $replace = [], ?string $locale = null): string
{
    return trans_choice($key, $number, $replace, $locale);
}

/**
 * Check if run on CLI
 * 
 * @deprecated 3.5.0 use PKPContainer::getInstance()->runningInConsole()
 */
if (!function_exists('runOnCLI')) {
    function runOnCLI(?string $scriptPath = null): bool
    {
        return \PKP\core\PKPContainer::getInstance()->runningInConsole($scriptPath);
    }
}

/**
 * Converts a shorthand byte value to an integer byte value.
 *
 * @link https://secure.php.net/manual/en/function.ini-get.php
 * @link https://secure.php.net/manual/en/faq.using.php#faq.using.shorthandbytes
 *
 * @param   string  A (PHP ini) byte value, either shorthand or ordinary.
 *
 * @return  int     An integer byte value.
 */
if (!function_exists('convertHrToBytes')) {
    function convertHrToBytes(string $value): int
    {
        $value = strtolower(trim($value));
        $bytes = (int) $value;

        if (false !== strpos($value, 'g')) {
            $bytes *= GB_IN_BYTES;
        } elseif (false !== strpos($value, 'm')) {
            $bytes *= MB_IN_BYTES;
        } elseif (false !== strpos($value, 'k')) {
            $bytes *= KB_IN_BYTES;
        }

        // Deal with large (float) values which run into the maximum integer size.
        return min($bytes, PHP_INT_MAX);
    }
}

/**
 * Check if valid JSON
 *
 * @param   mixed  $data
 *
 * @return  bool
 */
if (!function_exists('isValidJson')) {
    function isValidJson(mixed $data): bool
    {
        if (!empty($data)) {
            @json_decode($data);
            return (json_last_error() === JSON_ERROR_NONE);
        }
        return false;
    }
}

/**
 * Convert a query parameter to an array
 *
 * This method will convert a query parameter to an array, and
 * supports a comma-separated list of values
 *
 * @param mixed $value
 *
 * @return array
 */
if (!function_exists('paramToArray')) {
    function paramToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            return explode(',', $value);
        }

        return [$value];
    }
}

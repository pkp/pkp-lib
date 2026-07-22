<?php

/**
 * @file classes/citation/externalServices/ExternalServicesHelper.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Helpers
 *
 * @ingroup citation
 *
 * @brief Provides static helper methods.
 */

namespace PKP\citation\externalServices;

use APP\core\Application;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

class ExternalServicesHelper
{
    /**
     * Gets an element of an array from an array containing the path to the keys for each dimension.
     *
     * @param array $array The array to retrieve the value from.
     * @param array $path An array containing the path to the value, e.g., ['person', 'name', 'value'].
     *
     * @return mixed The value (string or array) retrieved from the specified path. If the path does not exist, null is returned.
     */
    public static function getValueFromArrayPath(array $array, array $path): mixed
    {
        $key = array_shift($path);

        if (!isset($array[$key])) {
            return null;
        }

        if (empty($path)) {
            return $array[$key];
        } else {
            return self::getValueFromArrayPath($array[$key], $path);
        }
    }

    /**
     * Makes HTTP request to the API and returns the response as an array.
     *
     * @param string $url The API endpoint URL.
     * @param array $options Guzzle request options.
     * @param int|null &$retryAfter Set to the response's Retry-After value (in seconds), if the server sent one.
     *
     * @return array|int|null The response as an associative array, request status code or null.
     */
    public static function apiRequest(string $url, array $options = [], ?int &$retryAfter = null): array|int|null
    {
        $retryAfter = null;
        $httpClient = Application::get()->getHttpClient();

        try {
            $response = $httpClient->request('GET', $url, $options);

            if (!str_contains('200,201,202', (string)$response->getStatusCode())) {
                return $response->getStatusCode();
            }

            $result = json_decode($response->getBody(), true);

            if (empty($result) || json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $result;

        } catch (RequestException $e) {
            // Guzzle throws (rather than returning a response) for 4xx/5xx statuses by default,
            // so this is where a real 429/408/504/etc from the service actually needs to be caught.
            $response = $e->getResponse();

            if (!$response) {
                error_log(__METHOD__ . ' ' . $e->getMessage());
                return null;
            }

            if ($response->hasHeader('Retry-After')) {
                $retryAfter = (int) $response->getHeaderLine('Retry-After');
            }

            return $response->getStatusCode();

        } catch (GuzzleException|Exception $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
        }

        return null;
    }
}

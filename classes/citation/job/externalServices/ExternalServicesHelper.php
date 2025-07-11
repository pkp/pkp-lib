<?php

/**
 * @file classes/citation/job/externalServices/ExternalServicesHelper.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Helpers
 *
 * @ingroup citation
 *
 * @brief Provides static helper methods.
 */

namespace PKP\citation\job\externalServices;

use Application;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ExternalServicesHelper
{
    /**
     * Gets an element of an array from an array containing the path to the keys for each dimension.
     *
     * @param array $array The array to retrieve the value from.
     * @param array $path  An array containing the path to the value, e.g., ['person', 'name', 'value'].
     * @return string The value retrieved from the specified path. If the path does not exist, an empty string is returned.
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
     * @param string $method The HTTP method (e.g., 'POST', 'GET').
     * @param string $url The API endpoint URL.
     * @param array $options Additional options for the HTTP request.
     * @return array The response data as an associative array.
     */
    public static function apiRequest(string $method, string $url, array $options): array
    {
        if ($method !== 'POST' && $method !== 'GET') return [];

        $httpClient = new Client(
            [
                'headers' => [
                    'User-Agent' => Application::get()->getName(),
                    'Accept' => 'application/json'
                ],
                'verify' => false
            ]
        );

        try {
            $response = $httpClient->request($method, $url, $options);

            if (!str_contains('200,201,202', (string)$response->getStatusCode())) {
                return [];
            }

            $result = json_decode($response->getBody(), true);

            if (empty($result) || json_last_error() !== JSON_ERROR_NONE) return [];

            return $result;

        } catch (GuzzleException $e) {
            error_log(__METHOD__ . ' ' . $e->getMessage());
        }

        return [];
    }
}

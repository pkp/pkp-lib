<?php

/**
 * @file classes/testing/scenario/SpecValidator.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SpecValidator
 *
 * @ingroup testing
 *
 * @brief JSON-schema validation for scenario specs.
 *
 * Two passes, because a silently dropped spec key is the failure mode this whole
 * layer exists to prevent:
 *
 *  1. An explicit unknown-key walk that reports the DOTTED PATH of the first key
 *     the schema does not declare (`reviewRounds.0.reviewers.1.bogus`). Opis
 *     short-circuits on the first failing keyword and will happily report a
 *     nested error while hiding a stray top-level key, so this pass is our own.
 *  2. Opis (opis/json-schema, present in lib/pkp/lib/vendor) for everything else:
 *     types, required properties, enums, string lengths.
 *
 * Either pass failing raises a ScenarioException carrying the offending key,
 * which the controller renders as HTTP 400.
 */

namespace PKP\testing\scenario;

use Illuminate\Http\Response;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class SpecValidator
{
    /**
     * Validate a decoded spec against a schema given as a PHP array.
     *
     * @throws ScenarioException
     */
    public static function validate(array $spec, array $schema): void
    {
        static::assertNoUnknownKeys($spec, $schema, '');

        if (!class_exists(Validator::class)) {
            throw new ScenarioException(
                'opis/json-schema is not installed; run composer install (with dev dependencies) in lib/pkp.',
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $result = (new Validator())->validate(
            json_decode(json_encode($spec === [] ? new \stdClass() : $spec)),
            json_decode(json_encode($schema))
        );

        if ($result->isValid()) {
            return;
        }

        $formatted = (new ErrorFormatter())->formatKeyed($result->error());
        $firstPath = (string) array_key_first($formatted);

        throw new ScenarioException(
            'The scenario spec is not valid: ' . implode(' ', $formatted[$firstPath]),
            trim(str_replace('/', '.', $firstPath), '.') ?: null,
            Response::HTTP_BAD_REQUEST,
            $formatted
        );
    }

    /**
     * Walk the spec alongside the schema and throw on the first key the schema
     * does not declare. Only objects that close themselves with
     * `additionalProperties: false` are checked, which is every object in the
     * scenario schemas.
     *
     * @throws ScenarioException
     */
    protected static function assertNoUnknownKeys(mixed $value, array $schema, string $path): void
    {
        $type = $schema['type'] ?? null;

        if ($type === 'object' && is_array($value)) {
            $declared = $schema['properties'] ?? [];

            if (($schema['additionalProperties'] ?? true) === false) {
                foreach (array_keys($value) as $key) {
                    if (!array_key_exists($key, $declared)) {
                        $keyPath = $path === '' ? (string) $key : "{$path}.{$key}";
                        throw new ScenarioException(
                            "Unknown key '{$keyPath}' in the scenario spec. Scenario specs never drop "
                                . 'keys silently: either the key is misspelled, or the endpoint does not support it yet.',
                            $keyPath
                        );
                    }
                }
            }

            foreach ($value as $key => $child) {
                if (isset($declared[$key]) && is_array($declared[$key])) {
                    static::assertNoUnknownKeys(
                        $child,
                        $declared[$key],
                        $path === '' ? (string) $key : "{$path}.{$key}"
                    );
                }
            }

            return;
        }

        if ($type === 'array' && is_array($value) && isset($schema['items']) && is_array($schema['items'])) {
            foreach (array_values($value) as $index => $child) {
                static::assertNoUnknownKeys($child, $schema['items'], "{$path}.{$index}");
            }
        }
    }

    /**
     * Load a schema shipped with the shared scenario layer.
     */
    public static function load(string $name): array
    {
        $file = __DIR__ . '/schema/' . $name . '.json';

        if (!file_exists($file)) {
            throw new ScenarioException("Missing scenario schema '{$name}'.", null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Merge an app's overlay properties into a core schema.
     *
     * Overlay properties are how app-specific concepts (OJS sections and issues,
     * OMP series) enter an otherwise app-neutral schema. They are DECLARED, so an
     * OJS-only key sent to OPS fails with "unknown key" rather than being ignored.
     */
    public static function withOverlay(array $schema, array $overlayProperties): array
    {
        $schema['properties'] = array_merge($schema['properties'] ?? [], $overlayProperties);

        return $schema;
    }
}

<?php

/**
 * @file classes/core/traits/ResourceWithData.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ResourceWithData
 *
 * @brief Passes additional data to the API resources not directly related to the associated Model
 *
 */

namespace PKP\core\traits;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

trait ResourceWithData
{
    /**
     * Additional data to be passed to the resource.
     */
    protected array $data = [];

    public function __construct($resource, $key = null, array $data = [])
    {
        if (!empty($data)) {
            static::validateData($data);
            $this->data = array_merge($this->data, $data);
        }

        parent::__construct($resource);
    }

    /**
     * Override the parent method to pass additional data to the resource collection.
     */
    public static function collection($resource, array $data = [])
    {
        if (!empty($data)) {
            static::validateData($data);
        }

        return tap(static::newCollection($resource, $data), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }

    /**
     * Retrieve additional resource data by keys.
     *
     * @param string ...$keys The keys of the data entries to retrieve.
     *
     * @throws InvalidArgumentException If any of the specified keys are missing in the data array.
     *
     * @return array The array containing additional data for the resource.
     */
    public function getData(string ...$keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                throw new InvalidArgumentException(
                    "Missing required data key: {$key}"
                );
            }
            $result[] = $this->data[$key];
        }
        return $result;
    }

    /**
     * Override the parent method to set additional data to the resource.
     */
    protected static function newCollection($resource, array $data = []): AnonymousResourceCollection
    {
        return new class ($resource, static::class, $data) extends AnonymousResourceCollection {
            protected array $data = [];

            public function __construct($resource, $collects, array $data = [])
            {
                parent::__construct($resource, $collects);

                $this->collection = $resource->map(function ($item) use ($collects, $data) {
                    return in_array(ResourceWithData::class, class_uses_recursive($collects))
                        ? new $collects($item, null, $data)
                        : new $collects($item);
                });
            }
        };
    }

    /**
     * Validate that all needed data is passed to the resource.
     *
     * @param array $data The data array to validate.
     *
     * @throws InvalidArgumentException If any required key is missing in the data array.
     */
    protected static function validateData(array $data): void
    {
        $requiredKeys = static::requiredKeys(); // abstract method in each resource class to have
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required data key: {$key}");
            }
        }
    }

    /**
     * Each resource class using this trait must implement this method to specify required data.
     */
    abstract protected static function requiredKeys(): array;
}

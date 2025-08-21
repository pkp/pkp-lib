<?php

/**
 * @file api/v1/reviewers/suggestions/resources/EnrichesWithData.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EnrichesWithData
 *
 * @brief Passes additional data to the API resources
 *
 */

namespace PKP\API\v1\submissions\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

trait EnrichesWithData
{
    /**
     * Additional data to be passed to the resource.
     *
     * @var array $data [
     *   'submission' => Submission,
     *   'stageAssignments' => Collection,
     *   'users' => LazyCollection,
     *   'userGroups' => Collection,
     *   'reviewAssignments' => Collection,
     * ]
     */
    protected array $data = [];

    public function __construct($resource, $key = null, ?array $data = [])
    {
        if (!empty($data)) {
            $this->data = $data;
        }

        parent::__construct($resource);
    }

    /**
     * Override the parent method to pass additional data to the resource collection.
     */
    public static function collection($resource, ?array $data = [])
    {
        return tap(static::newCollection($resource, $data), function ($collection) {
            if (property_exists(static::class, 'preserveKeys')) {
                $collection->preserveKeys = (new static([]))->preserveKeys === true;
            }
        });
    }

    /**
     * Override the parent method to set additional data to the resource.
     */
    protected static function newCollection($resource, ?array $data = []): AnonymousResourceCollection
    {
        return new class ($resource, static::class, $data) extends AnonymousResourceCollection {
            protected array $data = [];

            public function __construct($resource, $collects, ?array $data = [])
            {
                if (!empty($data)) {
                    $this->data = $data;
                }

                parent::__construct($resource, $collects);
            }

            /**
             * Override the Resource method to include additional data to the nested resource collection for presenting through the API.
             *
             */
            public function toArray(Request $request): array
            {
                return $this->collection->map->toArray(
                    $request,
                    $this->data
                )->all();
            }
        };
    }
}

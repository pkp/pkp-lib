<?php

declare(strict_types=1);

/**
 * @file classes/job/resources/BaseResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseResource
 *
 * @brief Abstract class BaseResource
 */

namespace PKP\job\resources;

use IteratorAggregate;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource
{
    protected $resource;

    public function __construct(Model $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Create a new resource collection.
     *
     * @return Arrayable
     */
    public static function collection(IteratorAggregate $resources): Arrayable
    {
        $data = [];
        foreach ($resources as $resource) {
            $data[] = (new static($resource))->toArray();
        }

        return collect($data);
    }

    public function getResource(): Model
    {
        return $this->resource;
    }

    abstract public function toArray();
}

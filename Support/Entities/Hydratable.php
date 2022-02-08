<?php

declare(strict_types=1);

/**
 * @file Support/Entities/Hydratable.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Hydratable
 * @ingroup Support
 *
 * @brief Abstract class for Hydratable Entities
 */

namespace PKP\Support\Entities;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

abstract class Hydratable implements Arrayable
{
    public function __construct(array $data = [])
    {
        $this->hydrate($data);
    }

    abstract public function toArray();
    abstract public function isEmpty();

    public function hydrate(array $data = []): self
    {
        foreach ($data as $key => $value) {
            $convertedKey = ucwords(Str::camel($key));
            $method = 'set' . $convertedKey;

            if (!method_exists($this, $method)) {
                continue;
            }

            $this->$method($value);
        }

        return $this;
    }
}

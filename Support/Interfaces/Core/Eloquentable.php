<?php

declare(strict_types=1);

/**
 * @file Support/Interfaces/Core/Eloquentable.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Eloquentable
 * @ingroup support
 *
 * @brief Interface for Eloquent Classes
 */

namespace PKP\Support\Interfaces\Core;

use Illuminate\Contracts\Support\Arrayable;

interface Eloquentable extends Arrayable
{
    /**
     * Set an attribute in the model
     *
     * @param string $key
     *
     * @return $this
     */
    public function setAttribute($key, $value);

    /**
     * Set the model's accessors based on appends
     *
     *
     * @return $this
     */
    public function setAppends(array $appends);
}

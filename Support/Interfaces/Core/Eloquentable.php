<?php

declare(strict_types=1);

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

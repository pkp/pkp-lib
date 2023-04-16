<?php

declare(strict_types=1);

/**
 * @file classes/job/casts/DatetimeToInt.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DatetimeToInt
 *
 * @brief Cast timestamp/int to Carbon datetime
 */

namespace PKP\job\casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class DatetimeToInt implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $attributes
     *
     * @return \Carbon\Carbon
     */
    public function get($model, $key, $value, $attributes)
    {
        return Carbon::parse($value);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  string  $key
     * @param  array  $value
     * @param  array  $attributes
     *
     * @return int
     */
    public function set($model, $key, $value, $attributes)
    {
        if ($value instanceof Carbon) {
            return $value->timestamp;
        }

        return Carbon::parse($value)->timestamp;
    }
}

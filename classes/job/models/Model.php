<?php

declare(strict_types=1);

/**
 * @file classes/job/models/Model.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Model
 *
 * @brief Abstract class for Eloquent Models
 */

namespace PKP\job\models;

use Illuminate\Database\Eloquent\Model as BaseModel;
use PKP\job\interfaces\Eloquentable;

abstract class Model extends BaseModel implements Eloquentable
{
    /**
     * Return model's primary key
     *
     */
    public static function getPrimaryKey(): string
    {
        return (new static())->getKeyName();
    }
}

<?php

declare(strict_types=1);

/**
 * @file Support/Database/Model.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Model
 * @ingroup support
 *
 * @brief Abstract class for Eloquent Models
 */

namespace PKP\Support\Database;

use Illuminate\Database\Eloquent\Model as BaseEloquent;

use PKP\Support\Interfaces\Core\Eloquentable;

abstract class Model extends BaseEloquent implements Eloquentable
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

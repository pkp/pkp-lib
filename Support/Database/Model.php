<?php

declare(strict_types=1);

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

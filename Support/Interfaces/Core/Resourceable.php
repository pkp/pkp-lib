<?php

declare(strict_types=1);

/**
 * @file Support/Interfaces/Core/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 * @ingroup support
 *
 * @brief Interface for Repository Classes
 */

namespace PKP\Support\Interfaces\Core;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

use IteratorAggregate;

interface Resourceable extends Arrayable
{
    public static function collection(IteratorAggregate $resource): Arrayable;
    public function getResource(): Model;
}

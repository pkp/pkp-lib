<?php

declare(strict_types=1);

/**
 * @file classes/observers/events/DeletePreprintSearchIndex.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DeletePreprintSearchIndex
 * @ingroup core
 *
 * @brief Event for preprint search index deleting
 */

namespace APP\observers\events;

use Illuminate\Foundation\Events\Dispatchable;

class DeletePreprintSearchIndex
{
    use Dispatchable;

    /** @var int $preprintId Preprint's Id */
    public $preprintId;

    /**
     * Class construct
     *
     * @param int $preprintId Preprint's Id
     */
    public function __construct(
        int $preprintId
    ) {
        $this->preprintId = $preprintId;
    }
}

<?php

/**
 * @file classes/publication/helpers/VersionDataResource.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class VersionDataResource
 *
 * @brief Resource class for VersionData. Used for API retrieval of the base object.
 */

namespace PKP\publication\helpers;

use Illuminate\Http\Resources\Json\JsonResource;

class VersionDataResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(\Illuminate\Http\Request $request)
    {
        return [
            'stage' => $this->stage->value,
            'stageLabel' => $this->stage->label(),
            'majorNumbering' => $this->majorNumbering,
            'minorNumbering' => $this->minorNumbering,
            'display' => $this->display(),
        ];
    }
}


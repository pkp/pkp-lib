<?php

/**
 * @file classes/publication/helpers/PublicationVersionInfoResource.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationVersionInfoResource
 *
 * @brief Resource class for VersionData. Used for API retrieval of the base object.
 */

namespace PKP\publication\helpers;

use Illuminate\Http\Resources\Json\JsonResource;

class PublicationVersionInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(\Illuminate\Http\Request $request)
    {
        return [
            'versionStage' => $this->stage->value,
            'versionStageLabelKey' => $this->stage->labelKey(),
            'majorNumbering' => $this->majorNumbering,
            'minorNumbering' => $this->minorNumbering,
            'versionDisplay' => (string) $this->resource,
        ];
    }
}


<?php

declare(strict_types=1);

/**
 * @file Domains/Jobs/Resources/JobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HttpJobResource
 * @ingroup domains
 *
 * @brief Mapping class for HTTP Output values
 */

namespace PKP\Domains\Jobs\Resources;

use APP\core\Application;

use Illuminate\Database\Eloquent\Model;
use PKP\Domains\Jobs\Traits\JobResource as TraitsJobResource;
use PKP\Support\Resources\BaseResource;

class HttpJobResource extends BaseResource
{
    use TraitsJobResource;

    public function __construct(Model $resource)
    {
        parent::__construct($resource);

        $this->dateFormat = Application::get()
            ->getRequest()
            ->getContext()
            ->getLocalizedDateTimeFormatShort();
    }

    /**
     * Transform the resource into an array.
     *
     */
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'queue' => $this->resource->queue,
            'displayName' => $this->getJobName(),
            'attempts' => $this->resource->attempts,
            'created_at' => __('manager.jobs.createdAt', ['createdAt' => (isset($this->resource->created_at) ? $this->localizedFormatDate($this->resource->created_at) : '-')]),
        ];
    }
}

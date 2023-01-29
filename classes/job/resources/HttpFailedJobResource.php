<?php

declare(strict_types=1);

/**
 * @file classes/job/resources/HttpFailedJobResource.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HttpFailedJobResource
 *
 * @brief Mapping class for HTTP Output values
 */

namespace PKP\job\resources;

use APP\core\Application;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\handler\APIHandler;
use PKP\job\traits\JobResource;

class HttpFailedJobResource extends JsonResource
{
    use JobResource;

    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id'            => $this->getResource()->id,
            'displayName'   => $this->getJobName(),
            'queue'         => $this->getResource()->queue,
            'connection'    => $this->getResource()->connection,
            'failed_at'     => $this->getFailedAt(),
            'payload'       => $this->getResource()->payload,
            'exception'     => $this->getResource()->exception,
            '_hrefs'         => [
                '_details' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, 'index', 'admin', 'failedJobDetails', $this->getResource()->id),
                '_redispatch' => $request->getDispatcher()->url($request, Application::ROUTE_API, APIHandler::ADMIN_API_PREFIX, 'jobs/redispatch/' . $this->getResource()->id),
                '_delete' => $request->getDispatcher()->url($request, Application::ROUTE_API, APIHandler::ADMIN_API_PREFIX, 'jobs/failed/delete/' . $this->getResource()->id),
            ],
        ];
    }
}

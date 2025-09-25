<?php

/**
 * @file api/v1/submissions/resources/Note.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NoteResource
 *
 * @brief Transforms the API response of the note into the desired format
 *
 */

namespace PKP\API\v1\submissions\tasks\resources;

use Illuminate\Http\Resources\Json\JsonResource;
use PKP\core\traits\ResourceWithData;

class NoteResource extends JsonResource
{
    use ResourceWithData;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'contents' => $this->contents,
            'dateCreated' => $this->dateCreated->format('Y-m-d H:i:s'),
            'dateModified' => $this->dateModified?->format('Y-m-d H:i:s'),
            'createdByName' => $this->user->getFullName(),
            'createdByUsername' => $this->user->getUsername(),
            'createdByEmail' => $this->user->getEmail(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected static function requiredKeys(): array
    {
        return [
            'users',
        ];
    }
}

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
use PKP\user\User;

class NoteResource extends JsonResource
{
    use ResourceWithData;

    public function toArray($request)
    {
        [$parentResource, $users] = $this->getData('parentResource', 'users');
        if (!$this->userId) {
            $user = $users->first(fn (User $user) => $user->getId() === $parentResource->createdBy); // fallback to the task creator
        } else {
            $user = $this->user;
        }

        return [
            'id' => $this->id,
            'userId' => $user->getId(),
            'contents' => $this->contents,
            'dateCreated' => $this->dateCreated?->format('Y-m-d H:i:s'), // might be null if not saved yet
            'dateModified' => $this->dateModified?->format('Y-m-d H:i:s'),
            'createdByName' => $user->getFullName(),
            'createdByUsername' => $user->getUsername(),
            'createdByEmail' => $user->getEmail(),
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

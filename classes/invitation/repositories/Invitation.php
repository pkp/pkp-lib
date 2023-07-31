<?php

declare(strict_types=1);

/**
 * @file classes/invitation/repositories/Invitation.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Invitation
 *
 * @brief Invitation Repository
 */

namespace PKP\invitation\repositories;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use PKP\invitation\models\Invitation as PKPInvitationModel;

class Invitation extends BaseRepository
{
    public function __construct(PKPInvitationModel $model)
    {
        $this->model = $model;
    }

    public function total(): int
    {
        return $this->model
            ->notHandled()
            ->count();
    }

    public function getByKeyHash($keyHash): ?PKPInvitationModel
    {
        try {
            return $this->model
                ->notHandled()
                ->certainKeyhash($keyHash)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    public function getByInvitationFamily(string $type, string $email, int $contextId, ?int $assocId): ?PKPInvitationModel
    {
        try {
            $query = $this->model
                ->notHandled()
                ->byType($type)
                ->byEmail($email)
                ->byContextId($contextId);
            
            if (!is_null($assocId)) {
                $query->byAssocId($assocId);
            }

            return $query->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    public function cancelInvitationFamily(string $type, string $email, int $contextId, ?int $assocId): ?bool
    {
        try {
            $query = $this->model
                ->notHandled()
                ->byType($type)
                ->byEmail($email)
                ->byContextId($contextId);
            
            if (!is_null($assocId)) {
                $query->byAssocId($assocId);
            }

            $results = $query->get();

            $hadCanceled = false;
            foreach ($results as $result) {
                $result->markInvitationAsCanceled();
                $hadCanceled = true;
            }

            return $hadCanceled;
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }
}

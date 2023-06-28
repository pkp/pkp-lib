<?php

declare(strict_types=1);

/**
 * @file classes/job/repositories/Job.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Job
 *
 * @brief Job Repository
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
            ->NotHandled()
            ->count();
    }

    public function getByKeyHash($keyHash): ?PKPInvitationModel
    {
        try {
            return $this->model
                ->NotHandled()
                ->CertainKeyhash($keyHash)
                ->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return null;
        }
    }

    public function getByInvitationFamily(string $type, string $email, int $contextId, ?int $assocId): ?PKPInvitationModel
    {
        try {
            $query = $this->model
                ->NotHandled()
                ->ByType($type)
                ->ByEmail($email)
                ->ByContextId($contextId);
            
            if (!is_null($assocId)) {
                $query->ByAssocId($assocId);
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
                ->NotHandled()
                ->ByType($type)
                ->ByEmail($email)
                ->ByContextId($contextId);
            
            if (!is_null($assocId)) {
                $query->ByAssocId($assocId);
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

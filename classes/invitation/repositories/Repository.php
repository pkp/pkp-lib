<?php

/**
 * @file classes/invitation/repositories/Repository.php
 *
 * Copyright (c) 2023-2024 Simon Fraser University
 * Copyright (c) 2023-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief Invitation Repository
 */

namespace PKP\invitation\repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\Invitation;
use PKP\invitation\models\InvitationModel;

class Repository
{
    public function getById(int $id): ?Invitation
    {
        $invitationModel = InvitationModel::find($id);

        if (is_null($invitationModel)) {
            return null;
        }

        return app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
    }

    public function getByIdAndKey(int $id, string $key): ?Invitation
    {
        $invitationModel = InvitationModel::notHandled()
            ->notExpired()
            ->find($id);

        if (is_null($invitationModel)) {
            return null;
        }

        if (!password_verify($key, $invitationModel->keyHash)) {
            return null;
        }

        return app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
    }

    public function getByKey($key): ?Invitation
    {
        $keyHash = md5($key);

        $invitationModel = InvitationModel::notHandled()
            ->notExpired()
            ->certainKeyhash($keyHash)
            ->first();

        if (!isset($invitationModel)) {
            return null;
        }

        return app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
    }

    public function getInvitationByReviewerAssignmentId(int $reviewerAssignmentId) : ?Invitation
    {
        $invitationModel = InvitationModel::where('payload->reviewAssignmentId', $reviewerAssignmentId)
            ->orderBy('invitation_id', 'DESC')
            ->first();

        if (is_null($invitationModel)) {
            return null;
        }

        return app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
    }

    public function findExistingInvitation(Invitation $invitation):Collection
    {
        return InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType($invitation->getType())
            ->byNotId($invitation->getId())
            ->when(
                isset($invitation->invitationModel->userId),
                fn (Builder $q) => $q->byUserId($invitation->invitationModel->userId)
            )
            ->when(
                !isset($invitation->invitationModel->userId) && $invitation->invitationModel->email,
                fn (Builder $q) => $q->byEmail($invitation->invitationModel->email)
            )
            ->when(
                isset($invitation->invitationModel->contextId),
                fn (Builder $q) => $q->byContextId($invitation->invitationModel->contextId)
            )->get();
    }
}

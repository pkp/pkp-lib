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

    /**
     * Get an invitation by ID, check that it is both pending and not expired
     */
    public function getValidPendingById(int $id): ?Invitation
    {
        $invitationModel = InvitationModel::notHandled()
            ->notExpired()
            ->find($id);

        if ($invitationModel === null) {
            return null;
        }

        return app(Invitation::class)->getExisting($invitationModel->type, $invitationModel);
    }

    /**
     * Get an invitation by ID and key, checking that it is both pending and not expired
     */
    public function getByIdAndKey(int $id, string $key): ?Invitation
    {
        $invitation = $this->getValidPendingById($id);

        if ($invitation === null) {
            return null;
        }

        if (!password_verify($key, $invitation->invitationModel->keyHash)) {
            return null;
        }

        return $invitation;
    }

    /**
     * Get an invitation by key, checking that it is both pending and not expired
     */
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
}

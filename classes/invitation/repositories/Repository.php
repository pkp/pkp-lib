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
}

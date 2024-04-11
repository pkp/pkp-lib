<?php

/**
 * @file classes/invitation/repositories/Repository.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief Invitation Repository
 */

namespace PKP\invitation\repositories;

use Carbon\Carbon;
use PKP\invitation\invitations\BaseInvitation;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation as InvitationModel;

class Repository
{
    public function getByIdAndKey(int $id, string $key): ?BaseInvitation
    {
        $invitation = InvitationModel::notHandled()
            ->notExpired()
            ->find($id);

        if (is_null($invitation)) {
            return null;
        }

        if (!password_verify($key, $invitation->keyHash)) {
            return null;
        }

        return $this->constructInvitationFromModel($invitation);
    }

    public function getByMD5Key($key): ?BaseInvitation
    {
        $keyHash = md5($key);
        
        $invitationModel = InvitationModel::notHandled()
            ->notExpired()
            ->certainKeyhash($keyHash)
            ->first();

        if (!isset($invitationModel)) {
            return null;
        }

        return $this->constructInvitationFromModel($invitationModel);
    }

    public function constructInvitationFromModel(InvitationModel $invitationModel): ?BaseInvitation
    {
        $className = $invitationModel->className;

        if (!class_exists($className)) {
            throw new \Exception("The class name does not exist. Invitation can't be created");
        }

        $retInvitation = new $className(...$invitationModel->payload);
        $retInvitation->setInvitationModel($invitationModel);

        return $retInvitation;
    }

    public function addInvitation(BaseInvitation $invitationBO): int
    {
        $invitationModelData = [
            'keyHash' => $invitationBO->getKeyHash(),
            'userId' => $invitationBO->userId,
            'assocId' => $invitationBO->assocId,
            'expiryDate' => $invitationBO->expirationDate,
            'payload' => $invitationBO->getPayload(),
            'createdAt' => Carbon::now(),
            'updatedAt' => Carbon::now(),
            'status' => InvitationStatus::PENDING,
            'className' => $invitationBO->className,
            'email' => $invitationBO->email,
            'contextId' => $invitationBO->contextId
        ];

        $model = InvitationModel::create($invitationModelData);

        return $model->id;
    }

    public function updatePayload(BaseInvitation $invitationBO, array $attributesToUpdate = []): ?BaseInvitation
    {
        $filteredAttributes = collect($attributesToUpdate)
            ->except($invitationBO->getExcludedUpdatePayloadVariables())
            ->all();

        foreach ($filteredAttributes as $attr => $value) {
            if (property_exists($invitationBO, $attr)) {
                $invitationBO->$attr = $value;
            }
        }

        $payload = $invitationBO->getPayload();
        
        try {
            $invitationModel = InvitationModel::findOrFail($invitationBO->getId());
            $invitationModel->update(['payload' => $payload]);
            
            return $this->getById($invitationBO->getId());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function markAs(BaseInvitation $invitation, InvitationStatus $status): ?BaseInvitation
    {
        try {
            $model = InvitationModel::findOrFail($invitation->getId());
            $model->update(['status' => $status->value]);
            
            return $this->getById($invitation->getId());
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function getById(int $id): ?BaseInvitation
    {
        $invitation = InvitationModel::find($id);

        if (is_null($invitation)) {
            return null;
        }

        return $this->constructInvitationFromModel($invitation);
    }
}

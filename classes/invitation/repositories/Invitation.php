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

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PKP\invitation\invitations\BaseInvitation;
use PKP\invitation\invitations\enums\InvitationStatus;
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
        return $this->model
            ->notHandled()
            ->certainKeyhash($keyHash)
            ->firstOrFail();
    }

    public function getBOByKeyHash($keyHash): ?BaseInvitation
    {
        $invitationModel = $this->getByKeyHash($keyHash);

        if (!isset($invitationModel)) {
            return null;
        }

        return $this->constructBOFromModel($invitationModel);
    }

    public function getByIdAndKey(int $id, string $key): ?BaseInvitation
    {
        $invitation = $this->get($id);

        if (is_null($invitation)) {
            return null;
        }

        if (!password_verify($key, $invitation->keyHash)) {
            return null;
        }

        return $this->constructBOFromModel($invitation);
    }

    /**
     * Get a collection of BaseInvitation objects with specific properties.
     *
     * @return Collection<BaseInvitation>
     */
    public function getByProperties(string $className, int $contextId, ?string $email = null, ?int $assocId = null, ?int $userId = null): Collection
    {
        $query = $this->model
            ->notHandled()
            ->byClassName($className)
            ->byContextId($contextId);

        if (!is_null($assocId)) {
            $query->byAssocId($assocId);
        }

        if (!is_null($userId)) {
            $query->byUserId($userId);
        }

        if (!is_null($email)) {
            $query->byEmail($email);
        }

        $results = $query->get();
        
        $invitations = new Collection();
        foreach ($results as $result) {
            $bo = $this->constructBOFromModel($result);
            $invitations->add($bo);
        }

        return $invitations;
    }

    public function constructBOFromModel(PKPInvitationModel $invitationModel): ?BaseInvitation
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

        $model = $this->add($invitationModelData);

        return $model->id;
    }
}

<?php

/**
 * @file classes/invitation/repositories/Invitation.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Invitation
 *
 * @brief Invitation Repository
 */

namespace PKP\invitation\repositories;

use APP\core\Services;
use APP\submission\Submission;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PKP\context\Context;
use PKP\decision\DecisionType;
use PKP\invitation\invitations\BaseInvitation;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation as PKPInvitationModel;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Invitation extends BaseRepository
{
    public function __construct(PKPInvitationModel $model)
    {
        $this->model = $model;
        $this->query = $this->newQuery();
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
            ->notExpired()
            ->certainKeyhash($keyHash)
            ->first();
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
        $invitation = $this->model
            ->notHandled()
            ->notExpired()
            ->find($id);

        if (is_null($invitation)) {
            return null;
        }

        if (!password_verify($key, $invitation->keyHash)) {
            return null;
        }

        return $this->constructBOFromModel($invitation);
    }

    public function filterByStatus(InvitationStatus $status): Invitation
    {
        $this->query->byStatus($status);
        return $this;
    }

    public function filterByContextId(?int $contextId): Invitation
    {
        $this->query->byContextId($contextId);
        return $this;
    }

    public function filterByClassName(string $className): Invitation
    {
        $this->query->byClassName($className);
        return $this;
    }

    public function filterByUserId(?int $userId): Invitation
    {
        $this->query->byUserId($userId);
        return $this;
    }

    public function filterByAssocId(int $assocId): Invitation
    {
        $this->query->byAssocId($assocId);
        return $this;
    }

    /**
     * Filter invitations by whether they are expired
     */
    public function expired(): Invitation
    {
        $this->query->expired();
        return $this;
    }

    /**
     * Filter invitations by whether they are not expired
     */
    public function notExpired(): Invitation
    {
        $this->query->notExpired();
        return $this;
    }

    /**
     * Get a collection of BaseInvitation objects from filtered queries
     *
     * @return Collection<BaseInvitation>
     */
    public function getMany(): Collection
    {
        $results = $this->query->get();

        $invitations = new Collection();
        foreach ($results as $result) {
            $bo = $this->constructBOFromModel($result);
            $invitations->add($bo);
        }

        return $invitations;
    }

    /**
     * Get a BaseInvitation object from filtered queries
     *
     * @return Collection<BaseInvitation>
     */
    public function getFirst(): ?BaseInvitation
    {
        $invitation = $this->query->first();

        if (!isset($invitation)) {
            return null;
        }

        return $this->constructBOFromModel($invitation);
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
    public function limit($count): Invitation
    {
        $this->query->limit($count);
        return $this;
    }

    public function offset($count): Invitation
    {
        $this->query->offset($count);
        return $this;
    }

    /**
     * Validate properties for a decision
     *
     * Perform validation checks on data used to add a decision. It is not
     * possible to edit a decision.
     *
     * @param array $props A key/value array with the new data to validate
     * @param Submission $submission The submission for this decision
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Decision::validate [[&$errors, $props]]
     */
    public function validate(array $props): array
    {
        $schemaService = Services::get('schema');
        // Return early if no valid decision type exists
        if (isset($props['userId']) && !$props['user']) {
            return ['userId' => [__('invitation.userId.invalid')]];
        }
        if(isset($props['userId']) && ($props['user']['email'] != $props['email'])){
            return ['email' => [__('invitation.email.invalid')]];
        }

        $validator = ValidatorFactory::make(
            $props,
            $schemaService->getValidationRules(PKPSchemaService::SCHEMA_USER_INVITATION, []),
        );

        // Check required
        ValidatorFactory::required(
            $validator,
            null,
            $schemaService->getRequiredProps(PKPSchemaService::SCHEMA_USER_INVITATION),
            $schemaService->getMultilingualProps(PKPSchemaService::SCHEMA_USER_INVITATION),
            [],
            ''
        );
        $errors = [];
        if ($validator->fails()) {
            $errors = $schemaService->formatValidationErrors($validator->errors());
        }
        return $errors;
    }
}

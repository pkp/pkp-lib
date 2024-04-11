<?php

/**
 * @file classes/invitation/invitations/BaseInvitation.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BaseInvitation
 *
 * @brief Abstract class for all Invitations
 */

namespace PKP\invitation\invitations;

use APP\core\Application;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\context\Context;
use PKP\facades\Repo;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation;
use PKP\pages\invitation\PKPInvitationHandler;
use PKP\security\Validation;
use ReflectionClass;
use Symfony\Component\Mailer\Exception\TransportException;

abstract class BaseInvitation
{
    public const DEFAULT_EXPIRY_DAYS = 3;

    /**
     * The name of the class name of the specific invitation
     */
    public string $className;
    private string $keyHash;
    public string $key;
    public DateTime $expirationDate;

    private int $id;

    protected ?Mailable $mailable = null;
    protected ?Context $context = null;

    public function __construct(
        public ?int $userId,
        public ?string $email,
        public ?int $contextId,
        public ?int $assocId,
        ?int $expiryDays = null
    ) {
        $expiryDays ??= Config::getVar('invitations', 'expiration_days', self::DEFAULT_EXPIRY_DAYS);
        $this->expirationDate = Carbon::now()->addDays($expiryDays);
        $this->className = $this::class;
    }

    public function getPayload(): array
    {
        $values = [];

        $reflection = new ReflectionClass($this->className);
        $constructor = $reflection->getConstructor();

        if ($constructor) {
            // If the constructor exists, get its parameters
            $parameters = $constructor->getParameters();

            // Loop through the parameters and get their values from the object
            foreach ($parameters as $parameter) {
                $propertyName = $parameter->getName();
                $propertyValue = $reflection->getProperty($propertyName)->getValue($this);
                $values[$propertyName] = $propertyValue;
            }
        }

        return $values;
    }

    public function finaliseAccept(): void
    {
        Repo::invitation()
            ->markAs($this, InvitationStatus::ACCEPTED);
    }

    public function finaliseDecline(): void
    {
        Repo::invitation()
            ->markAs($this, InvitationStatus::DECLINED);
    }

    abstract function acceptHandle(): void;

    abstract function declineHandle(): void;

    abstract public function getMailable(): ?Mailable;
    abstract public function preDispatchActions(): bool;

    public function getAcceptUrl(): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                PKPInvitationHandler::REPLY_PAGE,
                PKPInvitationHandler::REPLY_OP_ACCEPT,
                null,
                [
                    'id' => $this->getId(),
                    'key' => $this->key,
                ]
            );
    }
    public function getDeclineUrl(): string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                PKPInvitationHandler::REPLY_PAGE,
                PKPInvitationHandler::REPLY_OP_DECLINE,
                null,
                [
                    'id' => $this->getId(),
                    'key' => $this->key,
                ]
            );
    }

    public function dispatch(bool $sendEmail = false): bool
    {
        // Need to return error messages also?
        if (!$this->preDispatchActions()) {
            return false;
        }

        if (!isset($this->keyHash)) {
            if (!isset($this->key)) {
                $this->key = Validation::generatePassword();
            }

            $this->keyHash = self::makeKeyHash($this->key);
        }

        $invitationId = Repo::invitation()
            ->addInvitation($this);

        $this->setId($invitationId);

        $mailable = $this->getMailable();

        if ($sendEmail && isset($mailable)) {
            try {
                Mail::to($this->email)
                    ->send($mailable);

            } catch (TransportException $e) {
                trigger_error('Failed to send email invitation: ' . $e->getMessage(), E_USER_ERROR);
            }
        }

        return true;
    }

    public function setMailable(Mailable $mailable): void
    {
        $this->mailable = $mailable;
    }

    public function setKeyHash(string $keyHash): void
    {
        $this->keyHash = $keyHash;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function setExpirationDate(Carbon $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function setInvitationModel(Invitation $invitationModel)
    {
        $this->keyHash = $invitationModel->keyHash;
        $this->expirationDate = $invitationModel->expiryDate;
        $this->id = $invitationModel->id;
    }

    public static function makeKeyHash($key): string
    {
        return password_hash($key, PASSWORD_BCRYPT);
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get constructor parameters for exclusion.
     */
    public function getExcludedUpdatePayloadVariables(): array 
    {
        $reflection = new ReflectionClass(BaseInvitation::class);
        $constructor = $reflection->getConstructor();
        $parameters = $constructor ? $constructor->getParameters() : [];
        return array_map(function ($param) {
            return $param->getName();
        }, $parameters);
    }
}

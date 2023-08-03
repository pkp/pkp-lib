<?php

declare(strict_types=1);

/**
 * @file classes/invitation/invitations/BaseInvitation.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
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

    protected ?Mailable $mailable = null;
    protected ?Context $context = null;

    public function __construct(
        public ?int $userId,
        public ?string $email,
        public int $contextId,
        public ?int $assocId,
        ?int $expiryDays = null
    ) {
        $expiryDays ??= Config::getVar('invitations', 'expiration_days', self::DEFAULT_EXPIRY_DAYS);
        $this->expirationDate = Carbon::now()->addDays($expiryDays);
        $this->className = $this::class;
    }

    public function getPayload(): array
    {
        $vars = get_object_vars($this);

        foreach ($this->getExcludedPayloadVariables() as $excludedPayloadVariable) {
            unset($vars[$excludedPayloadVariable]);
        }

        return $vars;
    }

    public function markStatus(InvitationStatus $status): void
    {
        $invitation = Repo::invitation()
            ->getByKeyHash($this->keyHash);

        if (is_null($invitation)) {
            throw new Exception('This invitation was not found');
        }
        
        $invitation->markAs($status);
    }

    public function acceptHandle(): void
    {
        $this->markStatus(InvitationStatus::ACCEPTED);
    }
    public function declineHandle(): void
    {
        $this->markStatus(InvitationStatus::DECLINED);
    }

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

        Repo::invitation()->addInvitation($this);

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

    public function isKeyValid(string $key): bool
    {
        $keyHash = self::makeKeyHash($key);

        return $keyHash == $this->keyHash;
    }

    public function getExcludedPayloadVariables(): array
    {
        return [
            'mailable',
            'context',
            'userId',
            'assocId',
            'key',
            'keyHash',
            'expirationDate',
            'className',
            'email',
            'contextId',
        ];
    }

    public function setMailable(Mailable $mailable): void
    {
        $this->mailable = $mailable;
    }

    public function setKeyHash(string $keyHash): void
    {
        $this->keyHash = $keyHash;
    }

    public function setExpirationDate(Carbon $expirationDate): void
    {
        $this->expirationDate = $expirationDate;
    }

    public function setInvitationModel(Invitation $invitationModel)
    {
        $this->keyHash = $invitationModel->keyHash;
        $this->expirationDate = $invitationModel->expiryDate;
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        $currentDateTime = Carbon::now();

        if ($this->expirationDate > $currentDateTime) {
            return false;
        }

        return false;
    }

    static public function makeKeyHash($key): string
    {
        return password_hash($key, PASSWORD_BCRYPT);
    }
}

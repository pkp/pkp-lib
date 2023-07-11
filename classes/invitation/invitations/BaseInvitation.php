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
 *
 * @brief Abstract class for all Invitations
 */

namespace PKP\invitation\invitations;

use APP\core\Application;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;
use PKP\config\Config;
use PKP\facades\Repo;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\models\Invitation;
use PKP\pages\invitation\PKPInvitationHandler;
use PKP\security\Validation;
use Symfony\Component\Mailer\Exception\TransportException;

abstract class BaseInvitation
{
    use SerializesModels;

    /**
     * The name of the class name of the specific invitation
     */
    public string $className;
    public string $keyHash;
    public DateTime $expirationDate;
    private Invitation $invitationModel;

    public function __construct(public string $email, public int $contextId, public ?int $assocId)
    {
        $this->expirationDate = Carbon::now()->addDays(Config::getVar('invitations', 'expiration_days', 3))->toDateTime();
        $this->className = get_class($this);
    }

    public function getPayload()
    {
        return get_object_vars($this);
    }

    public function invitationMarkStatus(InvitationStatus $status) 
    {
        $invitation = Repo::invitation()
            ->getByKeyHash($this->keyHash);

        if (is_null($invitation)) {
            throw new Exception('This invitation was not found');
        }

        switch ($status) {
            case InvitationStatus::ACCEPTED:
                $invitation->markInvitationAsAccepted();
                break;
            case InvitationStatus::DECLINED:
                $invitation->markInvitationAsDeclined();
                break;
            case InvitationStatus::EXPIRED:
                $invitation->markInvitationAsExpired();
                break;
            case InvitationStatus::CANCELLED:
                $invitation->markInvitationAsCanceled();
                break;
            default:
                throw new Exception('Invalid Invitation type');
        }
    }

    public function invitationAcceptHandle() : bool
    {
        $this->invitationMarkStatus(InvitationStatus::ACCEPTED);

        return true;
    }
    public function invitationDeclineHandle() : bool
    {
        $this->invitationMarkStatus(InvitationStatus::DECLINED);

        return true;
    }

    abstract public function getInvitationMailable() : Mailable;
    abstract public function preDispatchActions() : bool;

    public function getAcceptInvitationUrl() : string
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
                    'key' => $this->keyHash,
                ]
            );
    }
    public function getDeclineInvitationUrl() : string
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
                    'key' => $this->keyHash,
                ]
            );
    }

    public function dispatch() : bool
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        // Need to return error messages also?
        if (!$this->preDispatchActions()) {
            return false;
        }

        $key = Validation::generatePassword();

        $this->keyHash = md5($key);

        $invitationModelData = [
            'context' => Repo::invitation()::CONTEXT_INVITATION,
            'key_hash' => $this->keyHash,
            'user_id' => $user->getId(),
            'assoc_id' => $this->assocId,
            'expiry_date' => $this->expirationDate->getTimestamp(),
            'payload' => $this->getPayload(),
            'created_at' => Carbon::now()->timestamp,
            'updated_at' => Carbon::now()->timestamp,
            'status' => InvitationStatus::PENDING,
            'type' => $this->className,
            'invitation_email' => $this->email,
            'context_id' => $this->contextId
        ];

        $invitationModel = Invitation::create($invitationModelData);
        $this->setInvitationModel($invitationModel);
        
        try {
            $mailable = $this->getInvitationMailable();
            
            Mail::to($this->email)
                ->send($mailable);

        } catch (TransportException $e) {
            trigger_error('Failed to send email invitation: ' . $e->getMessage(), E_USER_ERROR);
        }

        return true;
    }

    public function setInvitationModel(Invitation $invitationModel)
    {
        $this->invitationModel = $invitationModel;
        $this->keyHash = $invitationModel->keyHash;
    }
}

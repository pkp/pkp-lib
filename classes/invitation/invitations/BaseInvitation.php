<?php

declare(strict_types=1);

/**
 * @file classes/invitation/invitations/BaseInvitation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
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
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable; // should be use PKP\mail\Mailable;
use phpDocumentor\Reflection\Types\Void_;
use PKP\facades\Repo;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\invitations\enums\InvitationType;
use PKP\invitation\models\Invitation;
use PKP\pages\invitation\PKPInvitationHandler;
use PKP\security\Validation;
use Symfony\Component\Mailer\Exception\TransportException;

abstract class BaseInvitation
{
    use SerializesModels;

    /**
     * The name of the connection the job should be sent to.
     *
     * @var string|null
     */
    public $className;

    public array $data;

    public string $type;

    public string $keyHash;

    public int $contextId;
    public ?int $assocId;

    public DateTime $expirationDate;

    private Invitation $invitationModel;
    /**
     * eMail
     */
    protected string $email;

    public function __construct(string $type, string $email, int $contextId, ?int $assocId)
    {
        $this->type = $type;
        $this->email = $email;
        $this->contextId = $contextId;
        $this->assocId = $assocId;

        // This should be taken in another place or taken in a journal bases?
        $this->expirationDate = Carbon::now()->addDays(3)->toDateTime();

        $this->populateData();
    }

    protected function populateData()
    {
        $this->className = get_class($this);
        $this->data = get_object_vars($this);
    }

    public function getPayload()
    {
        return [
            "className" => $this->className,
            "data" => $this->data
        ];
    }

    public function invitationMarkStatus(int $status) 
    {
        $invitation = Repo::invitation()
            ->getByKeyHash($this->keyHash);

        if (is_null($invitation)) {
            throw new \Exception('This invitation was not found'); // Should create a custom exception and localise the message?
        }

        switch ($status) {
            case InvitationStatus::INVITATION_STATUS_ACCEPTED:
                $invitation->markInvitationAsAccepted();
                break;
            case InvitationStatus::INVITATION_STATUS_DECLINED:
                $invitation->markInvitationAsDeclined();
                break;
            case InvitationStatus::INVITATION_STATUS_EXPIRED:
                $invitation->markInvitationAsExpired();
                break;
            case InvitationStatus::INVITATION_STATUS_CANCELLED:
                $invitation->markInvitationAsCanceled();
                break;
            default:
                echo "Unknown invitation type.";
                break;
        }
    }

    public function invitationAcceptHandle() : bool
    {
        $this->invitationMarkStatus(InvitationStatus::INVITATION_STATUS_ACCEPTED);

        return true;
    }
    public function invitationDeclineHandle() : bool
    {
        $this->invitationMarkStatus(InvitationStatus::INVITATION_STATUS_DECLINED);

        return true;
    }

    abstract public function getInvitationMailable() : Mailable;
    abstract public function preDispatchActions() : bool;

    public function getAcceptInvitationURL() : string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                PKPInvitationHandler::INVITATION_REPLY_BASE,
                PKPInvitationHandler::INVITATION_REPLY_ACCEPT,
                null,
                [
                    'key' => $this->keyHash,
                ]
            );
    }
    public function getDeclineInvitationURL() : string
    {
        $request = Application::get()->getRequest();
        return $request->getDispatcher()
            ->url(
                $request,
                Application::ROUTE_PAGE,
                $request->getContext()->getPath(),
                PKPInvitationHandler::INVITATION_REPLY_BASE,
                PKPInvitationHandler::INVITATION_REPLY_DECLINE,
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
            'context' => InvitationType::INVITATION_CONTEXT,
            'key_hash' => $this->keyHash,
            'user_id' => $user->getId(),
            'assoc_id' => $this->assocId,
            'expiry_date' => $this->expirationDate->getTimestamp(),
            'payload' => $this->getPayload(),
            'created_at' => Carbon::now()->timestamp,
            'updated_at' => Carbon::now()->timestamp,
            'status' => InvitationStatus::INVITATION_STATUS_PENDING,
            'type' => $this->type,
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
        $this->keyHash = $invitationModel->key_hash;
    }
}

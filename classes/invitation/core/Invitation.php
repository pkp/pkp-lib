<?php

/**
 * @file classes/invitation/core/Invitation.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Invitation
 *
 * @brief Abstract class for all Invitations
 */

namespace PKP\invitation\core;

use APP\core\Application;
use APP\facades\Repo;
use Carbon\Carbon;
use Exception;
use Identity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\context\Context;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\traits\HasMailable;
use PKP\invitation\core\traits\ShouldValidate;
use PKP\invitation\models\InvitationModel;
use PKP\pages\invitation\InvitationHandler;
use PKP\security\Validation;
use PKP\user\User;
use Symfony\Component\Mailer\Exception\TransportException;

abstract class Invitation
{
    public const VALIDATION_RULE_GENERIC = 'generic_validation_rule';

    public const DEFAULT_EXPIRY_DAYS = 3;

    private ?string $key = null;

    public InvitationModel $invitationModel;

    protected InvitePayload $payload;

    /**
     * The properties of the invitation that are added here should not change
     * at the "Create" stage of the invitation
     */
    protected array $notAccessibleBeforeInvite = [];

     /**
     * The properties of the invitation that are added here should not change
     * at the "Receive" stage of the invitation
     */
    protected array $notAccessibleAfterInvite = [];

    /**
     * Only the properties of the invitation that are added here will be considered
     * for the payload
     */
    protected array $payloadAccessibleProperties = [];

    abstract public static function getType(): string;

    /**
     * Defines the controller that is responsible for the handle of the accept/decline
     * urls send to the invitee's email
     */
    abstract public function getInvitationActionRedirectController(): ?InvitationActionRedirectController;

    /**
     * Get a specific payload instance for the child class.
     */
    abstract protected function getPayloadClass(): string;

    /**
     * Get the specific payload instance for the child class.
     */
    public function getPayload(): InvitePayload
    {
        if (!isset($this->payload)) {
            $payloadClass = $this->getPayloadClass(); // Get the class from child
            return new $payloadClass();
        }

        return $this->payload;
    }

    /**
     * This is used during every populate call so that the code can use the currenlty processing properties.
     */
    protected array $currentlyFilledFromArgs = [];

    public function __construct(?InvitationModel $invitationModel = null)
    {
        $this->invitationModel = $invitationModel ?: new InvitationModel([
            'type' => $this->getType()
        ]);

        $this->fillFromPayload();
    }

    /**
     * Function that adds the invitation to the database, initialising its main attributes.
     * Either userId or email is taken into account - if both are defined, the userId is passed into the invitation.
     * It removes all other InvitationStatus::INITIALIZED invitation from the database, that correspont to
     * the same main attributes.
     */
    public function initialize(?int $userId = null, ?int $contextId = null, ?string $email = null, ?int $inviterId = null): void
    {
        if (!isset($userId) && !isset($email)) {
            throw new Exception("Invitation should contain the user id or an invited email.')");
        }

        if (isset($userId)) {
            unset($email);
        }

        InvitationModel::byStatus(InvitationStatus::INITIALIZED)
            ->when($userId !== null, fn (Builder $q) => $q->byUserId($userId))
            ->when($contextId !== null, fn (Builder $q) => $q->byContextId($contextId))
            ->when($email !== null, fn (Builder $q) => $q->byEmail($email))
            ->byType($this->getType())
            ->delete();

        $this->invitationModel->userId = $userId;
        $this->invitationModel->contextId = $contextId;
        $this->invitationModel->email = $email;
        $this->invitationModel->inviterId = $inviterId;

        $this->invitationModel->status = InvitationStatus::INITIALIZED;

        $this->invitationModel->payload = $this->payload;

        $this->invitationModel->save();
    }

    /**
     * Used to fill the invitation's properties from the model's payload values.
     */
    protected function fillFromPayload(): void
    {
        $payloadClass = $this->getPayload();
        $this->payload = $payloadClass;

        if ($this->invitationModel->payload) {
            $this->payload = $payloadClass::fromArray(
                $this->invitationModel->payload
            );
        }
    }

    /**
     * Validates the incoming data given the validation context if necessary,
     * and fills the invitation payload with the given data.
     */
    public function fillFromData(array $data): bool
    {
        // Determine the properties that are not allowed to be changed based on the current status
        $checkArray = [];
        if ($this->getStatus() == InvitationStatus::INITIALIZED) {
            $checkArray = $this->getNotAccessibleBeforeInvite();
        } elseif ($this->getStatus() == InvitationStatus::PENDING) {
            $checkArray = $this->getNotAccessibleAfterInvite();
        } else {
            throw new Exception('You cannot modify the Invitation in this stage.');
        }

        // Filter out the properties that are not allowed to change
        $filteredArgs = array_diff_key($data, array_flip($checkArray));

        // Convert the existing payload to an array
        $existingData = $this->payload->toArray();

        // Merge existing payload data with the filtered arguments
        $mergedData = array_merge($existingData, $filteredArgs);

        // Update the payload with the filtered arguments using fromArray
        $payloadClass = $this->getPayload();
        $this->payload = $payloadClass::fromArray($mergedData);

        // Track which properties have been updated
        $this->currentlyFilledFromArgs = array_keys($filteredArgs);

        return true;
    }

    /**
     * Saves the payload to the database, after it passes a sanity check
     * Returns: True : if database update SUCCEEDED or if there is nothing to update
     *          False: if database update FAILED
     *          null : if invitation validation failed for properties
     */
    public function updatePayload(?ValidationContext $validationContext = null): ?bool
    {
        // Convert the current payload object to an array
        $currentPayloadArray = $this->payload->toArray();

        // Get the existing payload from the database
        $existingPayloadArray = $this->invitationModel->payload ?? [];

        // Compare the current payload with the existing one
        $changedData = $this->array_diff_assoc_recursive($currentPayloadArray, $existingPayloadArray);

        // If no changes are detected, return true (no need to update)
        if (empty($changedData)) {
            return true;
        }

        // Determine which properties are not allowed to be changed based on the current status
        $checkArray = [];
        if ($this->getStatus() == InvitationStatus::INITIALIZED) {
            $checkArray = $this->getNotAccessibleBeforeInvite();
        } elseif ($this->getStatus() == InvitationStatus::PENDING) {
            $checkArray = $this->getNotAccessibleAfterInvite();
        } else {
            throw new Exception('You cannot modify the Invitation in this stage');
        }

        // Filter out the changes that are not allowed based on the current status
        $invalidChanges = array_intersect_key($changedData, array_flip($checkArray));

        if (!empty($invalidChanges)) {
            // Throw an exception or handle the error if there are invalid changes
            throw new Exception('The following properties cannot be modified at this stage: ' . implode(', ', array_keys($invalidChanges)));
        }

        // Validate only the changed data if the ShouldValidate trait is used
        if (in_array(ShouldValidate::class, class_uses($this)) && isset($validationContext)) {
            if (!$this->validate($changedData, $validationContext)) {
                return null; // Validation failed
            }
        }

        // Update the payload attribute on the invitation model
        $this->invitationModel->setAttribute('payload', $currentPayloadArray);

        // Save the updated invitation model to the database
        return $this->invitationModel->save();
    }

    public function getNotAccessibleBeforeInvite(): array
    {
        return $this->notAccessibleBeforeInvite;
    }

    public function getNotAccessibleAfterInvite(): array
    {
        return $this->notAccessibleAfterInvite;
    }

    public function getPayloadAccessibleProperties(): array
    {
        return $this->payloadAccessibleProperties;
    }

    /**
     * The invitation does not have a key before the "invite" action is called.
     * This function checks if the key_hash is present and creates it if it is not
     */
    protected function checkForKey(): void
    {
        if (!isset($this->invitationModel->keyHash)) {
            if (!isset($this->key)) {
                $this->key = Validation::generatePassword();
            }

            $this->invitationModel->keyHash = self::makeKeyHash($this->key);
        }
    }

    /**
     * The invitation does not have an expiryDate before the "invite" action is called.
     * This function sets the expiry date of the invitation.
     * This cannot change after the invitation is dispatched.
     */
    public function setExpiryDate(Carbon $expiryDate): void
    {
        if ($this->getStatus() !== InvitationStatus::INITIALIZED) {
            throw new Exception('Can not change expiry date at this stage');
        }

        $this->invitationModel->expiryDate = $expiryDate;
    }

    /**
     * Call this to trigger the "invite" action of the invitation, which also dispatces it.
     */
    public function invite(): bool
    {
        if ($this->getStatus() !== InvitationStatus::INITIALIZED) {
            throw new Exception('The invitation can not be dispatched');
        }

        if (in_array(ShouldValidate::class, class_uses($this))) {
            if (!$this->validate([], ValidationContext::VALIDATION_CONTEXT_INVITE)) {
                return false;
            }
        }

        $this->checkForKey();

        $this->setExpiryDate(Carbon::now()->addDays($this->getExpiryDays()));

        if (in_array(HasMailable::class, class_uses($this))) {
            $mailable = $this->getMailable();

            if (isset($mailable)) {
                try {
                    Mail::send($mailable);
                } catch (Exception $e) {
                    throw $e;
                }
            }
        }

        $this->invitationModel->status = InvitationStatus::PENDING;

        $this->invitationModel->save();

        InvitationModel::byStatus(InvitationStatus::PENDING)
            ->byType($this->getType())
            ->byNotId($this->getId())
            ->when(isset($this->invitationModel->userId), fn (Builder $q) => $q->byUserId($this->invitationModel->userId))
            ->when(!isset($this->invitationModel->userId) && $this->invitationModel->email, fn (Builder $q) => $q->byEmail($this->invitationModel->email))
            ->when(isset($this->invitationModel->contextId), fn (Builder $q) => $q->byContextId($this->invitationModel->contextId))
            ->delete();

        return true;
    }

    public function getInviter(): ?User
    {
        if (!isset($this->invitationModel->inviterId)) {
            return null;
        }

        return Repo::user()->get($this->invitationModel->inviterId);
    }

    public function getExistingUser(): ?User
    {
        if (!isset($this->invitationModel->userId)) {
            return null;
        }

        return Repo::user()->get($this->invitationModel->userId);
    }

    public function getContext(): ?Context
    {
        if (!isset($this->invitationModel->contextId)) {
            return null;
        }

        $contextDao = Application::getContextDAO();
        return $contextDao->getById($this->invitationModel->contextId);
    }

    public function getMailableReceiver(?string $locale = null): Identity
    {
        $locale = $this->getUsedLocale($locale);

        $sendIdentity = new Identity();
        $user = null;
        if ($this->invitationModel->userId) {
            $user = Repo::user()->get($this->invitationModel->userId);
            
            $sendIdentity->setFamilyName($user->getFamilyName($locale), $locale);
            $sendIdentity->setGivenName($user->getGivenName($locale), $locale);
            $sendIdentity->setEmail($user->getEmail());
        } else {
            $sendIdentity->setEmail($this->invitationModel->email);
        }

        return $sendIdentity;
    }

    public function getUsedLocale(?string $locale = null): string
    {
        if (isset($locale)) {
            return $locale;
        }

        if (isset($this->invitationModel->contextId)) {
            $contextDao = Application::getContextDAO();
            $context = $contextDao->getById($this->invitationModel->contextId);
            return $context->getPrimaryLocale();
        }

        $request = Application::get()->getRequest();
        $site = $request->getSite();
        return $site->getPrimaryLocale();
    }

    private static function makeKeyHash($key): string
    {
        return password_hash($key, PASSWORD_BCRYPT);
    }

    public function getId(): ?int
    {
        if (isset($this->invitationModel)) {
            return $this->invitationModel->id;
        }

        return null;
    }

    public function getStatus(): ?InvitationStatus
    {
        if (isset($this->invitationModel)) {
            return $this->invitationModel->status;
        }

        return null;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getActionURL(InvitationAction $invitationAction): ?string
    {
        return InvitationHandler::getActionUrl($invitationAction, $this);
    }

    public function decline(): void
    {
        $this->invitationModel->markAs(InvitationStatus::DECLINED);
    }

    protected function getExpiryDays(): int
    {
        return (int) Config::getVar('invitations', 'expiration_days', self::DEFAULT_EXPIRY_DAYS);
    }

    public function getUserId(): ?int
    {
        return $this->invitationModel->userId;
    }

    public function getContextId(): ?int
    {
        return $this->invitationModel->contextId;
    }

    public function getEmail(): ?string
    {
        return $this->invitationModel->email;
    }

    protected function array_diff_assoc_recursive($array1, $array2) 
    {
        $difference = [];

        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    // If $array2 doesn't have the key or the corresponding value isn't an array
                    $difference[$key] = $value;
                } else {
                    // Recursively call the function
                    $new_diff = $this->array_diff_assoc_recursive($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                // If $array2 doesn't have the key or the values don't match
                $difference[$key] = $value;
            }
        }

        return $difference;
    }

    public function updateStatus(InvitationStatus $status): void
    {
        $this->invitationModel->status = $status;
        $this->invitationModel->save();
    }

    public function isPending(): bool
    {
        if (
            $this->getStatus() == InvitationStatus::INITIALIZED ||
            $this->getStatus() == InvitationStatus::PENDING
        ) {
            return true;
        }

        return false;
    }
}

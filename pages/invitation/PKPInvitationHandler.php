<?php

/**
 * @file pages/invitation/PKPInvitationHandler.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\components\forms\submission\ReconfigureSubmission;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\invitation\invitations\BaseInvitation;
use ReflectionClass;

class PKPInvitationHandler extends Handler
{
    public const REPLY_PAGE = 'invitation';
    public const REPLY_OP_ACCEPT = 'accept';
    public const REPLY_OP_DECLINE = 'decline';

    /**
     * Accept invitation handler
     */
    public function accept($args, $request): void
    {
        $invitation = $this->getInvitationByKey($request);

        $invitation->invitationAcceptHandle();
    }

    /**
     * Decline invitation handler
     */
    public function decline(array $args, Request $request): void
    {
        $invitation = $this->getInvitationByKey($request);
        $invitation->invitationDeclineHandle();
    }

    private function getInvitationByKey(Request $request) : BaseInvitation
    {
        $key = $request->getUserVar('key')
            ? $request->getUserVar('key')
            : null;

        $invitation = $this->getInvitation($key);

        if (is_null($invitation)) {
            $request->getDispatcher()->handle404();
        }

        return $invitation;
    }

    private function getInvitation(string $key) : ?BaseInvitation
    {
        $hashKey = md5($key);
        $invitation = Repo::invitation()
            ->getByKeyHash($hashKey);

        if (is_null($invitation)) {
            return null;
        }

        if ($invitation->isExpired()) {
            $invitation->markInvitationAsExpired();
            return null;
        }
        
        $className = $invitation->type;
        $data = $invitation->payload;

        if (!class_exists($className)) {
            return null; // Class does not exist
        }

        $reflectionClass = new ReflectionClass($className);

        // Get the constructor parameters
        $constructor = $reflectionClass->getConstructor();
        $constructorParameters = $constructor ? $constructor->getParameters() : [];

        $arguments = [];
        foreach ($constructorParameters as $parameter) {
            $parameterName = $parameter->getName();
            if (array_key_exists($parameterName, $data)) {
                $arguments[] = $data[$parameterName];
            } else {
                if ($parameter->isDefaultValueAvailable()) {
                    $arguments[] = $parameter->getDefaultValue();
                } else {
                    // Unable to reconstruct object if required parameter value is missing
                    return null;
                }
                
            }
        }

        $retInvitation = $reflectionClass->newInstanceArgs($arguments);

        $retInvitation->setInvitationModel($invitation);

        return $retInvitation;
    }
}

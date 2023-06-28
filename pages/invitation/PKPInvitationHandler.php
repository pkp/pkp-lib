<?php

/**
 * @file pages/submission/PKPSubmissionHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionHandler
 *
 * @ingroup pages_submission
 *
 * @brief Handles page requests to the submission wizard
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
    public const INVITATION_REPLY_BASE = 'invitation';
    public const INVITATION_REPLY_ACCEPT = 'accept';
    public const INVITATION_REPLY_DECLINE = 'decline';

    /**
     * Route the request to the correct page based
     * on whether they are starting a new submission,
     * working on a submission in progress, or viewing
     * a submission that has been submitted.
     *
     * @param array $args
     * @param Request $request
     */
    public function accept($args, $request): void
    {
        $invitation = $this->getInvitationByKey($request);

        // maybe could return possible errors here
        $invitation->invitationAcceptHandle();
    }

    /**
     * Display the screen to start a new submission
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
        $invitation = Repo::invitation()
            ->getByKeyHash($key);

        if (is_null($invitation)) {
            return null;
        }

        if ($invitation->isExpired()) {
            $invitation->markInvitationAsExpired();
            return null;
        }
        
        $className = $invitation->getHandlerClassNameAttribute();
        $data = $invitation->getHandlerDataAttribute();

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
                // Unable to reconstruct object if required parameter value is missing
                return null;
            }
        }

        $retInvitation = $reflectionClass->newInstanceArgs($arguments);

        $retInvitation->setInvitationModel($invitation);

        return $retInvitation;
    }
}

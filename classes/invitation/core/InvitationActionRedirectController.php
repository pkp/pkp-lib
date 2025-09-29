<?php

/**
 * @file classes/invitation/core/InvitationActionRedirectController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationActionRedirectController
 *
 * @brief Declares the accept/decline url handlers.
 */

namespace PKP\invitation\core;

use APP\core\Request;
use APP\template\TemplateManager;
use Illuminate\Routing\Controller;
use PKP\core\PKPApplication;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\enums\InvitationStatus;

/**
 * @template TInvitation of Invitation
 */
abstract class InvitationActionRedirectController extends Controller
{
    /** @var TInvitation */
    protected Invitation $invitation;

    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
    }

    /**
     * Redirect to accept invitation page
     *
     * @throws \Exception
     */
    public function declineHandle(Request $request): void
    {
        if ($this->invitation->getStatus() !== InvitationStatus::PENDING) {
            throw new \Symfony\Component\HttpKernel\Exception\GoneHttpException();
        }

        $context = $request->getContext();

        $templateMgr = TemplateManager::getManager($request);
        $declineUrl = PKPApplication::get()->getDispatcher()->url(
            PKPApplication::get()->getRequest(),
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'invitation',
            'confirmDecline',
            null,
            [
                'id' => $request->getUserVar('id'),
                'key' => $request->getUserVar('key'),
            ]
        );

        $templateMgr->assign([
            'declineUrl' => $declineUrl,
            'pageComponent' => 'Page',
        ]);

        $templateMgr->display('invitation/declineInvitation.tpl');
    }

    abstract public function preRedirectActions(InvitationAction $action): void;

    abstract public function acceptHandle(Request $request): void;
    abstract public function confirmDecline(Request $request): void;
}

<?php

/**
 * @file pages/user/ProfileHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProfileHandler
 *
 * @ingroup pages_user
 *
 * @brief Handle requests for modifying user profiles.
 */

namespace PKP\pages\user;

use APP\core\Application;
use APP\pages\user\UserHandler;
use APP\template\TemplateManager;
use PKP\core\PKPRequest;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\userGroup\relationships\UserUserGroup;

class ProfileHandler extends UserHandler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $operations = [
            'profile',
        ];

        // Site access policy
        $this->addPolicy(new PKPSiteAccessPolicy($request, $operations, PKPSiteAccessPolicy::SITE_ACCESS_ALL_ROLES));

        // User must be logged in
        $this->addPolicy(new UserRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display user profile tabset.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function profile($args, $request)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        $date_start = null;
        if (!$context) {
            $contextDao = Application::getContextDAO();
            $workingContexts = $contextDao->getAvailable($user ? $user->getId() : null);
            [$firstContext, $secondContext] = [$workingContexts->next(), $workingContexts->next()];
            if ($firstContext && !$secondContext) {
                $request->redirect($firstContext->getPath(), 'user', 'profile', null, $args);
            }
        }

        if ($anchor = array_shift($args)) {
            // Some requests will try to specify a tab name in the args. Redirect
            // to use this as an anchor name instead.
            $request->redirect(null, null, null, null, null, $anchor);
        }

        if(count($user->getRoles($context->getId())) === 0){
           $userFutureRoleStartDate = UserUserGroup::withUserId($user->getId())
               ->withActiveInFuture()
               ->pluck('date_start')
               ->first();
            $date_start = new \DateTime($userFutureRoleStartDate);
        }

        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('user.profile'),
            'userFutureRoleStartDate'=> $date_start?->format('Y-m-d'),
        ]);
        $templateMgr->display('user/profile.tpl');
    }
}

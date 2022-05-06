<?php

/**
 * @defgroup controllers_tab_user
 */

/**
 * @file controllers/tab/user/ProfileTabHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProfileTabHandler
 * @ingroup controllers_tab_user
 *
 * @brief Handle requests for profile tab operations.
 */

use APP\handler\Handler;
use APP\notification\form\NotificationSettingsForm;
use APP\notification\NotificationManager;

use PKP\core\JSONMessage;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\user\form\APIProfileForm;
use PKP\user\form\ChangePasswordForm;
use PKP\user\form\ContactForm;
use PKP\user\form\IdentityForm;
use PKP\user\form\PublicProfileForm;
use PKP\user\form\RolesForm;
use PKP\session\SessionManager;

class ProfileTabHandler extends Handler
{
    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        // User must be logged in
        $this->addPolicy(new UserRequiredPolicy($request));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display form to edit user's identity.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function identity($args, $request)
    {
        $this->setupTemplate($request);
        $identityForm = new IdentityForm($request->getUser());
        $identityForm->initData();
        return new JSONMessage(true, $identityForm->fetch($request));
    }

    /**
     * Validate and save changes to user's identity info.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function saveIdentity($args, $request)
    {
        $this->setupTemplate($request);

        $identityForm = new IdentityForm($request->getUser());
        $identityForm->readInputData();
        if ($identityForm->validate()) {
            $identityForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true);
        }
        return new JSONMessage(true, $identityForm->fetch($request));
    }

    /**
     * Display form to edit user's contact information.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function contact($args, $request)
    {
        $this->setupTemplate($request);
        $contactForm = new ContactForm($request->getUser());
        $contactForm->initData();
        return new JSONMessage(true, $contactForm->fetch($request));
    }

    /**
     * Validate and save changes to user's contact info.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function saveContact($args, $request)
    {
        $this->setupTemplate($request);

        $contactForm = new ContactForm($request->getUser());
        $contactForm->readInputData();
        if ($contactForm->validate()) {
            $contactForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true);
        }
        return new JSONMessage(true, $contactForm->fetch($request));
    }

    /**
     * Display form to edit user's roles information.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function roles($args, $request)
    {
        $this->setupTemplate($request);
        $rolesForm = new RolesForm($request->getUser());
        $rolesForm->initData();
        return new JSONMessage(true, $rolesForm->fetch($request));
    }

    /**
     * Validate and save changes to user's roles info.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function saveRoles($args, $request)
    {
        $this->setupTemplate($request);

        $rolesForm = new RolesForm($request->getUser());
        $rolesForm->readInputData();
        if ($rolesForm->validate()) {
            $rolesForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true);
        }
        return new JSONMessage(false, $rolesForm->fetch($request));
    }

    /**
     * Display form to edit user's publicProfile information.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function publicProfile($args, $request)
    {
        $this->setupTemplate($request);
        $publicProfileForm = new PublicProfileForm($request->getUser());
        $publicProfileForm->initData();
        return new JSONMessage(true, $publicProfileForm->fetch($request));
    }

    /**
     * Upload a public profile image.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function uploadProfileImage($args, $request)
    {
        $publicProfileForm = new PublicProfileForm($request->getUser());
        $result = $publicProfileForm->uploadProfileImage();
        if ($result) {
            return $request->redirectUrlJson($request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'user', 'profile', null, ['uniq' => uniqid()], 'publicProfile'));
        } else {
            return new JSONMessage(false, __('common.uploadFailed'));
        }
    }

    /**
     * Remove a public profile image.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function deleteProfileImage($args, $request)
    {
        $publicProfileForm = new PublicProfileForm($request->getUser());
        $publicProfileForm->deleteProfileImage();
        $request->redirectUrl($request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'user', 'profile', null, ['uniq' => uniqid()], 'publicProfile'));
    }

    /**
     * Validate and save changes to user's publicProfile info.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function savePublicProfile($args, $request)
    {
        $this->setupTemplate($request);

        $publicProfileForm = new PublicProfileForm($request->getUser());
        $publicProfileForm->readInputData();
        if ($publicProfileForm->validate()) {
            $publicProfileForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true);
        }
        return new JSONMessage(true, $publicProfileForm->fetch($request));
    }

    /**
     * Display form to edit user's API key settings.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function apiProfile($args, $request)
    {
        $this->setupTemplate($request);
        $apiProfileForm = new APIProfileForm($request->getUser());
        $apiProfileForm->initData();
        return new JSONMessage(true, $apiProfileForm->fetch($request));
    }

    /**
     * Validate and save changes to user's API key settings.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function saveAPIProfile($args, $request)
    {
        $this->setupTemplate($request);

        $apiProfileForm = new APIProfileForm($request->getUser());
        $apiProfileForm->readInputData();
        if ($apiProfileForm->validate()) {
            $apiProfileForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
            return new JSONMessage(true, $apiProfileForm->fetch($request));
        }
        return new JSONMessage(true, $apiProfileForm->fetch($request));
    }

    /**
     * Display form to change user's password.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function changePassword($args, $request)
    {
        $this->setupTemplate($request);
        $passwordForm = new ChangePasswordForm($request->getUser(), $request->getSite());
        $passwordForm->initData();
        return new JSONMessage(true, $passwordForm->fetch($request));
    }

    /**
     * Save user's new password.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function savePassword($args, $request)
    {
        $this->setupTemplate($request);

        $user = $request->getUser();

        $passwordForm = new ChangePasswordForm($user, $request->getSite());
        $passwordForm->readInputData();

        if ($passwordForm->validate()) {

            $passwordForm->execute();

            $sessionManager = SessionManager::getManager();
            $sessionManager->invalidateSessions($user->getId(), $sessionManager->getUserSession()->getId());

            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($user->getId());
            
            return new JSONMessage(true);
        }
		
        return new JSONMessage(true, $passwordForm->fetch($request));
    }

    /**
     * Fetch notifications tab content.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function notificationSettings($args, $request)
    {
        $this->setupTemplate($request);

        $user = $request->getUser();
        $notificationSettingsForm = new NotificationSettingsForm();
        return new JSONMessage(true, $notificationSettingsForm->fetch($request));
    }

    /**
     * Save user notification settings.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON-formatted response
     */
    public function saveNotificationSettings($args, $request)
    {
        $this->setupTemplate($request);

        $notificationSettingsForm = new NotificationSettingsForm();
        $notificationSettingsForm->readInputData();

        $json = new JSONMessage();
        if ($notificationSettingsForm->validate()) {
            $notificationSettingsForm->execute();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId());
        } else {
            $json->setStatus(false);
        }

        return $json;
    }
}

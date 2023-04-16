<?php

/**
 * @file classes/user/form/APIProfileForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIProfileForm
 *
 * @ingroup user_form
 *
 * @brief Form to edit user's API key settings.
 */

namespace PKP\user\form;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Firebase\JWT\JWT;
use PKP\config\Config;
use PKP\notification\PKPNotification;
use PKP\user\User;

class APIProfileForm extends BaseProfileForm
{
    public const API_KEY_NEW = 1;
    public const API_KEY_DELETE = 0;

    /**
     * Constructor.
     *
     * @param User $user
     */
    public function __construct($user)
    {
        parent::__construct('user/apiProfileForm.tpl', $user);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $user = $this->getUser();
        $this->setData('apiKeyEnabled', (bool) $user->getData('apiKeyEnabled'));
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        parent::readInputData();

        $this->readUserVars([
            'apiKeyEnabled',
            'generateApiKey',
            'apiKeyAction',
        ]);
    }

    /**
     * Fetch the form to edit user's API key settings.
     *
     * @see BaseProfileForm::fetch
     *
     * @param null|mixed $template
     *
     * @return string JSON-encoded form contents.
     */
    public function fetch($request, $template = null, $display = false)
    {
        $user = $request->getUser();
        $secret = Config::getVar('security', 'api_key_secret', '');
        $templateMgr = TemplateManager::getManager($request);

        if ($secret === '') {
            $this->handleOnMissingAPISecret($templateMgr, $user);
            return parent::fetch($request, $template, $display);
        }

        $templateMgr->assign(
            $user->getData('apiKey') ? [
                'apiKey' => JWT::encode($user->getData('apiKey'), $secret, 'HS256'),
                'apiKeyAction' => self::API_KEY_DELETE,
                'apiKeyActionTextKey' => 'user.apiKey.remove',
            ] : [
                'apiKeyAction' => self::API_KEY_NEW,
                'apiKeyActionTextKey' => 'user.apiKey.generate',
            ]
        );

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $templateMgr = TemplateManager::getManager($request);

        if (Config::getVar('security', 'api_key_secret', '') === '') {
            $this->handleOnMissingAPISecret($templateMgr, $user);
            parent::execute(...$functionArgs);
        }

        $apiKeyAction = (int)$this->getData('apiKeyAction');

        $user->setData('apiKeyEnabled', $apiKeyAction === self::API_KEY_NEW ? 1 : null);
        $user->setData('apiKey', $apiKeyAction === self::API_KEY_NEW ? sha1(time()) : null);

        $this->setData('apiKeyAction', (int)!$apiKeyAction);

        parent::execute(...$functionArgs);
    }

    /**
     * Handle on missing API secret
     *
     *
     */
    protected function handleOnMissingAPISecret(TemplateManager $templateMgr, User $user): void
    {
        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            PKPNotification::NOTIFICATION_TYPE_WARNING,
            [
                'contents' => __('user.apiKey.secretRequired'),
            ]
        );
        $templateMgr->assign([
            'apiSecretMissing' => true,
        ]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\APIProfileForm', '\APIProfileForm');
}

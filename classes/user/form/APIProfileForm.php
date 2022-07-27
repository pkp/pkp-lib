<?php

/**
 * @file classes/user/form/APIProfileForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class APIProfileForm
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

class APIProfileForm extends BaseProfileForm
{
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
            'apiKeyEnabled', 'generateApiKey',
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
        if ($secret === '') {
            $notificationManager = new NotificationManager();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                PKPNotification::NOTIFICATION_TYPE_WARNING,
                [
                    'contents' => __('user.apiKey.secretRequired'),
                ]
            );
        } elseif ($user->getData('apiKey')) {
            $templateMgr = TemplateManager::getManager($request);
            $templateMgr->assign([
                'apiKey' => JWT::encode($user->getData('apiKey'), $secret, 'HS256'),
            ]);
        }
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $apiKeyEnabled = (bool) $this->getData('apiKeyEnabled');
        $user->setData('apiKeyEnabled', $apiKeyEnabled);

        // remove api key if exists
        if (!$apiKeyEnabled) {
            $user->setData('apiKeyEnabled', null);
        }

        // generate api key
        if ($apiKeyEnabled && !is_null($this->getData('generateApiKey'))) {
            $secret = Config::getVar('security', 'api_key_secret', '');
            if ($secret) {
                $user->setData('apiKey', sha1(time()));
            }
        }

        parent::execute(...$functionArgs);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\user\form\APIProfileForm', '\APIProfileForm');
}

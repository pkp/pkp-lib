<?php

/**
 * @file controllers/grid/settings/languages/ManageLanguageGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageLanguageGridHandler
 *
 * @ingroup controllers_grid_settings_languages
 *
 * @brief Handle language management grid requests only.
 */

namespace PKP\controllers\grid\settings\languages;

use APP\core\Services;
use APP\notification\NotificationManager;
use PKP\controllers\grid\languages\LanguageGridHandler;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\notification\PKPNotification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class ManageLanguageGridHandler extends LanguageGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['saveLanguageSetting', 'setContextPrimaryLocale', 'reloadLocale', 'fetchGrid', 'fetchRow']
        );
    }


    //
    // Implement methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $site = $request->getSite();
        $context = $request->getContext();
        $allLocales = Locale::getLocales();

        $supportedLocales = $site->getSupportedLocales();
        $contextPrimaryLocale = $context->getPrimaryLocale();
        $data = [];

        foreach ($supportedLocales as $locale) {
            $formattedLocale = Locale::getFormattedDisplayNames([$locale], $allLocales);
            $data[$locale] = [];
            $data[$locale]['code'] = $locale;
            $data[$locale]['name'] = array_shift($formattedLocale);
            $data[$locale]['supported'] = true;
            $data[$locale]['primary'] = ($locale == $contextPrimaryLocale);
        }

        $data = $this->addManagementData($request, $data);
        return $data;
    }

    //
    // Extended methods from LanguageGridHandler.
    //
    /**
     * @copydoc LanguageGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        $this->addNameColumn();
        $this->addLocaleCodeColumn();
        $this->addPrimaryColumn('contextPrimary');
        $this->addManagementColumns();
    }

    /**
     * Reload locale.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function reloadLocale($args, $request)
    {
        $context = $request->getContext();
        $locale = $request->getUserVar('rowId');
        $gridData = $this->getGridDataElements($request);

        if (empty($context) || !$request->checkCSRF() || !array_key_exists($locale, $gridData)) {
            return new JSONMessage(false);
        }

        $context = Services::get('context')->restoreLocaleDefaults($context, $request, $locale);

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $request->getUser()->getId(),
            PKPNotification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('notification.localeReloaded', ['locale' => $gridData[$locale]['name'], 'contextName' => $context->getLocalizedName()])]
        );

        return \PKP\db\DAO::getDataChangedEvent($locale);
    }
}

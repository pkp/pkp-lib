<?php

/**
 * @file controllers/grid/admin/languages/AdminLanguageGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdminLanguageGridHandler
 *
 * @ingroup controllers_grid_admin_languages
 *
 * @brief Handle administrative language grid requests. If in single context (e.g.
 * press) installation, this grid can also handle language management requests.
 * See _canManage().
 */

namespace PKP\controllers\grid\admin\languages;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\languages\form\InstallLanguageForm;
use PKP\controllers\grid\languages\LanguageGridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\Notification;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\site\Site;
use PKP\site\SiteDAO;

class AdminLanguageGridHandler extends LanguageGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN],
            [
                'fetchGrid', 'fetchRow',
                'installLocale', 'saveInstallLocale', 'uninstallLocale',
                'disableLocale', 'enableLocale', 'setPrimaryLocale'
            ]
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc GridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc LanguageGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Grid actions.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'installLocale',
                new AjaxModal(
                    $router->url($request, null, null, 'installLocale', null, null),
                    __('admin.languages.installLocale'),
                    null,
                    true
                ),
                __('admin.languages.installLocale'),
                'add'
            )
        );

        // Columns.
        // Enable locale.
        $this->addColumn(
            new GridColumn(
                'enable',
                'common.enable',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $this->getCellProvider(),
                ['width' => 10]
            )
        );

        $this->addNameColumn(); // Locale name.
        $this->addLocaleCodeColumn(); // Locale code.

        // Primary locale.
        if ($this->_canManage($request)) {
            $primaryId = 'contextPrimary';
        } else {
            $primaryId = 'sitePrimary';
        }
        $this->addPrimaryColumn($primaryId);

        if ($this->_canManage($request)) {
            $this->addManagementColumns();
        }

        $this->setFootNote('admin.locale.maybeIncomplete');
    }


    //
    // Implement methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $site = $request->getSite(); /** @var Site $site */
        $data = [];

        $installedLocales = $site->getInstalledLocaleNames();
        $supportedLocales = $site->getSupportedLocales();
        $primaryLocale = $site->getPrimaryLocale();

        foreach ($installedLocales as $localeKey => $localeName) {
            $data[$localeKey] = [];
            $data[$localeKey]['code'] = $localeKey;
            $data[$localeKey]['name'] = $localeName;
            $data[$localeKey]['incomplete'] = !Locale::getMetadata($localeKey)->isComplete();
            $data[$localeKey]['supported'] = in_array($localeKey, $supportedLocales);

            if ($this->_canManage($request)) {
                $context = $request->getContext();
                $primaryLocale = $context->getPrimaryLocale();
            }

            $data[$localeKey]['primary'] = $localeKey === $primaryLocale;
        }

        if ($this->_canManage($request)) {
            $data = $this->addLocaleSettingData($request, $data);
        }

        return $data;
    }


    //
    // Public grid actions.
    //
    /**
     * Open a form to select locales for installation.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function installLocale($args, $request)
    {
        // Form handling.
        $installLanguageForm = new InstallLanguageForm();
        $installLanguageForm->initData();
        return new JSONMessage(true, $installLanguageForm->fetch($request));
    }

    /**
     * Save the install language form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function saveInstallLocale($args, $request)
    {
        $installLanguageForm = new InstallLanguageForm();
        $installLanguageForm->readInputData();

        if ($installLanguageForm->validate()) {
            $installLanguageForm->execute();
            $this->_updateContextLocaleSettings($request);

            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.localeInstalled')]
            );
        }
        return \PKP\db\DAO::getDataChangedEvent();
    }

    /**
     * Uninstall a locale.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function uninstallLocale($args, $request)
    {
        $site = $request->getSite();
        $locale = $request->getUserVar('rowId');
        $gridData = $this->getGridDataElements($request);

        if ($request->checkCSRF() && array_key_exists($locale, $gridData)) {
            $localeData = $gridData[$locale];
            if ($localeData['primary']) {
                return new JSONMessage(false);
            }

            $installedLocales = $site->getInstalledLocales();
            if (in_array($locale, $installedLocales)) {
                $installedLocales = array_diff($installedLocales, [$locale]);
                $site->setInstalledLocales($installedLocales);
                $supportedLocales = $site->getSupportedLocales();
                $supportedLocales = array_diff($supportedLocales, [$locale]);
                $site->setSupportedLocales($supportedLocales);
                $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
                $siteDao->updateObject($site);

                $this->_updateContextLocaleSettings($request);
                Locale::uninstallLocale($locale);

                $notificationManager = new NotificationManager();
                $user = $request->getUser();
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    Notification::NOTIFICATION_TYPE_SUCCESS,
                    ['contents' => __('notification.localeUninstalled', ['locale' => $localeData['name']])]
                );
            }
            return \PKP\db\DAO::getDataChangedEvent($locale);
        }

        return new JSONMessage(false);
    }

    /**
     * Enable an existing locale.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function enableLocale($args, $request)
    {
        $rowId = $request->getUserVar('rowId');
        $gridData = $this->getGridDataElements($request);

        if (array_key_exists($rowId, $gridData)) {
            $this->_updateLocaleSupportState($request, $rowId, true);

            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.localeEnabled')]
            );
        }

        return \PKP\db\DAO::getDataChangedEvent($rowId);
    }

    /**
     * Disable an existing locale.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function disableLocale($args, $request)
    {
        $locale = $request->getUserVar('rowId');
        $gridData = $this->getGridDataElements($request);
        $notificationManager = new NotificationManager();
        $user = $request->getUser();

        if ($request->checkCSRF() && array_key_exists($locale, $gridData)) {
            // Don't disable primary locales.
            if ($gridData[$locale]['primary']) {
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    Notification::NOTIFICATION_TYPE_ERROR,
                    ['contents' => __('admin.languages.cantDisable')]
                );
            } else {
                $this->_updateLocaleSupportState($request, $locale, false);
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    Notification::NOTIFICATION_TYPE_SUCCESS,
                    ['contents' => __('notification.localeDisabled')]
                );
            }
            return \PKP\db\DAO::getDataChangedEvent($locale);
        }

        return new JSONMessage(false);
    }


    /**
     * Set primary locale.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function setPrimaryLocale($args, $request)
    {
        $rowId = $request->getUserVar('rowId');
        $gridData = $this->getGridDataElements($request);
        $localeData = $gridData[$rowId];
        $notificationManager = new NotificationManager();
        $user = $request->getUser();
        $site = $request->getSite();

        if (array_key_exists($rowId, $gridData)) {
            if (Locale::isLocaleValid($rowId)) {
                $oldSitePrimaryLocale = $site->getPrimaryLocale();
                Repo::user()->dao->changeSitePrimaryLocale($oldSitePrimaryLocale, $rowId);
                $site->setPrimaryLocale($rowId);
                $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
                $siteDao->updateObject($site);

                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    Notification::NOTIFICATION_TYPE_SUCCESS,
                    ['contents' => __('notification.primaryLocaleDefined', ['locale' => $localeData['name']])]
                );
            }
        }

        // Need to refresh whole grid to remove the check in others
        // primary locale radio buttons.
        return \PKP\db\DAO::getDataChangedEvent();
    }


    //
    // Helper methods.
    //
    /**
     * Update the locale support state (enabled or disabled).
     *
     * @param Request $request
     * @param string $rowId The locale row id.
     * @param bool $enable Enable locale flag.
     */
    protected function _updateLocaleSupportState($request, $rowId, $enable)
    {
        $newSupportedLocales = [];
        $gridData = $this->getGridDataElements($request);

        foreach ($gridData as $locale => $data) {
            if ($data['supported']) {
                array_push($newSupportedLocales, $locale);
            }
        }

        if (Locale::isLocaleValid($rowId)) {
            if ($enable) {
                array_push($newSupportedLocales, $rowId);
            } else {
                $key = array_search($rowId, $newSupportedLocales);
                if ($key !== false) {
                    unset($newSupportedLocales[$key]);
                }
            }
        }

        $site = $request->getSite();
        $site->setSupportedLocales($newSupportedLocales);

        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
        $siteDao->updateObject($site);

        $this->_updateContextLocaleSettings($request);
    }

    /**
     * Helper function to update locale settings in all
     * installed contexts, based on site locale settings.
     *
     * @param object $request
     */
    protected function _updateContextLocaleSettings($request)
    {
        $site = $request->getSite();
        $siteSupportedLocales = $site->getSupportedLocales();
        $contextService = app()->get('context');

        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            $params = [];
            $primaryLocale = $context->getPrimaryLocale();
            $params['supportedDefaultSubmissionLocale'] = $context->getData('supportedDefaultSubmissionLocale');
            foreach (['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales', 'supportedSubmissionMetadataLocales'] as $settingName) {
                $localeList = $context->getData($settingName);

                if (is_array($localeList)) {
                    $params[$settingName] = array_intersect($localeList, $siteSupportedLocales);
                }
            }
            if (!in_array($primaryLocale, $siteSupportedLocales)) {
                $params['primaryLocale'] = $site->getPrimaryLocale();
                $primaryLocale = $params['primaryLocale'];
            }
            $errors = $contextService->validate(EntityWriteInterface::VALIDATE_ACTION_EDIT, $params, $params['supportedLocales'], $primaryLocale);
            // If there are errors, it's too late to do anything about it
            assert(empty($errors));
            $contextService->edit($context, $params, $request);
        }
    }

    /**
     * This grid can also present management functions
     * if the conditions above are true.
     *
     * @param Request $request
     *
     * @return bool
     */
    protected function _canManage($request)
    {
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll();
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        [$firstContext, $secondContext] = [$contexts->next(), $contexts->next()];
        return ($firstContext && !$secondContext && $request->getContext() && in_array(Role::ROLE_ID_MANAGER, $userRoles));
    }
}

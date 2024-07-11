<?php

/**
 * @file controllers/grid/languages/LanguageGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LanguageGridHandler
 *
 * @ingroup classes_controllers_grid_languages
 *
 * @brief Handle language grid requests.
 */

namespace PKP\controllers\grid\languages;

use APP\core\Application;
use APP\core\Request;
use APP\notification\NotificationManager;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\db\DAO;
use PKP\facades\Locale;
use PKP\notification\Notification;
use PKP\security\Role;

class LanguageGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['saveLanguageSetting', 'setContextPrimaryLocale', 'setDefaultSubmissionLocale']
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration.
        $this->setTitle('common.languages');
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new LanguageGridRow();
    }

    //
    // Public handler methods.
    //
    /**
     * Save language management settings.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON message
     */
    public function saveLanguageSetting($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $locale = (string) $request->getUserVar('rowId');
        $settingName = (string) $request->getUserVar('setting');
        $settingValue = (bool) $request->getUserVar('value');
        $availableLocales = $this->getGridDataElements($request);
        $context = $request->getContext();

        $contextService = app()->get('context');

        $permittedSettings = ['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales', 'supportedSubmissionMetadataLocales'];
        if (in_array($settingName, $permittedSettings) && $locale) {
            $currentSettingValue = (array) $context->getData($settingName);
            $isValidLocale = in_array($settingName, array_slice($permittedSettings, 0, 2)) ? Locale::isLocaleValid($locale) : Locale::isSubmissionLocaleValid($locale);
            if ($isValidLocale && array_key_exists($locale, $availableLocales)) {
                if ($settingValue) {
                    (array_push($currentSettingValue, $locale) && sort($currentSettingValue));
                    if ($settingName == 'supportedFormLocales') {
                        // reload localized default context settings
                        $contextService->restoreLocaleDefaults($context, $request, $locale);
                    } elseif ($settingName == 'supportedSubmissionLocales') {
                        // if a submission locale is enabled, and this locale is not in the metadata locales, add it
                        $supportedSubmissionMetadataLocales = (array) $context->getSupportedSubmissionMetadataLocales();
                        if (!in_array($locale, $supportedSubmissionMetadataLocales)) {
                            (array_push($supportedSubmissionMetadataLocales, $locale) && sort($supportedSubmissionMetadataLocales));
                            $context = $contextService->edit($context, ['supportedSubmissionMetadataLocales' => $supportedSubmissionMetadataLocales], $request);
                            // reload localized default context settings
                            $contextService->restoreLocaleDefaults($context, $request, $locale);
                        }
                    }
                } else {
                    if (($settingName == 'supportedSubmissionLocales' || $settingName == 'supportedSubmissionMetadataLocales') && $locale === $context->getSupportedDefaultSubmissionLocale() ||
                        ($settingName == 'supportedLocales' || $settingName == 'supportedFormLocales') && $locale === $context->getPrimaryLocale()) {
                        return new JSONMessage(false, __('notification.defaultLocaleSettingsCannotBeSaved'));
                    }

                    $key = array_search($locale, $currentSettingValue);
                    if ($key !== false) {
                        unset($currentSettingValue[$key]);
                    }

                    if ($currentSettingValue === []) {
                        return new JSONMessage(false, __('notification.localeSettingsCannotBeSaved'));
                    }

                    if ($settingName == 'supportedSubmissionMetadataLocales') {
                        // if a metadata locale is disabled, disable it form submission locales as well
                        $supportedSubmissionLocales = (array) $context->getSupportedSubmissionLocales();
                        $key = array_search($locale, $supportedSubmissionLocales);
                        if ($key !== false) {
                            unset($supportedSubmissionLocales[$key]);
                        }
                        $supportedSubmissionLocales = array_values($supportedSubmissionLocales);
                        if ($supportedSubmissionLocales == []) {
                            return new JSONMessage(false, __('notification.localeSettingsCannotBeSaved'));
                        }
                        $context = $contextService->edit($context, ['supportedSubmissionLocales' => $supportedSubmissionLocales], $request);
                    }
                }
            }
        }

        $context = $contextService->edit($context, [$settingName => array_values(array_unique($currentSettingValue))], $request);

        $notificationManager = new NotificationManager();
        $user = $request->getUser();
        $notificationManager->createTrivialNotification(
            $user->getId(),
            Notification::NOTIFICATION_TYPE_SUCCESS,
            ['contents' => __('notification.localeSettingsSaved')]
        );

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $json = \PKP\db\DAO::getDataChangedEvent($locale);
        $json->setGlobalEvent('set-form-languages', $locales);
        return $json;
    }

    /**
     * Set context primary locale.
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function setContextPrimaryLocale($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $locale = (string) $request->getUserVar('rowId');
        $context = $request->getContext();
        $availableLocales = $this->getGridDataElements($request);

        if (Locale::isLocaleValid($locale) && array_key_exists($locale, $availableLocales)) {
            // Make sure at least the primary locale is chosen as available
            $context = app()->get('context')->edit(
                $context,
                collect(['supportedLocales', 'supportedFormLocales'])
                    ->mapWithKeys(fn ($name) => [$name => collect($context->getData($name))->push($locale)->unique()->sort()->values()])
                    ->toArray(),
                $request
            );

            $context->setPrimaryLocale($locale);
            $contextDao = Application::getContextDAO();
            $contextDao->updateObject($context);

            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.localeSettingsSaved')]
            );
        }

        return DAO::getDataChangedEvent();
    }

    /**
     * Set default submission locale.
     */
    public function setDefaultSubmissionLocale(array $args, Request $request): JSONMessage
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        $locale = (string) $request->getUserVar('rowId');
        $context = $request->getContext();
        $availableLocales = $this->getGridDataElements($request);

        if (Locale::isSubmissionLocaleValid($locale) && array_key_exists($locale, $availableLocales)) {
            // Make sure at least the primary locale is chosen as available
            app()->get('context')->edit(
                $context,
                [
                    'supportedDefaultSubmissionLocale' => $locale,
                    ...collect(['supportedSubmissionLocales', 'supportedSubmissionMetadataLocales'])
                        ->mapWithKeys(fn ($name) => [$name => collect($context->getData($name))->push($locale)->unique()->sort()->values()])
                        ->toArray(),
                ],
                $request
            );

            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.localeSettingsSaved')]
            );
        }

        return DAO::getDataChangedEvent();
    }

    //
    // Protected methods.
    //
    /**
     * Return an instance of the cell provider
     * used by this grid.
     *
     * @return LanguageGridCellProvider
     */
    public function getCellProvider()
    {
        return new LanguageGridCellProvider();
    }

    /**
     * Add name column.
     */
    public function addNameColumn()
    {
        $cellProvider = $this->getCellProvider();

        // Locale name.
        $this->addColumn(
            new GridColumn(
                'locale',
                'grid.columns.locale',
                null,
                'controllers/grid/languages/localeNameCell.tpl',
                $cellProvider
            )
        );
    }

    /**
     * Add locale code column.
     */
    public function addLocaleCodeColumn()
    {
        $cellProvider = $this->getCellProvider();

        // Locale code.
        $this->addColumn(
            new GridColumn(
                'code',
                'grid.columns.locale.code',
                null,
                null,
                $cellProvider
            )
        );
    }

    /**
     * Add website/submission primary/default column.
     */
    public function addPrimaryColumn(string $columnId, string $name = 'locale.primary')
    {
        $cellProvider = $this->getCellProvider();

        $this->addColumn(
            new GridColumn(
                $columnId,
                $name,
                null,
                'controllers/grid/common/cell/radioButtonCell.tpl',
                $cellProvider
            )
        );
    }

    /**
     * Add columns related to management settings.
     */
    public function addManagementColumns()
    {
        $cellProvider = $this->getCellProvider();
        $this->addColumn(
            new GridColumn(
                'uiLocale',
                'manager.language.ui',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $cellProvider
            )
        );

        $this->addColumn(
            new GridColumn(
                'formLocale',
                'manager.language.forms',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $cellProvider
            )
        );
    }

    /**
     * Add columns related to submission langauge settings.
     */
    public function addSubmissionColumns(): void
    {
        $cellProvider = $this->getCellProvider();

        $this->addColumn(
            new GridColumn(
                'submissionLocale',
                'manager.language.submissions',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $cellProvider
            )
        );

        $this->addColumn(
            new GridColumn(
                'submissionMetadataLocale',
                'manager.language.submissionMetadata',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $cellProvider
            )
        );
    }

    /**
     * Add locales data related to management settings.
     * $data Data already loaded.
     * Return Same passed array, but with the extra management data inserted.
     */
    public function addLocaleSettingData(Request $request, array $data, array $localeSettingNames = ['supportedFormLocales', 'supportedLocales']): array
    {
        $context = $request->getContext();

        if (is_array($data)) {
            foreach ($data as $locale => $localeData) {
                foreach ($localeSettingNames as $name) {
                    $data[$locale][$name] = in_array($locale, $context->getData($name));
                }
            }
        } else {
            assert(false);
        }

        return $data;
    }
}

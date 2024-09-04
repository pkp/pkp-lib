<?php

/**
 * @file controllers/grid/settings/languages/SubmissionLanguageGridHandler.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionLanguageGridHandler
 *
 * @ingroup controllers_grid_settings_languages
 *
 * @brief Handle language submission grid requests only.
 */

namespace PKP\controllers\grid\settings\languages;

use APP\notification\NotificationManager;
use PKP\controllers\grid\languages\form\AddLanguageForm;
use PKP\controllers\grid\languages\LanguageGridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class SubmissionLanguageGridHandler extends LanguageGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['addLanguages', 'addLanguageModal', 'fetchGrid', 'fetchRow']
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
        $context = $request->getContext();
        $addedLocales = $context->getSupportedAddedSubmissionLocales();
        $defaultSubmissionLocale = $context->getSupportedDefaultSubmissionLocale();
        $localeNames = Locale::getSubmissionLocaleDisplayNames($addedLocales);

        $data = [];

        foreach ($addedLocales as $locale) {
            $data[$locale] = [];
            $data[$locale]['code'] = $locale;
            $data[$locale]['name'] = "{$localeNames[$locale]}/" . Locale::getSubmissionLocaleDisplayNames([$locale], $locale)[$locale];
            $data[$locale]['supported'] = true;
            $data[$locale]['supportedDefaultSubmissionLocale'] = ($locale === $defaultSubmissionLocale);
        }

        $data = $this->addLocaleSettingData($request, $data, ['supportedSubmissionLocales', 'supportedSubmissionMetadataLocales']);
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
    public function initialize($request, $args = null): void
    {
        parent::initialize($request, $args);

        $this->setTitle('manager.language.submissionLanguages');

        $this->addNameColumn();
        $this->addLocaleCodeColumn();
        $this->addPrimaryColumn('defaultSubmissionLocale', 'common.default');
        $this->addSubmissionColumns();

        // Add grid action.
        $this->addAction(
            new LinkAction(
                'addLanguageModal',
                new AjaxModal(
                    ($request->getRouter())->url($request, null, null, 'addLanguageModal', null, null),
                    __('manager.language.gridAction.addLangauage'),
                    null,
                    true,
                    'addLanguageForm'
                ),
                __('manager.language.gridAction.addLangauage')
            )
        );
    }

    /**
     * Show the add language form.
     */
    public function addLanguageModal(array $args, PKPRequest $request): JSONMessage
    {
        $addLanguageForm = new AddLanguageForm();
        $addLanguageForm->initData();
        return new JSONMessage(true, $addLanguageForm->fetch($request));
    }

    /**
     * Add/Remove languages
     */
    public function addLanguages(array $args, PKPRequest $request): JSONMessage
    {
        $addLanguageForm = new AddLanguageForm();
        $addLanguageForm->readInputData();

        if ($addLanguageForm->validate()) {
            $addLanguageForm->execute();

            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                ['contents' => __('notification.submissionLocales')]
            );

            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(true, $addLanguageForm->fetch($request));
        }
    }
}

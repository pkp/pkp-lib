<?php

/**
 * @file controllers/grid/announcements/AnnouncementTypeGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeGridHandler
 *
 * @ingroup controllers_grid_announcements
 *
 * @brief Handle announcement type grid requests.
 */

namespace PKP\controllers\grid\announcements;

use APP\notification\NotificationManager;
use PKP\announcement\AnnouncementTypeDAO;
use PKP\controllers\grid\announcements\form\AnnouncementTypeForm;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\Notification;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;

class AnnouncementTypeGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            [
                'fetchGrid', 'fetchRow',
                'addAnnouncementType', 'editAnnouncementType',
                'updateAnnouncementType',
                'deleteAnnouncementType'
            ]
        );
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc GridHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $context = $request->getContext();

        if ($context) {
            $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        } else {
            $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        }

        $announcementTypeId = $request->getUserVar('announcementTypeId');
        if ($announcementTypeId) {
            // Ensure announcement type is valid and for this context
            $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
            $announcementType = $announcementTypeDao->getById($announcementTypeId);
            if (!$announcementType || $announcementType->getContextId() != $context?->getId()) {
                return false;
            }
        }
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration
        $this->setTitle('manager.announcementTypes');

        // Set the no items row text
        $this->setEmptyRowText('manager.announcementTypes.noneCreated');

        // Columns
        $announcementTypeCellProvider = new AnnouncementTypeGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $announcementTypeCellProvider,
                ['width' => 60]
            )
        );

        // Add grid action.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'addAnnouncementType',
                new AjaxModal(
                    $router->url($request, null, null, 'addAnnouncementType', null, null),
                    __('grid.action.addAnnouncementType'),
                    null,
                    true
                ),
                __('grid.action.addAnnouncementType'),
                'add_item'
            )
        );
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
        return $announcementTypeDao->getByContextId($request->getContext()?->getId());
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new AnnouncementTypeGridRow();
    }

    //
    // Public grid actions.
    //
    /**
     * Display form to add announcement type.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function addAnnouncementType($args, $request)
    {
        return $this->editAnnouncementType($args, $request);
    }

    /**
     * Display form to edit an announcement type.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editAnnouncementType($args, $request)
    {
        $announcementTypeId = (int)$request->getUserVar('announcementTypeId');
        $announcementTypeForm = new AnnouncementTypeForm($request->getContext()?->getId(), $announcementTypeId);
        $announcementTypeForm->initData();

        return new JSONMessage(true, $announcementTypeForm->fetch($request));
    }

    /**
     * Save an edited/inserted announcement type.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateAnnouncementType($args, $request)
    {
        // Identify the announcement type id.
        $announcementTypeId = $request->getUserVar('announcementTypeId');

        // Form handling.
        $announcementTypeForm = new AnnouncementTypeForm($request->getContext()?->getId(), $announcementTypeId);
        $announcementTypeForm->readInputData();

        if ($announcementTypeForm->validate()) {
            $announcementTypeForm->execute();

            if ($announcementTypeId) {
                // Successful edit of an existing announcement type.
                $notificationLocaleKey = 'notification.editedAnnouncementType';
            } else {
                // Successful added a new announcement type.
                $notificationLocaleKey = 'notification.addedAnnouncementType';
            }

            // Record the notification to user.
            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __($notificationLocaleKey)]);

            // Prepare the grid row data.
            return \PKP\db\DAO::getDataChangedEvent($announcementTypeId);
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Delete an announcement type.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteAnnouncementType($args, $request)
    {
        $announcementTypeId = (int) $request->getUserVar('announcementTypeId');

        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
        $announcementType = $announcementTypeDao->getById($announcementTypeId, $request->getContext()?->getId());
        if ($announcementType && $request->checkCSRF()) {
            $announcementTypeDao->deleteObject($announcementType);

            // Create notification.
            $notificationManager = new NotificationManager();
            $user = $request->getUser();
            $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.removedAnnouncementType')]);

            return \PKP\db\DAO::getDataChangedEvent();
        }

        return new JSONMessage(false);
    }
}

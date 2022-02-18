<?php

/**
 * @file controllers/grid/users/author/AuthorGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorGridHandler
 * @ingroup controllers_grid_users_author
 *
 * @brief base PKP class to handle author grid requests.
 */

import('lib.pkp.controllers.grid.users.author.PKPAuthorGridCellProvider');
import('lib.pkp.controllers.grid.users.author.AuthorGridRow');

use APP\facades\Repo;
use APP\notification\NotificationManager;

use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\PKPNotification;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\Role;

use PKP\submission\PKPSubmission;

class AuthorGridHandler extends GridHandler
{
    /** @var bool */
    public $_readOnly;

    /** @var int */
    public $_version;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow', 'addAuthor', 'editAuthor',
                'updateAuthor', 'deleteAuthor', 'saveSequence']
        );
        $this->addRoleAssignment(Role::ROLE_ID_REVIEWER, ['fetchGrid', 'fetchRow']);
        $this->addRoleAssignment([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], ['addUser']);
    }


    //
    // Getters/Setters
    //
    /**
     * Get the submission associated with this author grid.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the publication associated with this author grid.
     *
     * @return Submission
     */
    public function getPublication()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
    }

    /**
     * Get whether or not this grid should be 'read only'
     *
     * @return bool
     */
    public function getReadOnly()
    {
        return $this->_readOnly;
    }

    /**
     * Set the boolean for 'read only' status
     *
     * @param bool $readOnly
     */
    public function setReadOnly($readOnly)
    {
        $this->_readOnly = $readOnly;
    }

    //
    // Overridden methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
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

        $this->setTitle('submission.contributors');

        if ($this->getSubmission()->getData('submissionProgress') || $this->canAdminister($request->getUser())) {
            $this->setReadOnly(false);
            // Grid actions
            $router = $request->getRouter();
            $actionArgs = $this->getRequestArgs();

            $this->addAction(
                new LinkAction(
                    'addAuthor',
                    new AjaxModal(
                        $router->url($request, null, null, 'addAuthor', null, $actionArgs),
                        __('grid.action.addContributor'),
                        'modal_add_user'
                    ),
                    __('grid.action.addContributor'),
                    'add_user'
                )
            );
        } else {
            $this->setReadOnly(true);
        }

        // Columns
        $cellProvider = new PKPAuthorGridCellProvider($this->getPublication());
        $this->addColumn(
            new GridColumn(
                'name',
                'author.users.contributor.name',
                null,
                null,
                $cellProvider,
                ['width' => 40, 'alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT]
            )
        );
        $this->addColumn(
            new GridColumn(
                'email',
                'author.users.contributor.email',
                null,
                null,
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'role',
                'author.users.contributor.role',
                null,
                null,
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'principalContact',
                'author.users.contributor.principalContact',
                null,
                'controllers/grid/users/author/primaryContact.tpl',
                $cellProvider
            )
        );
        $this->addColumn(
            new GridColumn(
                'includeInBrowse',
                'author.users.contributor.includeInBrowse',
                null,
                'controllers/grid/users/author/includeInBrowse.tpl',
                $cellProvider
            )
        );
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @see GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        $features = parent::initFeatures($request, $args);
        if ($this->canAdminister($request->getUser())) {
            $features[] = new OrderGridItemsFeature();
        }
        return $features;
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($gridDataElement)
    {
        return $gridDataElement->getSequence();
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        if (!$this->canAdminister($request->getUser())) {
            return;
        }

        $author = Repo::author()->get((int) $rowId);

        Repo::author()->edit($author, ['seq' => $newSequence]);
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return AuthorGridRow
     */
    protected function getRowInstance()
    {
        return new AuthorGridRow($this->getSubmission(), $this->getPublication(), $this->getReadOnly());
    }

    /**
     * Get the arguments that will identify the data in the grid.
     * Overridden by child grids.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        $submission = $this->getSubmission();
        $publication = $this->getPublication();
        return [
            'submissionId' => $submission->getId(),
            'publicationId' => $publication->getId()
        ];
    }

    /**
     * Determines if there should be add/edit actions on this grid.
     *
     * @param User $user
     *
     * @return bool
     */
    public function canAdminister($user)
    {
        $publication = $this->getPublication();
        $submission = $this->getSubmission();
        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

        if ($publication->getData('status') === PKPSubmission::STATUS_PUBLISHED) {
            return false;
        }

        if (in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            return true;
        }

        // Incomplete submissions can be edited. (Presumably author.)
        if ($submission->getDateSubmitted() == null) {
            return true;
        }

        // The user may not be allowed to edit the metadata
        if (Repo::submission()->canEditPublication($submission->getId(), $user->getId())) {
            return true;
        }

        // Default: Read-only.
        return false;
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        $authors = Repo::author()->getMany(
            Repo::author()
                ->getCollector()
                ->filterByPublicationIds([$this->getPublication()->getId()])
                ->orderBy(Repo::author()->getCollector()::ORDERBY_SEQUENCE)
        );

        return $authors;
    }

    //
    // Public Author Grid Actions
    //
    /**
     * An action to manually add a new author
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addAuthor($args, $request)
    {
        if (!$this->canAdminister($request->getUser())) {
            return new JSONMessage(false);
        }
        // Calling editAuthor() with an empty row id will add
        // a new author.
        return $this->editAuthor($args, $request);
    }

    /**
     * Edit an author
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editAuthor($args, $request)
    {
        if (!$this->canAdminister($request->getUser())) {
            return new JSONMessage(false);
        }
        // Identify the author to be updated
        $authorId = (int) $request->getUserVar('authorId');

        $author = Repo::author()->get($authorId);

        // Form handling
        import('controllers.grid.users.author.form.AuthorForm');
        $authorForm = new AuthorForm($this->getPublication(), $author);
        $authorForm->initData();

        return new JSONMessage(true, $authorForm->fetch($request));
    }

    /**
     * Update an author
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateAuthor($args, $request)
    {
        if (!$this->canAdminister($request->getUser())) {
            return new JSONMessage(false);
        }
        // Identify the author to be updated
        $authorId = (int) $request->getUserVar('authorId');
        $publication = $this->getPublication();

        $author = Repo::author()->get($authorId);

        // Form handling
        import('controllers.grid.users.author.form.AuthorForm');
        $authorForm = new AuthorForm($publication, $author);
        $authorForm->readInputData();
        if ($authorForm->validate()) {
            $authorId = $authorForm->execute();

            if (!isset($author)) {
                // This is a new contributor
                $author = Repo::author()->get($authorId);
                // New added author action notification content.
                $notificationContent = __('notification.addedAuthor');
            } else {
                // Author edition action notification content.
                $notificationContent = __('notification.editedAuthor');
            }

            // Create trivial notification.
            $currentUser = $request->getUser();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => $notificationContent]);

            // Prepare the grid row data
            $row = $this->getRowInstance();
            $row->setGridId($this->getId());
            $row->setId($authorId);
            $row->setData($author);
            $row->initialize($request);

            // Render the row into a JSON response
            if ($author->getPrimaryContact()) {
                // If this is the primary contact, redraw the whole grid
                // so that it takes the checkbox off other rows.
                $json = \PKP\db\DAO::getDataChangedEvent();
            } else {
                $json = \PKP\db\DAO::getDataChangedEvent($authorId);
            }
            $json->setGlobalEvent('authorsUpdated');
            return $json;
        } else {
            return new JSONMessage(true, $authorForm->fetch($request));
        }
    }

    /**
     * Delete a author
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteAuthor($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }
        if (!$this->canAdminister($request->getUser())) {
            return new JSONMessage(false);
        }

        $authorId = (int) $request->getUserVar('authorId');

        $author = Repo::author()->get($authorId);
        Repo::author()->delete($author);

        $json = \PKP\db\DAO::getDataChangedEvent($authorId);
        $json->setGlobalEvent('authorsUpdated');
        return $json;
    }

    /**
     * Add a user with data initialized from an existing author.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function addUser($args, $request)
    {
        // Identify the author Id.
        $authorId = (int) $request->getUserVar('authorId');

        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */

        $author = Repo::author()->get($authorId);

        if ($author !== null && Repo::user()->getByEmail($author->getEmail(), true)) {
            // We don't have administrative rights over this user.
            return new JSONMessage(false, __('grid.user.cannotAdminister'));
        } else {
            // Form handling.
            import('lib.pkp.controllers.grid.settings.user.form.UserDetailsForm');
            $userForm = new UserDetailsForm($request, null, $author);
            $userForm->initData();

            return new JSONMessage(true, $userForm->display($request));
        }
    }
}

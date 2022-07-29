<?php

/**
 * @file controllers/grid/users/author/AuthorGridRow.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorGridRow
 * @ingroup controllers_grid_users_author
 *
 * @brief Base class for author grid row definition
 */

namespace PKP\controllers\grid\users\author;

use APP\facades\Repo;
use PKP\controllers\grid\GridRow;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class AuthorGridRow extends GridRow
{
    /** @var Submission */
    public $_submission;

    /** @var Publication */
    public $_publication;

    /** @var bool */
    public $_readOnly;

    /** @var int */
    public $_version;

    /**
     * Constructor
     */
    public function __construct($submission, $publication, $readOnly = false)
    {
        $this->_submission = $submission;
        $this->_publication = $publication;
        $this->_readOnly = $readOnly;
        parent::__construct();
    }

    //
    // Overridden methods from GridRow
    //
    /**
     * @copydoc GridRow::initialize()
     *
     * @param null|mixed $template
     */
    public function initialize($request, $template = null)
    {
        // Do the default initialization
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $rowId = $this->getId();
        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = $this->getRequestArgs();
            $actionArgs['authorId'] = $rowId;

            if (!$this->isReadOnly()) {
                // Add row-level actions
                $this->addAction(
                    new LinkAction(
                        'editAuthor',
                        new AjaxModal(
                            $router->url($request, null, null, 'editAuthor', null, $actionArgs),
                            __('grid.action.editContributor'),
                            'modal_edit'
                        ),
                        __('grid.action.edit'),
                        'edit'
                    )
                );

                $this->addAction(
                    new LinkAction(
                        'deleteAuthor',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('common.confirmDelete'),
                            __('common.delete'),
                            $router->url($request, null, null, 'deleteAuthor', null, $actionArgs),
                            'modal_delete'
                        ),
                        __('grid.action.delete'),
                        'delete'
                    )
                );

                $author = Repo::author()->get((int) $rowId);

                if ($author && !Repo::user()->getByEmail($author->getEmail(), true)) {
                    $this->addAction(
                        new LinkAction(
                            'addUser',
                            new AjaxModal(
                                $router->url($request, null, null, 'addUser', null, $actionArgs),
                                __('grid.user.add'),
                                'modal_add_user',
                                true
                            ),
                            __('grid.user.add'),
                            'add_user'
                        )
                    );
                }
            }
        }
    }

    /**
     * Get the submission for this row (already authorized)
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->_submission;
    }

    /**
     * Get the publication for this row (already authorized)
     *
     * @return Publication
     */
    public function getPublication()
    {
        return $this->_publication;
    }

    /**
     * Get the base arguments that will identify the data in the grid.
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
     * Determines whether the current user can create user accounts from authors present
     * in the grid.
     * Overridden by child grid rows.
     *
     * @param PKPRequest $request
     *
     * @return bool
     */
    public function allowedToCreateUser($request)
    {
        return false;
    }

    /**
     * Determine if this grid row should be read only.
     *
     * @return bool
     */
    public function isReadOnly()
    {
        return $this->_readOnly;
    }
}

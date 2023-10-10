<?php

/**
 * @file controllers/grid/settings/user/UserGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGridRow
 *
 * @ingroup controllers_grid_settings_user
 *
 * @brief User grid row definition
 */

namespace PKP\controllers\grid\settings\user;

use APP\facades\Repo;
use PKP\controllers\grid\GridRow;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectConfirmationModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\security\Validation;
use PKP\user\User;

class UserGridRow extends GridRow
{
    /** @var int the user id of the old user to remove when merging users. */
    public $_oldUserId;

    /**
     * Constructor
     *
     * @param null|mixed $oldUserId
     */
    public function __construct($oldUserId = null)
    {
        $this->_oldUserId = $oldUserId;
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
        parent::initialize($request, $template);

        // Is this a new row or an existing row?
        $element = & $this->getData();
        assert($element instanceof User);

        $rowId = $this->getId();

        if (!empty($rowId) && is_numeric($rowId)) {
            // Only add row actions if this is an existing row
            $router = $request->getRouter();
            $actionArgs = [
                'gridId' => $this->getGridId(),
                'rowId' => $rowId
            ];

            $actionArgs = array_merge($actionArgs, $this->getRequestArgs());

            // If this is the grid for merging a user, only show the merge
            // linkaction
            if ($this->getOldUserId()) {
                $actionArgs['oldUserId'] = $this->getOldUserId();
                $actionArgs['newUserId'] = $rowId;

                // Verify that the old user exists
                $oldUser = Repo::user()->get((int) $this->getOldUserId(), true);

                // Don't merge a user in itself
                if ($oldUser && $actionArgs['oldUserId'] != $actionArgs['newUserId']) {
                    $this->addAction(
                        new LinkAction(
                            'mergeUser',
                            new RemoteActionConfirmationModal(
                                $request->getSession(),
                                __('grid.user.mergeUsers.confirm', ['oldUsername' => $oldUser->getUsername(), 'newUsername' => $element->getUsername()]),
                                null,
                                $router->url($request, null, null, 'mergeUsers', null, $actionArgs),
                                'modal_merge_users'
                            ),
                            __('grid.user.mergeUsers.mergeIntoUser'),
                            'merge_users'
                        )
                    );
                }

                // Otherwise display all the default link actions
            } else {
                $this->addAction(
                    new LinkAction(
                        'email',
                        new AjaxModal(
                            $router->url($request, null, null, 'editEmail', null, $actionArgs),
                            __('grid.user.email'),
                            'modal_email',
                            true
                        ),
                        __('grid.user.email'),
                        'notify'
                    )
                );
                $this->addAction(
                    new LinkAction(
                        'edit',
                        new AjaxModal(
                            $router->url($request, null, null, 'editUser', null, $actionArgs),
                            __('grid.user.edit'),
                            'modal_edit',
                            true
                        ),
                        __('grid.user.edit'),
                        'edit'
                    )
                );
                if ($element->getDisabled()) {
                    $actionArgs['enable'] = true;
                    $this->addAction(
                        new LinkAction(
                            'enable',
                            new AjaxModal(
                                $router->url($request, null, null, 'editDisableUser', null, $actionArgs),
                                __('common.enable'),
                                'enable',
                                true
                            ),
                            __('common.enable'),
                            'enable'
                        )
                    );
                } else {
                    $actionArgs['enable'] = false;
                    $this->addAction(
                        new LinkAction(
                            'disable',
                            new AjaxModal(
                                $router->url($request, null, null, 'editDisableUser', null, $actionArgs),
                                __('grid.user.disable'),
                                'disable',
                                true
                            ),
                            __('grid.user.disable'),
                            'disable'
                        )
                    );
                }
                $this->addAction(
                    new LinkAction(
                        'remove',
                        new RemoteActionConfirmationModal(
                            $request->getSession(),
                            __('manager.people.confirmRemove'),
                            __('common.remove'),
                            $router->url($request, null, null, 'removeUser', null, $actionArgs),
                            'modal_delete'
                        ),
                        __('grid.action.remove'),
                        'delete'
                    )
                );

                $canAdminister = Validation::getAdministrationLevel($this->getId(), $request->getUser()->getId()) === Validation::ADMINISTRATION_FULL;
                if (
                    !Validation::loggedInAs() &&
                    $request->getUser()->getId() != $this->getId() &&
                    $canAdminister
                ) {
                    $dispatcher = $router->getDispatcher();
                    $this->addAction(
                        new LinkAction(
                            'logInAs',
                            new RedirectConfirmationModal(
                                __('grid.user.confirmLogInAs'),
                                __('grid.action.logInAs'),
                                $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'login', 'signInAsUser', $this->getId())
                            ),
                            __('grid.action.logInAs'),
                            'enroll_user'
                        )
                    );
                }

                // do not allow the deletion of your own account.
                if (
                    $request->getUser()->getId() != $this->getId() and
                    $canAdminister
                ) {
                    $this->addAction(
                        new LinkAction(
                            'mergeUser',
                            new AjaxModal(
                                $router->url($request, null, null, 'mergeUsers', null, ['oldUserId' => $rowId]),
                                __('grid.user.mergeUsers.mergeUser'),
                                'modal_merge_users',
                                true
                            ),
                            __('grid.user.mergeUsers.mergeUser'),
                            'merge_users'
                        )
                    );
                }
            }
        }
    }

    /**
     * Returns the stored user id of the user to be removed.
     *
     * @return int the user id.
     */
    public function getOldUserId()
    {
        return $this->_oldUserId;
    }
}

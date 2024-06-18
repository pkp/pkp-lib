<?php

/**
 * @file controllers/grid/admin/context/ContextGridRow.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextGridRow
 *
 * @ingroup controllers_grid_admin_context
 *
 * @brief Context grid row definition
 */

namespace PKP\controllers\grid\admin\context;

use APP\core\Application;
use PKP\controllers\grid\GridRow;
use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;

class ContextGridRow extends GridRow
{
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
        $element = $this->getData();
        assert($element instanceof \PKP\context\Context);

        $rowId = $this->getId();

        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'edit',
                new AjaxModal(
                    $router->url($request, null, null, 'editContext', null, ['rowId' => $rowId]),
                    __('grid.action.edit'),
                    'modal_edit',
                    true,
                    'context',
                    ['editContext']
                ),
                __('grid.action.edit'),
                'edit'
            )
        );
        $this->addAction(
            new LinkAction(
                'delete',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __('admin.contexts.confirmDelete', ['contextName' => $element->getLocalizedName()]),
                    null,
                    $router->url($request, null, null, 'deleteContext', null, ['rowId' => $rowId])
                ),
                __('grid.action.remove'),
                'delete'
            )
        );
        $dispatcher = $router->getDispatcher();
        $this->addAction(
            new LinkAction(
                'wizard',
                new RedirectAction($dispatcher->url($request, PKPApplication::ROUTE_PAGE, Application::SITE_CONTEXT_PATH, 'admin', 'wizard', [$element->getId()])),
                __('grid.action.wizard'),
                'wrench'
            )
        );
        $this->addAction(
            new LinkAction(
                'users',
                new AjaxModal(
                    $router->url($request, $element->getPath(), null, 'users', null),
                    __('manager.users'),
                    'modal_edit',
                    true
                ),
                __('manager.users'),
                'users'
            )
        );
    }
}

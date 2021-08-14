<?php

/**
 * @file controllers/grid/admin/context/ContextGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextGridHandler
 * @ingroup controllers_grid_admin_context
 *
 * @brief Handle context grid requests.
 */

import('lib.pkp.controllers.grid.admin.context.ContextGridRow');

use APP\core\Services;
use APP\template\TemplateManager;

use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class ContextGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SITE_ADMIN],
            ['fetchGrid', 'fetchRow', 'createContext', 'editContext', 'updateContext', 'users',
                'deleteContext', 'saveSequence']
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc PKPHandler::authorize()
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
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->setTitle('context.contexts');

        // Grid actions.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'createContext',
                new AjaxModal(
                    $router->url($request, null, null, 'createContext', null, null),
                    __('admin.contexts.create'),
                    'modal_add_item',
                    true,
                    'context',
                    ['editContext']
                ),
                __('admin.contexts.create'),
                'add_item'
            )
        );

        //
        // Grid columns.
        //
        import('lib.pkp.controllers.grid.admin.context.ContextGridCellProvider');
        $contextGridCellProvider = new ContextGridCellProvider();

        // Context name.
        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $contextGridCellProvider
            )
        );

        // Context path.
        $this->addColumn(
            new GridColumn(
                'urlPath',
                'context.path',
                null,
                null,
                $contextGridCellProvider
            )
        );
    }


    //
    // Implement methods from GridHandler.
    //
    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return UserGridRow
     */
    protected function getRowInstance()
    {
        return new ContextGridRow();
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        // Get all contexts.
        $contextDao = Application::getContextDAO();
        $contexts = $contextDao->getAll();

        return $contexts->toAssociativeArray();
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $contextDao = Application::getContextDAO();
        $gridDataElement->setSequence($newSequence);
        $contextDao->updateObject($gridDataElement);
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($gridDataElement)
    {
        return $gridDataElement->getSequence();
    }

    /**
     * @copydoc GridHandler::addFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new OrderGridItemsFeature()];
    }

    /**
     * Get the list of "publish data changed" events.
     * Used to update the site context switcher upon create/delete.
     *
     * @return array
     */
    public function getPublishChangeEvents()
    {
        return ['updateHeader'];
    }


    //
    // Public grid actions.
    //
    /**
     * Add a new context.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function createContext($args, $request)
    {
        // Calling editContext with an empty row id will add a new context.
        return $this->editContext($args, $request);
    }

    /**
     * Edit an existing context.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editContext($args, $request)
    {
        $contextService = Services::get('context');
        $context = null;

        if ($request->getUserVar('rowId')) {
            $context = $contextService->get((int) $request->getUserVar('rowId'));
            if (!$context) {
                return new JSONMessage(false);
            }
        }

        $dispatcher = $request->getDispatcher();
        if ($context) {
            $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
            $supportedLocales = $context->getSupportedFormLocales();
        } else {
            $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, CONTEXT_ID_ALL, 'contexts');
            $supportedLocales = $request->getSite()->getSupportedLocales();
        }

        $localeNames = Locale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedLocales);

        $contextForm = new \APP\components\forms\context\ContextForm($apiUrl, $locales, $request->getBaseUrl(), $context);
        $contextFormConfig = $contextForm->getConfig();

        // Pass the URL to the context settings wizard so that the AddContextForm
        // component can redirect to it when a new context is added.
        if (!$context) {
            $contextFormConfig['editContextUrl'] = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, 'index', 'admin', 'wizard', '__id__');
        }

        $containerData = [
            'components' => [
                FORM_CONTEXT => $contextFormConfig,
            ],
        ];

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'containerData' => $containerData,
            'isAddingNewContext' => !$context,
        ]);

        return new JSONMessage(true, $templateMgr->fetch('admin/editContext.tpl'));
    }

    /**
     * Delete a context.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteContext($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $contextService = Services::get('context');

        $context = $contextService->get((int) $request->getUserVar('rowId'));

        if (!$context) {
            return new JSONMessage(false);
        }

        $contextService->delete($context);

        return \PKP\db\DAO::getDataChangedEvent($context->getId());
    }

    /**
     * Display users management grid for the given context.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function users($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('oldUserId', (int) $request->getUserVar('oldUserId')); // for merging users.
        parent::setupTemplate($request);
        return $templateMgr->fetchJson('management/accessUsers.tpl');
    }
}

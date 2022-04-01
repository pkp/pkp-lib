<?php

/**
 * @file controllers/grid/settings/submissionChecklist/SubmissionChecklistGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionChecklistGridHandler
 * @ingroup controllers_grid_settings_submissionChecklist
 *
 * @brief Handle submissionChecklist grid requests.
 */

import('lib.pkp.controllers.grid.settings.SetupGridHandler');
import('lib.pkp.controllers.grid.settings.submissionChecklist.SubmissionChecklistGridRow');

use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\core\JSONMessage;
use PKP\facades\Locale;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\security\Role;

class SubmissionChecklistGridHandler extends SetupGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER],
            ['fetchGrid', 'fetchRow', 'addItem', 'editItem', 'updateItem', 'deleteItem', 'saveSequence']
        );
    }

    //
    // Overridden template methods
    //
    /**
     * @copydoc SetupGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration
        $this->setId('submissionChecklist');
        $this->setTitle('manager.setup.submissionPreparationChecklist');

        // Add grid-level actions
        $router = $request->getRouter();
        $this->addAction(
            new LinkAction(
                'addItem',
                new AjaxModal(
                    $router->url($request, null, null, 'addItem', null, ['gridId' => $this->getId()]),
                    __('grid.action.addItem'),
                    'modal_add_item',
                    true
                ),
                __('grid.action.addItem'),
                'add_item'
            )
        );

        // Columns
        $this->addColumn(
            new GridColumn(
                'content',
                'grid.submissionChecklist.column.checklistItem',
                null,
                null,
                null,
                ['html' => true, 'maxLength' => 220]
            )
        );
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new OrderGridItemsFeature()];
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new SubmissionChecklistGridRow();
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Elements to be displayed in the grid
        $router = $request->getRouter();
        $context = $router->getContext($request);
        $submissionChecklist = $context->getData('submissionChecklist');
        return $submissionChecklist[Locale::getLocale()];
    }


    //
    // Public grid actions.
    //
    /**
     * An action to add a new submissionChecklist
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addItem($args, $request)
    {
        // Calling editItem with an empty row id will add a new row.
        return $this->editItem($args, $request);
    }

    /**
     * An action to edit a submissionChecklist
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editItem($args, $request)
    {
        import('lib.pkp.controllers.grid.settings.submissionChecklist.form.SubmissionChecklistForm');
        $submissionChecklistId = $args['rowId'] ?? null;
        $submissionChecklistForm = new SubmissionChecklistForm($submissionChecklistId);

        $submissionChecklistForm->initData($args);

        return new JSONMessage(true, $submissionChecklistForm->fetch($request));
    }

    /**
     * Update a submissionChecklist
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateItem($args, $request)
    {
        // -> submissionChecklistId must be present and valid
        // -> htmlId must be present and valid

        import('lib.pkp.controllers.grid.settings.submissionChecklist.form.SubmissionChecklistForm');
        $submissionChecklistId = $args['rowId'] ?? null;
        $submissionChecklistForm = new SubmissionChecklistForm($submissionChecklistId);
        $submissionChecklistForm->readInputData();

        if ($submissionChecklistForm->validate()) {
            $submissionChecklistForm->execute();
            return \PKP\db\DAO::getDataChangedEvent($submissionChecklistForm->submissionChecklistId);
        } else {
            return new JSONMessage(false);
        }
    }

    /**
     * Delete a submissionChecklist
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteItem($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $rowId = $request->getUserVar('rowId');

        $router = $request->getRouter();
        $context = $router->getContext($request);

        // get all of the submissionChecklists
        $submissionChecklistAll = $context->getData('submissionChecklist');

        foreach ($context->getSupportedLocaleNames() as $locale => $name) {
            if (isset($submissionChecklistAll[$locale][$rowId])) {
                unset($submissionChecklistAll[$locale][$rowId]);
            } else {
                // only fail if the currently displayed locale was not set
                // (this is the one that needs to be removed from the currently displayed grid)
                if ($locale == Locale::getLocale()) {
                    return new JSONMessage(false, __('manager.setup.errorDeletingSubmissionChecklist'));
                }
            }
        }

        $context->updateSetting('submissionChecklist', $submissionChecklistAll, 'object', true);
        return \PKP\db\DAO::getDataChangedEvent($rowId);
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($gridDataElement)
    {
        return $gridDataElement['order'];
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $router = $request->getRouter();
        $context = $router->getContext($request);

        // Get all of the submissionChecklists.
        $submissionChecklistAll = $context->getData('submissionChecklist');
        $locale = Locale::getLocale();

        if (isset($submissionChecklistAll[$locale][$rowId])) {
            $submissionChecklistAll[$locale][$rowId]['order'] = $newSequence;
        }

        $orderMap = [];
        foreach ($submissionChecklistAll[$locale] as $id => $checklistItem) {
            $orderMap[$id] = $checklistItem['order'];
        }

        asort($orderMap);

        // Build the new order checklist object.
        $orderedChecklistItems = [];
        foreach ($orderMap as $id => $order) {
            if (isset($submissionChecklistAll[$locale][$id])) {
                $orderedChecklistItems[$locale][$id] = $submissionChecklistAll[$locale][$id];
            }
        }

        // Update both the in-memory value and database setting.
        $context->setData('submissionChecklist', $orderedChecklistItems);
        $context->updateSetting('submissionChecklist', $orderedChecklistItems, 'object', true);
    }
}

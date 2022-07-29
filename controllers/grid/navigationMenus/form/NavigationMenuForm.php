<?php

/**
 * @file controllers/grid/navigationMenus/form/NavigationMenuForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NavigationMenuForm
 * @ingroup controllers_grid_navigationMenus_form
 *
 * @see NavigationMenu
 *
 * @brief Form for manager to create/edit NavigationMenus.
 */

namespace PKP\controllers\grid\navigationMenus\form;

use APP\core\Services;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\navigationMenu\NavigationMenuItemAssignment;
use PKP\plugins\PluginRegistry;

class NavigationMenuForm extends Form
{
    /** @var int Context ID */
    public $_contextId;

    /** @var int $_navigationMenuId The menu id being edited */
    public $_navigationMenuId;

    /**
     * Constructor
     *
     * @param int $contextId Context ID
     * @param int $navigationMenuId NavigationMenu Id
     */
    public function __construct($contextId, $navigationMenuId = null)
    {
        $this->_navigationMenuId = !empty($navigationMenuId) ? (int) $navigationMenuId : null;
        $this->_contextId = $contextId;

        parent::__construct('controllers/grid/navigationMenus/form/navigationMenuForm.tpl');

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'title', 'required', 'manager.navigationMenus.form.titleRequired'));

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Get a list of localized field names for this form
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        return null;
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);

        $themePlugins = PluginRegistry::getPlugins('themes');
        if (empty($themePlugins)) {
            $themePlugins = PluginRegistry::loadCategory('themes', true);
        }

        $activeThemeNavigationAreas = [];

        $activeThemeNavigationAreas = ['' => __('common.none')];

        foreach ($themePlugins as $themePlugin) {
            if ($themePlugin->isActive()) {
                $areas = $themePlugin->getMenuAreas();
                foreach ($areas as $area) {
                    $activeThemeNavigationAreas[$area] = $area;
                }
            }
        }

        $context = $request->getContext();
        $contextId = \PKP\core\PKPApplication::CONTEXT_ID_NONE;
        if ($context) {
            $contextId = $context->getId();
        }

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO'); /** @var NavigationMenuItemDAO $navigationMenuItemDao */
        $navigationMenuItems = $navigationMenuItemDao->getByContextId($contextId)
            ->toArray();
        $assignedItems = $navigationMenuItemDao->getByMenuId($this->_navigationMenuId)
            ->toArray();
        $unassignedItems = array_udiff($navigationMenuItems, $assignedItems, function ($a, $b) {
            return $a->getId() - $b->getId();
        });

        foreach ($unassignedItems as $unassignedItem) {
            Services::get('navigationMenu')->transformNavMenuItemTitle($templateMgr, $unassignedItem);
        }

        $navigationMenuItemTypes = Services::get('navigationMenu')->getMenuItemTypes();

        $typeConditionalWarnings = [];
        foreach ($navigationMenuItemTypes as $type => $settings) {
            if (array_key_exists('conditionalWarning', $settings)) {
                $typeConditionalWarnings[$type] = $settings['conditionalWarning'];
            }
        }

        $templateMgr->assign([
            'activeThemeNavigationAreas' => $activeThemeNavigationAreas,
            'unassignedItems' => $unassignedItems,
            'navigationMenuId' => $this->_navigationMenuId,
            'title' => $this->getData('title'),
            'navigationMenuArea' => $this->getData('areaName'),
            'menuTree' => $this->getData('menuTree'),
            'navigationMenuItemTypes' => $navigationMenuItemTypes,
            'navigationMenuItemTypeConditionalWarnings' => json_encode($typeConditionalWarnings),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Initialize form data from current NavigationMenu.
     */
    public function initData()
    {
        if (isset($this->_navigationMenuId) && $this->_navigationMenuId != 0) {
            $navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenusDao */
            $navigationMenu = $navigationMenusDao->getById($this->_navigationMenuId);

            if ($navigationMenu != null) {
                Services::get('navigationMenu')->getMenuTree($navigationMenu);

                $this->_data = [
                    'title' => $navigationMenu->getTitle(),
                    'navigationMenuId' => $navigationMenu->getId(),
                    'navigationMenuArea' => $navigationMenu->getAreaName(),
                    'menuTree' => $navigationMenu->menuTree,
                ];
            } else {
                $this->_navigationMenuId = null;
            }
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['title', 'navigationMenuId', 'areaName', 'menuTree']);
    }

    /**
     * Save NavigationMenu .
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        $navigationMenusDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenusDao */
        $navigationMenuItemAssignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO'); /** @var NavigationMenuItemAssignmentDAO $navigationMenuItemAssignmentDao */

        if (isset($this->_navigationMenuId)) {
            $navigationMenu = $navigationMenusDao->getById($this->_navigationMenuId);
        }

        if (!isset($navigationMenu)) {
            $navigationMenu = $navigationMenusDao->newDataObject();
        }

        $navigationMenu->setContextId($this->_contextId);
        $navigationMenu->setTitle($this->getData('title'));
        $navigationMenu->setAreaName($this->getData('areaName'));

        // Update or insert NavigationMenu
        if ($navigationMenu->getId() != null) {
            $navigationMenusDao->updateObject($navigationMenu);
        } else {
            $this->_navigationMenuId = $navigationMenusDao->insertObject($navigationMenu);
        }

        // Update NavigationMenuItemAssignment
        if ($this->_navigationMenuId) {
            $navigationMenuItemAssignmentDao->deleteByMenuId($this->_navigationMenuId);
            $menuTree = $this->getData('menuTree');
            if (!empty($menuTree)) {
                foreach ($menuTree as $menuItemId => $assignmentData) {
                    $assignment = new NavigationMenuItemAssignment();
                    $assignment->setMenuId($this->_navigationMenuId);
                    $assignment->setMenuItemId((int) $menuItemId);
                    $assignment->setSequence((int) $assignmentData['seq']);
                    if (isset($assignmentData['parentId'])) {
                        $assignment->setParentId((int) $assignmentData['parentId']);
                    }
                    $navigationMenuItemAssignmentDao->insertObject($assignment);
                }
            }
        }
    }

    /**
     * Perform additional validation checks
     *
     * @copydoc Form::validate
     */
    public function validate($callHooks = true)
    {
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */

        $navigationMenu = $navigationMenuDao->getByTitle($this->_contextId, $this->getData('title'));
        if (isset($navigationMenu) && $navigationMenu->getId() != $this->_navigationMenuId) {
            $this->addError('path', __('manager.navigationMenus.form.duplicateTitle'));
        }

        if ($this->getData('areaName') != '') {
            $navigationMenusWithArea = $navigationMenuDao->getByArea($this->_contextId, $this->getData('areaName'))->toArray();
            if (count($navigationMenusWithArea) == 1 && $navigationMenusWithArea[0]->getId() != $this->_navigationMenuId) {
                $this->addError('areaName', __('manager.navigationMenus.form.menuAssigned'));
            }
        }

        return parent::validate($callHooks);
    }
}

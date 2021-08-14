<?php

/**
 * @file controllers/grid/settings/sections/form/PKPSectionForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSectionForm
 * @ingroup controllers_grid_settings_section_form
 *
 * @brief Form for adding/editing a section
 */

use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\security\Role;

class PKPSectionForm extends Form
{
    /** @var int the id for the section being edited */
    public $_sectionId;

    /** @var int The current user ID */
    public $_userId;

    /** @var string Cover image extension */
    public $_imageExtension;

    /** @var array Cover image information from getimagesize */
    public $_sizeArray;

    /**
     * Constructor.
     *
     * @param PKPRequest $request
     * @param string $template Template path
     * @param int $sectionId optional
     */
    public function __construct($request, $template, $sectionId = null)
    {
        $this->setSectionId($sectionId);

        $user = $request->getUser();
        $this->_userId = $user->getId();

        parent::__construct($template);

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['title', 'subEditors']);
    }

    /**
     * Get the section ID for this section.
     *
     * @return int
     */
    public function getSectionId()
    {
        return $this->_sectionId;
    }

    /**
     * Set the section ID for this section.
     *
     * @param int $sectionId
     */
    public function setSectionId($sectionId)
    {
        $this->_sectionId = $sectionId;
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $collector = Repo::user()->getCollector()
            ->filterByRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->filterByContextIds([$request->getContext()->getId()]);
        $usersIterator = Repo::user()->getMany($collector);
        $subeditors = [];
        foreach ($usersIterator as $user) {
            $subeditors[(int) $user->getId()] = $user->getFullName();
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'subeditors' => $subeditors,
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Save changes to subeditors
     *
     */
    public function execute(...$functionArgs)
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteBySubmissionGroupId($this->getSectionId(), ASSOC_TYPE_SECTION, $contextId);
        $subEditors = $this->getData('subEditors');
        if (!empty($subEditors)) {
            $roleDao = DAORegistry::getDAO('RoleDAO'); /** @var RoleDAO $roleDao */
            foreach ($subEditors as $subEditor) {
                if ($roleDao->userHasRole($contextId, $subEditor, Role::ROLE_ID_SUB_EDITOR)) {
                    $subEditorsDao->insertEditor($contextId, $this->getSectionId(), $subEditor, ASSOC_TYPE_SECTION);
                }
            }
        }

        parent::execute($functionArgs);
    }
}

<?php

/**
 * @file controllers/grid/settings/sections/form/PKPSectionForm.php
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

namespace PKP\controllers\grid\settings\sections\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\section\Section;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\security\Role;
use PKP\userGroup\UserGroup;

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

    public ?Section $section = null;

    /** @var array Roles that can be assigned to this section */
    public $assignableRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];

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

    public function getSection(): ?Section
    {
        return $this->section;
    }

    public function setSection(Section $section): void
    {
        $this->section = $section;
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $assignableUserGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$request->getContext()->getId()])
            ->filterByRoleIds($this->assignableRoles)
            ->filterByStageIds([WORKFLOW_STAGE_ID_SUBMISSION])
            ->getMany()
            ->map(function (UserGroup $userGroup) use ($request) {
                return [
                    'userGroup' => $userGroup,
                    'users' => Repo::user()
                        ->getCollector()
                        ->filterByUserGroupIds([$userGroup->getId()])
                        ->filterByContextIds([$request->getContext()->getId()])
                        ->getMany()
                        ->mapWithKeys(fn ($user, $key) => [$user->getId() => $user->getFullName()])
                        ->toArray()
                ];
            });

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'assignableUserGroups' => $assignableUserGroups->toArray(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    public function initData()
    {
        if ($this->getSection() !== null) {
            $this->setData([
                'assignedSubeditors' => Repo::user()
                    ->getCollector()
                    ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
                    ->filterByRoleIds($this->assignableRoles)
                    ->assignedToSectionIds([$this->getSectionId()])
                    ->getIds()
                    ->toArray(),
            ]);
        } else {
            $this->setData([
                'assignedSubeditors' => [],
            ]);
        }

        parent::initData();
    }

    /**
     * Save changes to subeditors
     *
     */
    public function execute(...$functionArgs)
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $subEditorsDao->deleteBySubmissionGroupId($this->getSectionId(), Application::ASSOC_TYPE_SECTION, $contextId);
        $subEditors = $this->getData('subEditors');
        if (!empty($subEditors)) {
            $allowedEditors = Repo::user()
                ->getCollector()
                ->filterByRoleIds($this->assignableRoles)
                ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
                ->getIds();
            foreach ($subEditors as $userGroupId => $userIds) {
                foreach ($userIds as $userId) {
                    if (!$allowedEditors->contains($userId)) {
                        continue;
                    }
                    $subEditorsDao->insertEditor($contextId, $this->getSectionId(), $userId, Application::ASSOC_TYPE_SECTION, (int) $userGroupId);
                }
            }
        }

        parent::execute($functionArgs);
    }
}

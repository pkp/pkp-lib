<?php

/**
 * @file controllers/grid/settings/contributorRoles/form/ContributorRoleForm.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRoleForm
 *
 * @ingroup controllers_grid_settings_contributorRole_form
 *
 * @brief Form for adding/editing contributor roles.
 */

namespace PKP\controllers\grid\settings\contributorRoles\form;

use PKP\author\contributorRole\ContributorRoleIdentifier;
use APP\core\Application;
use APP\template\TemplateManager;
use PKP\facades\Repo;
use PKP\form\Form;
use PKP\security\Validation;

class ContributorRoleForm extends Form
{
    public ?int $_roleId;

    /**
     * Set the contributor role id
     */
    public function setRoleId(?int $roleId): void
    {
        $this->_roleId = $roleId;
    }

    /**
     * Get the contributor role id
     */
    public function getRoleId(): ?int
    {
        return $this->_roleId;
    }

    /**
     * Constructor.
     */
    public function __construct(int $roleId = null)
    {
        $this->setRoleId($roleId);
        parent::__construct('controllers/grid/settings/contributorRoles/form/contributorRoleForm.tpl');

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorCustom($this, 'name', 'required', 'manager.setup.contributorRoles.nameRequired', fn (array $name) => count($name) === count(array_filter($name))));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Initialize form data from current settings.
     */
    public function initData(array $args = []): void
    {
        $identifierNames = null;
        if ($this->getRoleId()) {
            $identifierNames = Repo::contributorRole()->getByRoleId($this->getRoleId());
        }

        if ($identifierNames) {
            $identifier = array_key_first($identifierNames);
            $this->_data = [
                'identifier' => $identifier,
                'name' => $identifierNames[$identifier],
            ];
        } else {
            $this->_data = [
                'name' => [],
            ];
        }

        // grid related data
        $this->_data['gridId'] = $args['gridId'];
        $this->_data['rowId'] = $args['rowId'] ?? null;
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('contributorRoles', collect(ContributorRoleIdentifier::getRoles())
            ->diff(array_keys(Repo::contributorRole()->getByContextId($request->getContext()->getId())))
            ->mapWithKeys(fn (string $identifier) => [$identifier => $identifier])
            ->toArray()
        );
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['identifier', 'name', 'gridId', 'rowId']);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return bool
     */
    public function execute(...$functionArgs)
    {
        // Edit or add contributor role
        $roleId = Repo::contributorRole()->add(
            $this->getData('name'),
            $this->getData('identifier'),
            Application::get()->getRequest()->getContext()->getId(),
            $this->getData('rowId')
        );

        if (!$this->getRoleId()) {
            $this->setRoleId($roleId);
        }

        parent::execute(...$functionArgs);
        return true;
    }
}

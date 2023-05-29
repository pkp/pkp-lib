<?php

/**
 * @file controllers/grid/announcements/form/AnnouncementTypeForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementTypeForm
 *
 * @ingroup controllers_grid_announcements_form
 *
 * @see AnnouncementType
 *
 * @brief Form for manager to create/edit announcement types.
 */

namespace PKP\controllers\grid\announcements\form;

use APP\template\TemplateManager;
use PKP\announcement\AnnouncementTypeDAO;
use PKP\db\DAORegistry;
use PKP\form\Form;

class AnnouncementTypeForm extends Form
{
    /** @var int Context ID */
    public $contextId;

    /** @var int The ID of the announcement type being edited */
    public $typeId;

    /**
     * Constructor
     *
     * @param int $contextId Context ID
     * @param int $typeId leave as default for new announcement type
     */
    public function __construct($contextId, $typeId = null)
    {
        $this->typeId = isset($typeId) ? (int) $typeId : null;
        $this->contextId = $contextId;

        parent::__construct('manager/announcement/announcementTypeForm.tpl');

        // Type name is provided
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'name', 'required', 'manager.announcementTypes.form.typeNameRequired'));

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
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
        return $announcementTypeDao->getLocaleFieldNames();
    }

    /**
     * @copydoc Form::fetch()
     */
    public function fetch($request, $template = 'controllers/grid/announcements/form/announcementTypeForm.tpl', $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('typeId', $this->typeId);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Initialize form data from current announcement type.
     */
    public function initData()
    {
        if (isset($this->typeId)) {
            $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */
            $announcementType = $announcementTypeDao->getById($this->typeId);

            if ($announcementType != null) {
                $this->_data = [
                    'name' => $announcementType->getName(null) // Localized
                ];
            } else {
                $this->typeId = null;
            }
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['name']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $announcementTypeDao = DAORegistry::getDAO('AnnouncementTypeDAO'); /** @var AnnouncementTypeDAO $announcementTypeDao */

        if (isset($this->typeId)) {
            $announcementType = $announcementTypeDao->getById($this->typeId);
        }

        if (!isset($announcementType)) {
            $announcementType = $announcementTypeDao->newDataObject();
        }

        $announcementType->setContextId($this->contextId);
        $announcementType->setName($this->getData('name'), null); // Localized

        // Update or insert announcement type
        if ($announcementType->getId() != null) {
            $announcementTypeDao->updateObject($announcementType);
        } else {
            $announcementTypeDao->insertObject($announcementType);
        }
        parent::execute(...$functionArgs);
    }
}

<?php

/**
 * @file controllers/grid/settings/reviewForms/form/ReviewFormForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormForm
 * @ingroup controllers_grid_settings_reviewForms_form
 *
 * @brief Form for manager to edit a review form.
 */

namespace PKP\controllers\grid\settings\reviewForms\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\security\Validation;

class ReviewFormForm extends Form
{
    /** @var int The ID of the review form being edited, if any */
    public $reviewFormId;

    /**
     * Constructor.
     *
     * @param int $reviewFormId omit for a new review form
     */
    public function __construct($reviewFormId = null)
    {
        parent::__construct('manager/reviewForms/reviewFormForm.tpl');
        $this->reviewFormId = $reviewFormId ? (int) $reviewFormId : null;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'title', 'required', 'manager.reviewForms.form.titleRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['title', 'description']);
    }

    /**
     * Initialize form data from current settings.
     */
    public function initData()
    {
        if ($this->reviewFormId) {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewForm = $reviewFormDao->getById($this->reviewFormId, Application::getContextAssocType(), $context->getId());

            $this->setData('title', $reviewForm->getTitle(null));
            $this->setData('description', $reviewForm->getDescription(null));
        }
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $json = new JSONMessage();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('reviewFormId', $this->reviewFormId);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */

        if ($this->reviewFormId) {
            $reviewForm = $reviewFormDao->getById($this->reviewFormId, Application::getContextAssocType(), $context->getId());
        } else {
            $reviewForm = $reviewFormDao->newDataObject();
            $reviewForm->setAssocType(Application::getContextAssocType());
            $reviewForm->setAssocId($context->getId());
            $reviewForm->setActive(0);
            $reviewForm->setSequence(REALLY_BIG_NUMBER);
        }

        $reviewForm->setTitle($this->getData('title'), null); // Localized
        $reviewForm->setDescription($this->getData('description'), null); // Localized

        if ($this->reviewFormId) {
            $reviewFormDao->updateObject($reviewForm);
            $this->reviewFormId = $reviewForm->getId();
        } else {
            $this->reviewFormId = $reviewFormDao->insertObject($reviewForm);
            $reviewFormDao->resequenceReviewForms(Application::getContextAssocType(), $context->getId());
        }
        parent::execute(...$functionArgs);
    }

    /**
     * Get a list of field names for which localized settings are used
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
        return $reviewFormDao->getLocaleFieldNames();
    }
}

<?php
/**
 * @file controllers/grid/settings/reviewForms/form/PKPPreviewReviewForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreviewReviewForm
 *
 * @ingroup controllers_grid_settings_reviewForms_form
 *
 * @brief Form for manager to preview review form.
 */

namespace PKP\controllers\grid\settings\reviewForms\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\form\Form;

use PKP\security\Validation;

class PreviewReviewForm extends Form
{
    /** @var int The ID of the review form being edited */
    public $reviewFormId;

    /**
     * Constructor.
     *
     * @param int $reviewFormId omit for a new review form
     */
    public function __construct($reviewFormId = null)
    {
        parent::__construct('manager/reviewForms/previewReviewForm.tpl');

        $this->reviewFormId = (int) $reviewFormId;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
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
     * Initialize form data from current settings.
     */
    public function initData()
    {
        if ($this->reviewFormId) {
            // Get review form
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewForm = $reviewFormDao->getById($this->reviewFormId, Application::getContextAssocType(), $context->getId()); /** @var ReviewForm $reviewForm  */

            // Get review form elements
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($this->reviewFormId);

            // Set data
            $this->setData('title', $reviewForm->getLocalizedTitle(null));
            $this->setData('description', $reviewForm->getLocalizedDescription(null));
            $this->setData('reviewFormElements', $reviewFormElements);
        }
    }
}

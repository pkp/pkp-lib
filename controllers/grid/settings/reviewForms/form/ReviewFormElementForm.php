<?php

/**
 * @file classes/manager/form/ReviewFormElementForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewFormElementForm
 * @ingroup controllers_grid_settings_reviewForms_form
 *
 * @see ReviewFormElement
 *
 * @brief Form for creating and modifying review form elements.
 *
 */

namespace classes\manager\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\form\Form;

use PKP\reviewForm\ReviewFormElement;
use PKP\security\Validation;

class ReviewFormElementForm extends Form
{
    /** @var int $reviewFormId The ID of the review form being edited */
    public $reviewFormId;

    /** @var int $reviewFormElementId The ID of the review form element being edited */
    public $reviewFormElementId;

    /**
     * Constructor.
     *
     * @param int $reviewFormId
     * @param int $reviewFormElementId
     */
    public function __construct($reviewFormId, $reviewFormElementId = null)
    {
        parent::__construct('manager/reviewForms/reviewFormElementForm.tpl');

        $this->reviewFormId = $reviewFormId;
        $this->reviewFormElementId = $reviewFormElementId;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorLocale($this, 'question', 'required', 'manager.reviewFormElements.form.questionRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'elementType', 'required', 'manager.reviewFormElements.form.elementTypeRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Get the names of fields for which localized data is allowed.
     *
     * @return array
     */
    public function getLocaleFieldNames()
    {
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        return $reviewFormElementDao->getLocaleFieldNames();
    }

    /**
     * @copydoc Form::fetch
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $reviewFormElement = new ReviewFormElement();
        $templateMgr->assign([
            'reviewFormId' => $this->reviewFormId,
            'reviewFormElementId' => $this->reviewFormElementId,
            'multipleResponsesElementTypes' => $reviewFormElement->getMultipleResponsesElementTypes(),
            'multipleResponsesElementTypesString' => ';' . implode(';', $reviewFormElement->getMultipleResponsesElementTypes()) . ';',
            'reviewFormElementTypeOptions' => $reviewFormElement->getReviewFormElementTypeOptions(),
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Initialize form data from current review form.
     */
    public function initData()
    {
        if ($this->reviewFormElementId) {
            $request = Application::get()->getRequest();
            $context = $request->getContext();
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormElement = $reviewFormElementDao->getById($this->reviewFormElementId, $this->reviewFormId);
            $this->_data = [
                'question' => $reviewFormElement->getQuestion(null), // Localized
                'description' => $reviewFormElement->getDescription(null), // Localized
                'required' => $reviewFormElement->getRequired(),
                'included' => $reviewFormElement->getIncluded(),

                'elementType' => $reviewFormElement->getElementType(),
                'possibleResponses' => $reviewFormElement->getPossibleResponses(null) //Localized
            ];
        } else {
            $this->_data = [
                'included' => 1
            ];
        }
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData()
    {
        $this->readUserVars(['question', 'description', 'required', 'included', 'elementType', 'possibleResponses']);
    }

    /**
     * @copydoc Form::execute()
     *
     * @return int Review form element ID
     */
    public function execute(...$functionArgs)
    {
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $request = Application::get()->getRequest();

        if ($this->reviewFormElementId) {
            $context = $request->getContext();
            $reviewFormElement = $reviewFormElementDao->getById($this->reviewFormElementId);
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewForm = $reviewFormDao->getById($reviewFormElement->getReviewFormId(), Application::getContextAssocType(), $context->getId());
            if (!$reviewForm) {
                fatalError('Invalid review form element ID!');
            }
        } else {
            $reviewFormElement = $reviewFormElementDao->newDataObject();
            $reviewFormElement->setReviewFormId($this->reviewFormId);
            $reviewFormElement->setSequence(REALLY_BIG_NUMBER);
        }

        $reviewFormElement->setQuestion($this->getData('question'), null); // Localized
        $reviewFormElement->setDescription($this->getData('description'), null); // Localized
        $reviewFormElement->setRequired($this->getData('required') ? 1 : 0);
        $reviewFormElement->setIncluded($this->getData('included') ? 1 : 0);
        $reviewFormElement->setElementType($this->getData('elementType'));

        if (in_array($this->getData('elementType'), $reviewFormElement->getMultipleResponsesElementTypes())) {
            $this->setData('possibleResponsesProcessed', $reviewFormElement->getPossibleResponses(null));
            ListbuilderHandler::unpack($request, $this->getData('possibleResponses'), [$this, 'deleteEntry'], [$this, 'insertEntry'], [$this, 'updateEntry']);
            $reviewFormElement->setPossibleResponses($this->getData('possibleResponsesProcessed'), null);
        } else {
            $reviewFormElement->setPossibleResponses(null, null);
        }
        if ($reviewFormElement->getId()) {
            $reviewFormElementDao->deleteSetting($reviewFormElement->getId(), 'possibleResponses');
            $reviewFormElementDao->updateObject($reviewFormElement);
        } else {
            $this->reviewFormElementId = $reviewFormElementDao->insertObject($reviewFormElement);
            $reviewFormElementDao->resequenceReviewFormElements($this->reviewFormId);
        }
        parent::execute(...$functionArgs);
        return $this->reviewFormElementId;
    }

    /**
     * @copydoc ListbuilderHandler::insertEntry()
     */
    public function insertEntry($request, $newRowId)
    {
        $possibleResponsesProcessed = (array) $this->getData('possibleResponsesProcessed');
        foreach ($newRowId['possibleResponse'] as $key => $value) {
            $possibleResponsesProcessed[$key][] = $value;
        }
        $this->setData('possibleResponsesProcessed', $possibleResponsesProcessed);
        return true;
    }

    /**
     * @copydoc ListbuilderHandler::deleteEntry()
     */
    public function deleteEntry($request, $rowId)
    {
        $possibleResponsesProcessed = (array) $this->getData('possibleResponsesProcessed');
        foreach (array_keys($possibleResponsesProcessed) as $locale) {
            // WARNING: Listbuilders don't like zero row IDs. They are offset
            // by 1 to avoid this case, so 1 is subtracted here to normalize.
            unset($possibleResponsesProcessed[$locale][$rowId - 1]);
        }
        $this->setData('possibleResponsesProcessed', $possibleResponsesProcessed);
        return true;
    }

    /**
     * @copydoc ListbuilderHandler::updateEntry
     */
    public function updateEntry($request, $rowId, $newRowId)
    {
        $possibleResponsesProcessed = (array) $this->getData('possibleResponsesProcessed');
        foreach ($newRowId['possibleResponse'] as $locale => $value) {
            // WARNING: Listbuilders don't like zero row IDs. They are offset
            // by 1 to avoid this case, so 1 is subtracted here to normalize.
            $possibleResponsesProcessed[$locale][$rowId - 1] = $value;
        }
        $this->setData('possibleResponsesProcessed', $possibleResponsesProcessed);
        return true;
    }
}

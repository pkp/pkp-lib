<?php

/**
 * @file controllers/tab/pubIds/form/PKPPublicIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicIdentifiersForm
 * @ingroup controllers_tab_pubIds_form
 *
 * @brief Displays a pub ids form.
 */

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\form\Form;

use PKP\plugins\PKPPubIdPluginHelper;
use PKP\plugins\PluginRegistry;
use PKP\submission\Representation;
use PKP\submissionFile\SubmissionFile;

class PKPPublicIdentifiersForm extends Form
{
    /** @var int The context id */
    public $_contextId;

    /** @var object The pub object the identifiers are edited of
     * 	Submission, Representation, SubmissionFile, OJS Issue and OMP Chapter
     */
    public $_pubObject;

    /** @var int The current stage id, WORKFLOW_STAGE_ID_ */
    public $_stageId;

    /**
     * @var array Parameters to configure the form template.
     */
    public $_formParams;

    /**
     * Constructor.
     *
     * @param object $pubObject
     * @param int $stageId
     * @param array $formParams
     */
    public function __construct($pubObject, $stageId = null, $formParams = null)
    {
        parent::__construct('controllers/tab/pubIds/form/publicIdentifiersForm.tpl');

        $this->_pubObject = $pubObject;
        $this->_stageId = $stageId;
        $this->_formParams = $formParams;

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $this->_contextId = $context->getId();

        Locale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));

        // action links for pub id reset requests
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->setLinkActions($this->getContextId(), $this, $pubObject);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true, $this->getContextId()),
            'pubObject' => $this->getPubObject(),
            'stageId' => $this->getStageId(),
            'formParams' => $this->getFormParams(),
        ]);
        if ($this->getPubObject() instanceof Representation || $this->getPubObject() instanceof \APP\monograph\Chapter) {
            $publicationId = $this->getPubObject()->getData('publicationId');
            $publication = Repo::publication()->get($publicationId);
            $templateMgr->assign([
                'submissionId' => $publication->getData('submissionId'),
            ]);
        }
        // consider JavaScripts
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->addJavaScripts($this->getContextId(), $request, $templateMgr);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $pubObject = $this->getPubObject();
        $this->setData('publisherId', $pubObject->getStoredPubId('publisher-id'));
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->init($this->getContextId(), $this, $pubObject);
        return parent::initData();
    }


    //
    // Getters
    //
    /**
     * Get the pub object
     *
     * @return object
     */
    public function getPubObject()
    {
        return $this->_pubObject;
    }

    /**
     * Get the stage id
     *
     * @return int WORKFLOW_STAGE_ID_
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get the context id
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->_contextId;
    }

    /**
     * Get the extra form parameters.
     *
     * @return array
     */
    public function getFormParams()
    {
        return $this->_formParams;
    }


    //
    // Form methods
    //
    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['publisherId']);
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->readInputData($this->getContextId(), $this);
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $pubObject = $this->getPubObject();
        $assocType = $this->getAssocType($pubObject);
        $publisherId = $this->getData('publisherId');
        $pubObjectId = $pubObject->getId();
        if ($assocType == ASSOC_TYPE_SUBMISSION_FILE) {
            $pubObjectId = $pubObject->getId();
        }
        $contextDao = Application::getContextDAO();
        if ($publisherId) {
            if (ctype_digit((string) $publisherId)) {
                $this->addError('publisherId', __('editor.publicIdentificationNumericNotAllowed', ['publicIdentifier' => $publisherId]));
                $this->addErrorField('$publisherId');
            } elseif (count(explode('/', $publisherId)) > 1) {
                $this->addError('publisherId', __('editor.publicIdentificationPatternNotAllowed', ['pattern' => '"/"']));
                $this->addErrorField('$publisherId');
            } elseif ($pubObject instanceof SubmissionFile && preg_match('/^(\d+)-(\d+)$/', $publisherId)) {
                $this->addError('publisherId', __('editor.publicIdentificationPatternNotAllowed', ['pattern' => '\'/^(\d+)-(\d+)$/\' i.e. \'number-number\'']));
                $this->addErrorField('$publisherId');
            } elseif ($contextDao->anyPubIdExists($this->getContextId(), 'publisher-id', $publisherId, $assocType, $pubObjectId, true)) {
                $this->addError('publisherId', __('editor.publicIdentificationExistsForTheSameType', ['publicIdentifier' => $publisherId]));
                $this->addErrorField('$publisherId');
            }
        }
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->validate($this->getContextId(), $this, $this->getPubObject());
        return parent::validate($callHooks);
    }

    /**
     * Store objects with pub ids.
     *
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $pubObject = $this->getPubObject();
        $pubObject->setStoredPubId('publisher-id', $this->getData('publisherId'));

        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->execute($this->getContextId(), $this, $pubObject);

        if ($pubObject instanceof Representation) {
            $representationDao = Application::getRepresentationDAO();
            $representationDao->updateObject($pubObject);

            return;
        }

        if ($pubObject instanceof SubmissionFile) {
            Repo::submissionFile()->edit($pubObject, []);

            return;
        }
    }

    /**
     * Clear pub id.
     *
     * @param string $pubIdPlugInClassName
     */
    public function clearPubId($pubIdPlugInClassName)
    {
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->clearPubId($this->getContextId(), $pubIdPlugInClassName, $this->getPubObject());
    }

    /**
     * Get assoc type of the given object.
     *
     * @param object $pubObject
     *
     * @return int ASSOC_TYPE_
     */
    public function getAssocType($pubObject)
    {
        if ($pubObject instanceof Submission) {
            return ASSOC_TYPE_SUBMISSION;
        }

        if ($pubObject instanceof Publication) {
            return ASSOC_TYPE_PUBLICATION;
        }

        if ($pubObject instanceof Representation) {
            return ASSOC_TYPE_REPRESENTATION;
        }

        if ($pubObject instanceof SubmissionFile) {
            return ASSOC_TYPE_SUBMISSION_FILE;
        }

        return null;
    }
}

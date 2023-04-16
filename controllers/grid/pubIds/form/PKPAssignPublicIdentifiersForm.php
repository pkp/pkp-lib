<?php

/**
 * @file controllers/grid/pubIds/form/PKPAssignPublicIdentifiersForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAssignPublicIdentifiersForm
 *
 * @ingroup controllers_grid_pubIds_form
 *
 * @brief Displays the assign pub id form.
 */

namespace PKP\controllers\grid\pubIds\form;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\plugins\PKPPubIdPluginHelper;

use PKP\plugins\PluginRegistry;

class PKPAssignPublicIdentifiersForm extends Form
{
    /** @var int The context id */
    public $_contextId;

    /** @var object The pub object, that are being approved,
     * the pub ids can be considered for assignment there
     * OJS Issue, Representation or SubmissionFile
     */
    public $_pubObject;

    /** @var bool */
    public $_approval;

    /**
     * @var string Confirmation to display.
     */
    public $_confirmationText;

    /**
     * Constructor.
     *
     * @param string $template Form template
     * @param object $pubObject
     * @param bool $approval
     * @param string $confirmationText
     */
    public function __construct($template, $pubObject, $approval, $confirmationText)
    {
        parent::__construct($template);

        $this->_pubObject = $pubObject;
        $this->_approval = $approval;
        $this->_confirmationText = $confirmationText;

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $this->_contextId = $context->getId();

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $this->getContextId());
        $templateMgr->assign([
            'pubIdPlugins' => $pubIdPlugins,
            'pubObject' => $this->getPubObject(),
            'approval' => $this->getApproval(),
            'confirmationText' => $this->getConfirmationText(),
        ]);
        if ($request->getUserVar('submissionId')) {
            $templateMgr->assign('submissionId', $request->getUserVar('submissionId'));
        }
        if ($request->getUserVar('publicationId')) {
            $templateMgr->assign('publicationId', $request->getUserVar('publicationId'));
        }
        return parent::fetch($request, $template, $display);
    }


    //
    // Getters and Setters
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
     * Get weather it is an approval
     *
     * @return bool
     */
    public function getApproval()
    {
        return $this->_approval;
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
     * Get the confirmation text.
     *
     * @return string
     */
    public function getConfirmationText()
    {
        return $this->_confirmationText;
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->readAssignInputData($this);
    }

    /**
     * Assign pub ids.
     *
     * @param bool $save
     *  true if the pub id shall be saved here
     *  false if this form is integrated somewhere else, where the pub object will be updated.
     */
    public function execute($save = false, ...$functionArgs)
    {
        parent::execute($save, ...$functionArgs);

        $pubObject = $this->getPubObject();
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->assignPubId($this->getContextId(), $this, $pubObject, $save);
    }
}

<?php

/**
 * @file classes/install/form/MaintenanceForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MaintenanceForm
 *
 * @ingroup install_form
 *
 * @brief Base form for system maintenance (install/upgrade).
 */

namespace PKP\install\form;

use APP\template\TemplateManager;
use PKP\form\Form;

use PKP\site\VersionCheck;

class MaintenanceForm extends Form
{
    /** @var PKPRequest */
    public $_request;

    /**
     * Constructor.
     */
    public function __construct($request, $template)
    {
        parent::__construct($template);
        $this->_request = $request;
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
    }

    /**
     * @copydoc Form::display
     *
     * @param null|mixed $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        $templateMgr = TemplateManager::getManager($this->_request);
        $templateMgr->assign('version', VersionCheck::getCurrentCodeVersion());
        parent::display($request, $template);
    }

    /**
     * Fail with a generic installation error.
     *
     * @param string $errorMsg
     * @param bool $translate
     */
    public function installError($errorMsg, $translate = true)
    {
        $templateMgr = TemplateManager::getManager($this->_request);
        $templateMgr->assign(['isInstallError' => true, 'errorMsg' => $errorMsg, 'translateErrorMsg' => $translate]);
        $this->display($this->_request);
    }

    /**
     * Fail with a database installation error.
     *
     * @param string $errorMsg
     */
    public function dbInstallError($errorMsg)
    {
        $templateMgr = TemplateManager::getManager($this->_request);
        $templateMgr->assign(['isInstallError' => true, 'dbErrorMsg' => empty($errorMsg) ? __('common.error.databaseErrorUnknown') : $errorMsg]);
        error_log($errorMsg);
        $this->display($this->_request);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\form\MaintenanceForm', '\MaintenanceForm');
}

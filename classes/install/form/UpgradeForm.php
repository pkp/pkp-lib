<?php

/**
 * @file classes/install/form/UpgradeForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UpgradeForm
 *
 * @ingroup install_form
 *
 * @brief Form for system upgrades.
 */

namespace PKP\install\form;

use APP\core\Application;
use APP\install\Upgrade;
use APP\template\TemplateManager;
use PKP\install\Installer;

class UpgradeForm extends MaintenanceForm
{
    /**
     * Constructor.
     */
    public function __construct($request)
    {
        parent::__construct($request, 'install/upgrade.tpl');
    }

    /**
     * Perform installation.
     */
    public function execute(...$functionParams)
    {
        parent::execute(...$functionParams);

        Application::upgrade();
        $templateMgr = TemplateManager::getManager($this->_request);
        $installer = new Upgrade($this->_data);

        // FIXME Use logger?

        // FIXME Mostly common with InstallForm

        if ($installer->execute()) {
            if (!$installer->wroteConfig()) {
                // Display config file contents for manual replacement
                $templateMgr->assign(['writeConfigFailed' => true, 'configFileContents' => $installer->getConfigContents()]);
            }

            $templateMgr->assign('notes', $installer->getNotes());
            $templateMgr->assign('newVersion', $installer->getNewVersion());
            $templateMgr->display('install/upgradeComplete.tpl');
        } else {
            switch ($installer->getErrorType()) {
                case Installer::INSTALLER_ERROR_DB:
                    $this->dbInstallError($installer->getErrorMsg());
                    break;
                default:
                    $this->installError($installer->getErrorMsg());
                    break;
            }
        }

        $installer->destroy();
    }
}

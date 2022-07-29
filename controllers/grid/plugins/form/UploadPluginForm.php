<?php

/**
 * @file controllers/grid/plugins/form/UploadPluginForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UploadPluginForm
 * @ingroup controllers_grid_plugins_form
 *
 * @brief Form to upload a plugin file.
 */

namespace PKP\controllers\grid\plugins\form;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileManager;
use PKP\form\Form;
use PKP\notification\PKPNotification;

use PKP\plugins\PluginHelper;
use PKP\plugins\PluginRegistry;

class UploadPluginForm extends Form
{
    /** @var string PLUGIN_ACTION_... */
    public $_function;


    /**
     * Constructor.
     *
     * @param string $function PLUGIN_ACTION_...
     */
    public function __construct($function)
    {
        parent::__construct('controllers/grid/plugins/form/uploadPluginForm.tpl');

        $this->_function = $function;

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'temporaryFileId', 'required', 'manager.plugins.uploadFailed'));
    }

    //
    // Implement template methods from Form.
    //
    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['temporaryFileId']);
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
            'function' => $this->_function,
            'category' => $request->getUserVar('category'),
            'plugin' => $request->getUserVar('plugin'),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $pluginHelper = new PluginHelper();
        $notificationMgr = new NotificationManager();

        // Retrieve the temporary file.
        $temporaryFileManager = new TemporaryFileManager();
        $temporaryFileDao = DAORegistry::getDAO('TemporaryFileDAO'); /** @var TemporaryFileDAO $temporaryFileDao */
        $temporaryFile = $temporaryFileDao->getTemporaryFile($this->getData('temporaryFileId'), $user->getId());

        // Extract the temporary file into a temporary location.
        try {
            $pluginDir = $pluginHelper->extractPlugin($temporaryFile->getFilePath(), $temporaryFile->getOriginalFileName());
        } catch (Exception $e) {
            $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => $e->getMessage()]);
            return false;
        } finally {
            $temporaryFileManager->deleteById($temporaryFile->getId(), $user->getId());
        }

        // Install or upgrade the extracted plugin.
        try {
            switch ($this->_function) {
                case PluginHelper::PLUGIN_ACTION_UPLOAD:
                    $pluginVersion = $pluginHelper->installPlugin($pluginDir);
                    $notificationMgr->createTrivialNotification(
                        $user->getId(),
                        PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                        ['contents' =>
                            __('manager.plugins.installSuccessful', ['versionNumber' => $pluginVersion->getVersionString(false)])]
                    );
                    break;
                case PluginHelper::PLUGIN_ACTION_UPGRADE:
                    $plugin = PluginRegistry::getPlugin($request->getUserVar('category'), $request->getUserVar('plugin'));
                    $pluginVersion = $pluginHelper->upgradePlugin(
                        $request->getUserVar('category'),
                        basename($plugin->getPluginPath()),
                        $pluginDir
                    );
                    $notificationMgr->createTrivialNotification(
                        $user->getId(),
                        PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                        ['contents' => __('manager.plugins.upgradeSuccessful', ['versionString' => $pluginVersion->getVersionString(false)])]
                    );
                    break;
                default: assert(false); // Illegal PLUGIN_ACTION_...
            }
        } catch (Exception $e) {
            $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => $e->getMessage()]);
            $temporaryFileManager->rmtree($pluginDir);
            return false;
        }
        return true;
    }
}

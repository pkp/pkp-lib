<?php

/**
 * @file controllers/grid/plugins/form/UploadPluginForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UploadPluginForm
 *
 * @ingroup controllers_grid_plugins_form
 *
 * @brief Form to upload a plugin file.
 */

namespace PKP\controllers\grid\plugins\form;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Exception;
use PKP\db\DAORegistry;
use PKP\file\TemporaryFileDAO;
use PKP\file\TemporaryFileManager;
use PKP\form\Form;
use PKP\notification\PKPNotification;

use PKP\plugins\PluginHelper;
use PKP\plugins\PluginRegistry;

class UploadPluginForm extends Form
{
    /**
     * Constructor.
     *
     * @param string $pluginAction PLUGIN_ACTION_...
     */
    public function __construct(private $pluginAction)
    {
        parent::__construct('controllers/grid/plugins/form/uploadPluginForm.tpl');
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
            'function' => $this->pluginAction,
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

        try {
            if (!$temporaryFile) {
                throw new Exception('The uploaded plugin file was not found');
            }
            switch ($this->pluginAction) {
                case PluginHelper::PLUGIN_ACTION_UPLOAD:
                    $pluginVersion = $pluginHelper->installPlugin($temporaryFile->getFilePath(), $temporaryFile->getOriginalFileName());
                    $notificationMgr->createTrivialNotification(
                        $user->getId(),
                        PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                        ['contents' => __('manager.plugins.installSuccessful', ['versionNumber' => $pluginVersion->getVersionString(false)])]
                    );
                    break;
                case PluginHelper::PLUGIN_ACTION_UPGRADE:
                    $plugin = PluginRegistry::getPlugin($request->getUserVar('category'), $request->getUserVar('plugin'));
                    $pluginVersion = $pluginHelper->upgradePlugin(
                        $request->getUserVar('category'),
                        basename($plugin->getPluginPath()),
                        $temporaryFile->getFilePath(),
                        $temporaryFile->getOriginalFileName()
                    );
                    $notificationMgr->createTrivialNotification(
                        $user->getId(),
                        PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                        ['contents' => __('manager.plugins.upgradeSuccessful', ['versionString' => $pluginVersion->getVersionString(false)])]
                    );
                    break;
                default:
                    throw new Exception(__('common.unknownError'));
            }
        } catch (Exception $e) {
            $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => $e->getMessage()]);
        } finally {
            if ($temporaryFile) {
                $temporaryFileManager->deleteById($temporaryFile->getId(), $user->getId());
            }
        }
        return true;
    }
}

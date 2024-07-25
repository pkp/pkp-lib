<?php

/**
 * @file controllers/grid/plugins/PluginGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGridHandler
 *
 * @ingroup controllers_grid_plugins
 *
 * @brief Handle plugins grid requests.
 */

namespace PKP\controllers\grid\plugins;

use APP\notification\NotificationManager;
use Exception;
use PKP\controllers\grid\CategoryGridHandler;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\plugins\form\UploadPluginForm;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\file\TemporaryFileManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\notification\Notification;
use PKP\plugins\Plugin;
use PKP\plugins\PluginHelper;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\site\Version;
use PKP\site\VersionCheck;
use PKP\site\VersionDAO;

abstract class PluginGridHandler extends CategoryGridHandler
{
    /**
     * Constructor
     *
     * @param array $roles
     */
    public function __construct($roles)
    {
        $this->addRoleAssignment(
            $roles,
            ['enable', 'disable', 'manage', 'fetchGrid', 'fetchCategory', 'fetchRow']
        );

        $this->addRoleAssignment(
            Role::ROLE_ID_SITE_ADMIN,
            ['uploadPlugin', 'upgradePlugin', 'deletePlugin', 'saveUploadPlugin', 'uploadPluginFile']
        );

        parent::__construct();
    }


    //
    // Overridden template methods
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration
        $this->setTitle('common.plugins');

        // Set the no items row text
        $this->setEmptyRowText('grid.noItems');

        // Columns
        $pluginCellProvider = new PluginGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $pluginCellProvider,
                [
                    'showTotalItemsNumber' => true,
                    'collapseAllColumnsInCategories' => true
                ]
            )
        );

        $descriptionColumn = new GridColumn(
            'description',
            'common.description',
            null,
            null,
            $pluginCellProvider
        );
        $descriptionColumn->addFlag('html', true);
        $this->addColumn($descriptionColumn);

        $this->addColumn(
            new GridColumn(
                'enabled',
                'common.enabled',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $pluginCellProvider
            )
        );

        $router = $request->getRouter();

        // Grid level actions.
        $userRoles = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_USER_ROLES);
        if (in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            // Install plugin.
            $this->addAction(
                new LinkAction(
                    'upload',
                    new AjaxModal(
                        $router->url($request, null, null, 'uploadPlugin'),
                        __('manager.plugins.upload'),
                        'modal_add_file'
                    ),
                    __('manager.plugins.upload'),
                    'add'
                )
            );
        }
    }

    /**
     * @copydoc GridHandler::getFilterForm()
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/plugins/pluginGridFilter.tpl';
    }

    /**
     * @copydoc GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        $category = $request->getUserVar('category');
        $pluginName = $request->getUserVar('pluginName');

        if (is_null($category)) {
            $category = PluginGalleryGridHandler::PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE;
        }

        return ['category' => $category, 'pluginName' => $pluginName];
    }

    /**
     * @copydoc GridHandler::renderFilter()
     */
    public function renderFilter($request, $filterData = [])
    {
        $categoriesSymbolic = $this->loadData($request, null);
        $categories = [PluginGalleryGridHandler::PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE => __('grid.plugin.allCategories')];
        foreach ($categoriesSymbolic as $category) {
            $categories[$category] = __("plugins.categories.{$category}");
        }
        $filterData['categories'] = $categories;

        return parent::renderFilter($request, $filterData);
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowInstance()
     */
    protected function getCategoryRowInstance()
    {
        return new PluginCategoryGridRow();
    }

    /**
     * @copydoc CategoryGridHandler::loadCategoryData()
     *
     * @param null|mixed $filter
     */
    public function loadCategoryData($request, &$categoryDataElement, $filter = null)
    {
        $plugins = PluginRegistry::loadCategory($categoryDataElement);

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $fileManager = new FileManager();

        $notHiddenPlugins = [];
        foreach ((array) $plugins as $plugin) {
            if (!$plugin->getHideManagement()) {
                $notHiddenPlugins[$plugin->getName()] = $plugin;
            }
            $version = $plugin->getCurrentVersion();
            if ($version == null) { // this plugin is on the file system, but not installed.
                $versionFile = $plugin->getPluginPath() . '/version.xml';
                if ($fileManager->fileExists($versionFile)) {
                    $versionInfo = VersionCheck::parseVersionXML($versionFile);
                    $pluginVersion = $versionInfo['version'];
                } else {
                    $pluginVersion = new Version(
                        1,
                        0,
                        0,
                        0, // Major, minor, revision, build
                        Core::getCurrentDate(), // Date installed
                        1, // Current
                        'plugins.' . $plugin->getCategory(), // Type
                        basename($plugin->getPluginPath()), // Product
                        '', // Class name
                        0, // Lazy load
                        $plugin->isSitePlugin() // Site wide
                    );
                }
                $versionDao->insertVersion($pluginVersion, true);
            }
        }

        if (!is_null($filter) && isset($filter['pluginName']) && $filter['pluginName'] != '') {
            // Find all plugins that have the filter name string in their display names.
            $filteredPlugins = [];
            foreach ($notHiddenPlugins as $plugin) { /** @var Plugin $plugin */
                $pluginName = $plugin->getDisplayName();
                if (stristr($pluginName, $filter['pluginName']) !== false) {
                    $filteredPlugins[$plugin->getName()] = $plugin;
                }
            }
            ksort($filteredPlugins);
            return $filteredPlugins;
        }

        ksort($notHiddenPlugins);
        return $notHiddenPlugins;
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
     */
    public function getCategoryRowIdParameterName()
    {
        return 'category';
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $categories = PluginRegistry::getCategories();
        if (is_array($filter) && isset($filter['category']) && in_array($filter['category'], $categories)) {
            return [$filter['category'] => $filter['category']];
        } else {
            return array_combine($categories, $categories);
        }
    }


    //
    // Public handler methods.
    //
    /**
     * Manage a plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function manage($args, $request)
    {
        $plugin = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_PLUGIN); /** @var Plugin $plugin */
        return $plugin->manage($args, $request);
    }

    /**
     * Enable a plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function enable($args, $request)
    {
        $plugin = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_PLUGIN); /** @var Plugin $plugin */
        if ($request->checkCSRF() && $plugin->getCanEnable()) {
            $plugin->setEnabled(true);
            if (empty($args['disableNotification'])) {
                $user = $request->getUser();
                $notificationManager = new NotificationManager();
                $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_PLUGIN_ENABLED, ['pluginName' => $plugin->getDisplayName()]);
            }
            return \PKP\db\DAO::getDataChangedEvent($request->getUserVar('plugin'), $request->getUserVar($this->getCategoryRowIdParameterName()));
        }
        return new JSONMessage(false);
    }

    /**
     * Disable a plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function disable($args, $request)
    {
        $plugin = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_PLUGIN); /** @var Plugin $plugin */
        if ($request->checkCSRF() && $plugin->getCanDisable()) {
            $plugin->setEnabled(false);
            if (empty($args['disableNotification'])) {
                $user = $request->getUser();
                $notificationManager = new NotificationManager();
                $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_PLUGIN_DISABLED, ['pluginName' => $plugin->getDisplayName()]);
            }
            return \PKP\db\DAO::getDataChangedEvent($request->getUserVar('plugin'), $request->getUserVar($this->getCategoryRowIdParameterName()));
        }
        return new JSONMessage(false);
    }

    /**
     * Show upload plugin form to upload a new plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function uploadPlugin($args, $request)
    {
        return $this->_showUploadPluginForm(PluginHelper::PLUGIN_ACTION_UPLOAD, $request);
    }

    /**
     * Show upload plugin form to update an existing plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage
     */
    public function upgradePlugin($args, $request)
    {
        return $this->_showUploadPluginForm(PluginHelper::PLUGIN_ACTION_UPGRADE, $request);
    }

    /**
     * Upload a plugin file.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function uploadPluginFile($args, $request)
    {
        $temporaryFileManager = new TemporaryFileManager();
        $user = $request->getUser();

        // Return the temporary file id.
        if ($temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId())) {
            $json = new JSONMessage(true);
            $json->setAdditionalAttributes([
                'temporaryFileId' => $temporaryFile->getId()
            ]);
            return $json;
        } else {
            return new JSONMessage(false, __('manager.plugins.uploadError'));
        }
    }

    /**
     * Save upload plugin file form.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function saveUploadPlugin($args, $request)
    {
        if (!$request->checkCSRF()) {
            throw new Exception('CSRF mismatch!');
        }
        $function = $request->getUserVar('function');
        $uploadPluginForm = new UploadPluginForm($function);
        $uploadPluginForm->readInputData();

        if ($uploadPluginForm->validate()) {
            if ($uploadPluginForm->execute()) {
                return \PKP\db\DAO::getDataChangedEvent();
            }
        }

        return new JSONMessage(false);
    }

    /**
     * Delete plugin.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deletePlugin($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $plugin = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_PLUGIN);
        $category = $plugin->getCategory();
        $productName = basename($plugin->getPluginPath());

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $installedPlugin = $versionDao->getCurrentVersion('plugins.' . $category, $productName);

        $notificationMgr = new NotificationManager();
        $user = $request->getUser();
        $pluginName = ['pluginName' => $plugin->getDisplayName()];
        if ($installedPlugin) {
            $pluginDest = Core::getBaseDir() . "/plugins/{$category}/{$productName}";
            $pluginLibDest = Core::getBaseDir() . '/' . PKP_LIB_PATH . "/plugins/{$category}/{$productName}";

            // make sure plugin type is valid and then delete the files
            if (in_array($category, PluginRegistry::getCategories())) {
                // Delete the plugin from the file system.
                $fileManager = new FileManager();
                $fileManager->rmtree($pluginDest);
                $fileManager->rmtree($pluginLibDest);
            }

            if (is_dir($pluginDest) || is_dir($pluginLibDest)) {
                $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_ERROR, ['contents' => __('manager.plugins.deleteError', $pluginName)]);
            } else {
                $versionDao->disableVersion('plugins.' . $category, $productName);
                $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('manager.plugins.deleteSuccess', $pluginName)]);
            }
        } else {
            $notificationMgr->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_ERROR, ['contents' => __('manager.plugins.doesNotExist', $pluginName)]);
        }

        return \PKP\db\DAO::getDataChangedEvent($plugin->getName());
    }

    /**
     * Fetch upload plugin form.
     *
     * @param string $function
     * @param PKPRequest $request Request object
     *
     * @return JSONMessage JSON object
     */
    public function _showUploadPluginForm($function, $request)
    {
        $uploadPluginForm = new UploadPluginForm($function);
        $uploadPluginForm->initData();

        return new JSONMessage(true, $uploadPluginForm->fetch($request));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\plugins\PluginGridHandler', '\PluginGridHandler');
}

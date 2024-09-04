<?php

/**
 * @file controllers/grid/plugins/PluginGalleryGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryGridHandler
 *
 * @ingroup controllers_grid_settings_pluginGallery
 *
 * @brief Handle review form grid requests.
 */

namespace PKP\controllers\grid\plugins;

use APP\core\Application;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Exception;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\notification\Notification;
use PKP\plugins\GalleryPlugin;
use PKP\plugins\PluginGalleryDAO;
use PKP\plugins\PluginHelper;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use SplFileObject;

/**
 * Global value for 'all' category string value
 */
class PluginGalleryGridHandler extends GridHandler
{
    public const PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE = 'all';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['fetchGrid', 'fetchRow', 'viewPlugin']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN],
            ['installPlugin', 'upgradePlugin']
        );
    }


    //
    // Implement template methods from PKPHandler.
    //
    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Basic grid configuration.
        $this->setTitle('manager.plugins.pluginGallery');

        //
        // Grid columns.
        //
        $pluginGalleryGridCellProvider = new PluginGalleryGridCellProvider();

        // Plugin name.
        $this->addColumn(
            new GridColumn(
                'name',
                'common.name',
                null,
                null,
                $pluginGalleryGridCellProvider
            )
        );

        // Description.
        $this->addColumn(
            new GridColumn(
                'summary',
                'common.description',
                null,
                null,
                $pluginGalleryGridCellProvider,
                ['width' => 50, 'alignment' => GridColumn::COLUMN_ALIGNMENT_LEFT]
            )
        );

        // Status.
        $this->addColumn(
            new GridColumn(
                'status',
                'common.status',
                null,
                null,
                $pluginGalleryGridCellProvider,
                ['width' => 20]
            )
        );
    }

    /**
     * @see PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Implement methods from GridHandler.
    //
    /**
     * @see GridHandler::loadData()
     *
     * @param PKPRequest $request Request object
     * @param array $filter Filter parameters
     *
     * @return array Grid data.
     */
    protected function loadData($request, $filter)
    {
        // Get all plugins.
        $pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO'); /** @var PluginGalleryDAO $pluginGalleryDao */
        try {
            return $pluginGalleryDao->getNewestCompatible(
                Application::get(),
                $request->getUserVar('category'),
                $request->getUserVar('pluginText')
            );
        } catch (\GuzzleHttp\Exception\TransferException $e) {
            error_log($e);
            return [];
        }
    }

    /**
     * @see GridHandler::getFilterForm()
     */
    protected function getFilterForm()
    {
        return 'controllers/grid/plugins/pluginGalleryGridFilter.tpl';
    }

    /**
     * @see GridHandler::getFilterSelectionData()
     */
    public function getFilterSelectionData($request)
    {
        $category = $request->getUserVar('category');
        $pluginName = $request->getUserVar('pluginText');

        if (is_null($category)) {
            $category = self::PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE;
        }

        return ['category' => $category, 'pluginText' => $pluginName];
    }

    /**
     * @copydoc GridHandler::renderFilter()
     */
    protected function renderFilter($request, $filterData = [])
    {
        $categoriesSymbolic = $categories = PluginRegistry::getCategories();
        $categories = [self::PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE => __('grid.plugin.allCategories')];
        foreach ($categoriesSymbolic as $category) {
            $categories[$category] = __("plugins.categories.{$category}");
        }
        $filterData['categories'] = $categories;

        return parent::renderFilter($request, $filterData);
    }

    //
    // Public operations
    //
    /**
     * View a plugin's details
     */
    public function viewPlugin(array $args, PKPRequest $request): JSONMessage
    {
        $plugin = $this->_getSpecifiedPlugin($request);

        // Display plugin information
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('plugin', $plugin);

        // Get currently installed version, if any.
        $installActionKey = $installConfirmKey = $installOp = null;
        switch ($plugin->getCurrentStatus()) {
            case GalleryPlugin::PLUGIN_GALLERY_STATE_NEWER:
                $statusKey = 'manager.plugins.installedVersionNewer';
                $statusClass = 'newer';
                break;
            case GalleryPlugin::PLUGIN_GALLERY_STATE_UPGRADABLE:
                $statusKey = 'manager.plugins.installedVersionOlder';
                $statusClass = 'older';
                $installActionKey = 'grid.action.upgrade';
                $installOp = 'upgradePlugin';
                $installConfirmKey = 'manager.plugins.upgradeConfirm';
                break;
            case GalleryPlugin::PLUGIN_GALLERY_STATE_CURRENT:
                $statusKey = 'manager.plugins.installedVersionNewest';
                $statusClass = 'newest';
                break;
            case GalleryPlugin::PLUGIN_GALLERY_STATE_AVAILABLE:
                $statusKey = 'manager.plugins.noInstalledVersion';
                $statusClass = 'notinstalled';
                $installActionKey = 'grid.action.install';
                $installOp = 'installPlugin';
                $installConfirmKey = 'manager.plugins.installConfirm';
                break;
            case GalleryPlugin::PLUGIN_GALLERY_STATE_INCOMPATIBLE:
                $statusKey = 'manager.plugins.noCompatibleVersion';
                $statusClass = 'incompatible';
                break;
            default:
                return throw new Exception('Unexpected gallery state');
        }
        $templateMgr->assign([
            'statusKey' => $statusKey,
            'statusClass' => $statusClass
        ]);

        $router = $request->getRouter();
        if (Validation::isSiteAdmin() && $installOp) {
            $templateMgr->assign('installAction', new LinkAction(
                'installPlugin',
                new RemoteActionConfirmationModal(
                    $request->getSession(),
                    __($installConfirmKey),
                    __($installActionKey),
                    $router->url($request, null, null, $installOp, null, ['rowId' => $request->getUserVar('rowId')]),
                    'modal_information'
                ),
                __($installActionKey),
                null
            ));
        }
        return new JSONMessage(true, $templateMgr->fetch('controllers/grid/plugins/viewPlugin.tpl'));
    }

    /**
     * Upgrade a plugin
     */
    public function upgradePlugin(array $args, PKPRequest $request): JSONMessage
    {
        return $this->installPlugin($args, $request, true);
    }

    /**
     * Install or upgrade a plugin
     */
    public function installPlugin(array $args, PKPRequest $request, bool $isUpgrade = false): JSONMessage
    {
        if ($request->getContext()) {
            $redirectUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'management', 'settings', ['website'], ['r' => uniqid()], 'plugins');
        } else {
            $redirectUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, null, 'admin', 'settings', null, ['r' => uniqid()], 'plugins');
        }

        if (!$request->checkCSRF()) {
            return $request->redirectUrlJson($redirectUrl);
        }

        $plugin = $this->_getSpecifiedPlugin($request);
        $notificationMgr = new NotificationManager();
        $user = $request->getUser();
        $pluginHelper = new PluginHelper();

        // Create a temporary file to stream the download
        $pluginFile = new SplFileObject($pluginFilePath = tempnam(sys_get_temp_dir(), 'plugin'), 'w');
        $pluginFile->flock(LOCK_EX);
        $pluginFilePath = $pluginFile->getPathname();
        try {
            // Download the plugin package.
            $body = Application::get()
                ->getHttpClient()
                ->request('GET', $plugin->getReleasePackage())
                ->getBody();
            while ($data = $body->read(80 << 10)) {
                if ($pluginFile->fwrite($data) === false) {
                    throw new Exception('Failed to download the plugin');
                }
            }
            // Release the file
            $pluginFile = null;

            // Verify the plugin checksum.
            if (($md5 = md5_file($pluginFilePath)) !== $plugin->getReleaseMD5()) {
                throw new Exception("Integrity validation failed, expected MD5 {$plugin->getReleaseMD5()} received {$md5}");
            }

            // Install/upgrade the plugin
            $fileName = basename(parse_url($plugin->getReleasePackage(), PHP_URL_PATH));
            $pluginVersion = $isUpgrade
                ? $pluginHelper->upgradePlugin($plugin->getCategory(), $plugin->getProduct(), $pluginFilePath, $fileName)
                : $pluginHelper->installPlugin($pluginFilePath, $fileName);

            // Success notification
            $version = $pluginVersion->getVersionString(false);
            $notificationMgr->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_SUCCESS,
                [
                    'contents' => $isUpgrade
                        ? __('manager.plugins.upgradeSuccessful', ['versionString' => $version])
                        : __('manager.plugins.installSuccessful', ['versionNumber' => $version])
                ]
            );
        } catch (Exception $e) {
            // Failure notification
            $notificationMgr->createTrivialNotification(
                $user->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => $e->getMessage()]
            );
        } finally {
            // Release file
            $pluginFile = null;
            unlink($pluginFilePath);
        }

        return $request->redirectUrlJson($redirectUrl);
    }

    /**
     * Get the specified plugin.
     */
    public function _getSpecifiedPlugin(PKPRequest $request): GalleryPlugin
    {
        // Get all plugins.
        $pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO'); /** @var PluginGalleryDAO $pluginGalleryDao */
        $plugins = $pluginGalleryDao->getNewestCompatible(Application::get());

        // Get specified plugin. Indexes into $plugins are 0-based
        // but row IDs are 1-based; compensate.
        $rowId = (int) $request->getUserVar('rowId') - 1;
        if (!isset($plugins[$rowId])) {
            throw new Exception('Invalid row ID!');
        }
        return $plugins[$rowId];
    }
}

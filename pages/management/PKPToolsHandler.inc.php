<?php

/**
 * @file pages/management/PKPToolsHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPToolsHandler
 * @ingroup pages_management
 *
 * @brief Handle requests for Tool pages.
 */

// Import the base ManagementHandler.
import('lib.pkp.pages.management.ManagementHandler');

define('IMPORTEXPORT_PLUGIN_CATEGORY', 'importexport');

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use PKP\components\listPanels\ListPanel;
use PKP\components\PKPStatsJobsTable;
use PKP\core\JSONMessage;
use PKP\notification\PKPNotification;

use PKP\security\Role;

class PKPToolsHandler extends ManagementHandler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            Role::ROLE_ID_MANAGER,
            ['tools', 'importexport', 'permissions', 'jobs']
        );
    }


    //
    // Public handler methods.
    //
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_SUBMISSION);
    }

    /**
     * Route to other Tools operations
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function tools($args, $request)
    {
        $path = array_shift($args);
        switch ($path) {
            case '':
            case 'index':
                $this->index($args, $request);
                break;
            case 'permissions':
                $this->permissions($args, $request);
                break;
            case 'resetPermissions':
                $this->resetPermissions($args, $request);
                break;
            case 'jobs':
                $this->jobs($args, $request);
            break;
            default: assert(false);
        }
    }

    /**
     * Display tools index page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function index($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $templateMgr->assign('pageTitle', __('navigation.tools'));
        $templateMgr->display('management/tools/index.tpl');
    }

    /**
     * Import or export data.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function importexport($args, $request)
    {
        $this->setupTemplate($request, true);

        PluginRegistry::loadCategory(IMPORTEXPORT_PLUGIN_CATEGORY);
        $templateMgr = TemplateManager::getManager($request);

        if (array_shift($args) === 'plugin') {
            $pluginName = array_shift($args);
            $plugin = PluginRegistry::getPlugin(IMPORTEXPORT_PLUGIN_CATEGORY, $pluginName);
            if ($plugin) {
                return $plugin->display($args, $request);
            }
        }
        $templateMgr->assign('plugins', PluginRegistry::getPlugins(IMPORTEXPORT_PLUGIN_CATEGORY));
        return $templateMgr->fetchJson('management/tools/importexport.tpl');
    }

    //
    // Protected methods.
    //
    /**
     * Display the permissipns area.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function permissions($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);

        return $templateMgr->fetchJson('management/tools/permissions.tpl');
    }

    /**
     * Reset article/monograph permissions
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function resetPermissions($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $context = $request->getContext();
        if (!$context) {
            return;
        }

        Repo::submission()->resetPermissions($context->getId());

        $user = $request->getUser();
        NotificationManager::createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('manager.setup.resetPermissions.success')]);

        // This is an ugly hack to force the PageHandler to return JSON, so this
        // method can communicate properly with the AjaxFormHandler. Returning a
        // JSONMessage, or JSONMessage::toString(), doesn't seem to do it.
        echo json_encode(true);
        exit;
    }

    public function jobs($args, $request)
    {
        $this->setupTemplate($request, true);

        $dateFormatShort = Application::get()
            ->getRequest()
            ->getContext()
            ->getLocalizedDateTimeFormatShort();

        $queuedJobsItems = DB::table('jobs')
            ->whereNotNull('queue')
            ->whereNull('reserved_at')
            ->get()
            ->toArray();

        $parsedItems = [];
        foreach ($queuedJobsItems as $currentItem) {
            // Parsing job contents
            $parsedJob = json_decode($currentItem->payload, true);
            $parsedItems[] = [
                'id' => $currentItem->id,
                'title' => $parsedJob['displayName'],
                'subtitle' => __('manager.jobs.createdAt', ['createdAt' => strftime($dateFormatShort, $currentItem->created_at)]),
                'actions' => null,
            ];
        }

        // dd($parsedItems);

        $templateMgr = TemplateManager::getManager($request);

        $queuedJobs = new ListPanel(
            'queuedJobsItems',
            'Queued Jobs List',
            [
                'items' => $parsedItems
            ]
        );

        // $queuedJobs = new PKPStatsJobsTable(
        //     'queuedJobsTable',
        //     [
        //         'label' => 'Queued Jobs Table',
        //         'description' => 'Doesn\'t look fancy?',
        //         'tableColumns' => [
        //             [
        //                 'name' => 'id',
        //                 'label' => 'ID',
        //                 'value' => 'id',
        //             ],
        //             [
        //                 'name' => 'title',
        //                 'label' => 'Job',
        //                 'value' => 'title',
        //             ],
        //             [
        //                 'name' => 'created_at',
        //                 'label' => 'Created At',
        //                 'value' => 'created_at',
        //             ],
        //             [
        //                 'name' => 'actions',
        //                 'label' => 'Actions',
        //             ],
        //         ],
        //         'tableRows' => $parsedItems,
        //     ]
        // );

        $templateMgr->setState([
            'components' => [
                $queuedJobs->id => $queuedJobs->getConfig(),
            ],
        ]);
        $templateMgr->assign('pageTitle', __('navigation.tools.jobs'));
        $templateMgr->display('management/tools/jobs.tpl');
    }
}

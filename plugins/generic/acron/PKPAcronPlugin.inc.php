<?php

/**
 * @file plugins/generic/acron/PKPAcronPlugin.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAcronPlugin
 * @ingroup plugins_generic_acron
 *
 * @brief Removes dependency on 'cron' for scheduled tasks, including
 * possible tasks defined by plugins. See the PKPAcronPlugin::parseCrontab
 * hook implementation.
 */

use APP\core\Application;
use APP\notification\NotificationManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\notification\PKPNotification;
use PKP\plugins\GenericPlugin;
use PKP\plugins\HookRegistry;

use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\xml\PKPXMLParser;
use PKP\xml\XMLNode;

// TODO: Error handling. If a scheduled task encounters an error...?

class PKPAcronPlugin extends GenericPlugin
{
    /** @var string $_workingDir */
    public $_workingDir;

    /** @var array $_tasksToRun */
    public $_tasksToRun;

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        HookRegistry::register('Installer::postInstall', [&$this, 'callbackPostInstall']);

        if (Application::isUnderMaintenance()) {
            return $success;
        }
        if ($success) {
            $this->addLocaleData();
            HookRegistry::register('LoadHandler', [&$this, 'callbackLoadHandler']);
            // Need to reload cron tab on possible enable or disable generic plugin actions.
            HookRegistry::register('PluginGridHandler::plugin', [&$this, 'callbackManage']);
        }
        return $success;
    }

    /**
    * @copydoc Plugin::isSitePlugin()
    */
    public function isSitePlugin()
    {
        // This is a site-wide plugin.
        return true;
    }

    /**
     * @copydoc LazyLoadPlugin::getName()
     */
    public function getName()
    {
        return 'acronPlugin';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.acron.name');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.acron.description');
    }

    /**
     * @copydoc Plugin::getInstallSitePluginSettingsFile()
     */
    public function getInstallSitePluginSettingsFile()
    {
        return PKP_LIB_PATH . "/{$this->getPluginPath()}/settings.xml";
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'reload',
                    new AjaxAction(
                        $router->url($request, null, null, 'manage', null, ['verb' => 'reload', 'plugin' => $this->getName(), 'category' => 'generic'])
                    ),
                    __('plugins.generic.acron.reload'),
                    null
                )
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * @see Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'reload':
                $this->_parseCrontab();
                $notificationManager = new NotificationManager();
                $user = $request->getUser();
                $notificationManager->createTrivialNotification(
                    $user->getId(),
                    PKPNotification::NOTIFICATION_TYPE_SUCCESS,
                    ['contents' => __('plugins.generic.acron.tasksReloaded')]
                );
                return \PKP\db\DAO::getDataChangedEvent();
        }
        return parent::manage($args, $request);
    }

    /**
     * Post install hook to flag cron tab reload on every install/upgrade.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     *
     * @see Installer::postInstall() for the hook call.
     */
    public function callbackPostInstall($hookName, $args)
    {
        $this->_parseCrontab();
        return false;
    }

    /**
     * Load handler hook to check for tasks to run.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     *
     * @see PKPPageRouter::loadHandler() for the hook call.
     */
    public function callbackLoadHandler($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        // Avoid controllers requests because of the shutdown function usage.
        if (!is_a($router, 'PKPPageRouter')) {
            return false;
        }

        $tasksToRun = $this->_getTasksToRun();
        if (!empty($tasksToRun)) {
            // Save the current working directory, so we can fix
            // it inside the shutdown function.
            $this->_workingDir = getcwd();

            // Save the tasks to be executed.
            $this->_tasksToRun = $tasksToRun;

            // Need output buffering to send a finish message
            // to browser inside the shutdown function. Couldn't
            // do without the buffer.
            ob_start();

            // This callback will be used as soon as the main script
            // is finished. It will not stop running, even if the user cancels
            // the request or the time limit is reach.
            register_shutdown_function([&$this, 'shutdownFunction']);
        }

        return false;
    }

    /**
     * Syncronize crontab with lazy load plugins management.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     *
     * @see PluginHandler::plugin() for the hook call.
     */
    public function callbackManage($hookName, $args)
    {
        $verb = $args[0];
        $plugin = $args[4]; /** @var LazyLoadPlugin $plugin */

        // Only interested in plugins that can be enabled/disabled.
        if (!is_a($plugin, 'LazyLoadPlugin')) {
            return false;
        }

        // Only interested in enable/disable actions.
        if ($verb !== 'enable' && $verb !== 'disable') {
            return false;
        }

        // Check if the plugin wants to add its own
        // scheduled task into the cron tab.

        foreach (HookRegistry::getHooks('AcronPlugin::parseCronTab') as $hookPriorityList) {
            foreach ($hookPriorityList as $priority => $callback) {
                if ($callback[0] == $plugin) {
                    $this->_parseCrontab();
                    break;
                }
            }
        }

        return false;
    }

    /**
     * Shutdown callback.
     */
    public function shutdownFunction()
    {
        // Release requests from waiting the processing.
        header('Connection: close');
        // This header is needed so avoid using any kind of compression. If zlib is
        // enabled, for example, the buffer will not output until the end of the
        // script execution.
        header('Content-Encoding: none');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        flush();

        set_time_limit(0);

        // Fix the current working directory. See
        // http://www.php.net/manual/en/function.register-shutdown-function.php#92657
        chdir($this->_workingDir);

        $taskDao = DAORegistry::getDAO('ScheduledTaskDAO');
        foreach ($this->_tasksToRun as $task) {
            // Strip off the package name(s) to get the base class name
            $className = $task['className'];
            $pos = strrpos($className, '.');
            if ($pos === false) {
                $baseClassName = $className;
            } else {
                $baseClassName = substr($className, $pos + 1);
            }
            $taskArgs = [];
            if (isset($task['args'])) {
                $taskArgs = $task['args'];
            }

            // There's a race here. Several requests may come in closely spaced.
            // Each may decide it's time to run scheduled tasks, and more than one
            // can happily go ahead and do it before the "last run" time is updated.
            // By updating the last run time as soon as feasible, we can minimize
            // the race window. See bug #8737.
            $tasksToRun = $this->_getTasksToRun();
            $updateResult = 0;
            if (in_array($task, $tasksToRun, true)) {
                $updateResult = $taskDao->updateLastRunTime($className, time());
            }

            if ($updateResult === false || $updateResult === 1) {
                // DB doesn't support the get affected rows used inside update method, or one row was updated when we introduced a new last run time.
                // Load and execute the task.
                import($className);
                $task = new $baseClassName($taskArgs);
                $task->execute();
            }
        }
    }


    //
    // Private helper methods.
    //
    /**
     * Parse all scheduled tasks files and
     * save the result object in database.
     */
    public function _parseCrontab()
    {
        $xmlParser = new PKPXMLParser();

        $taskFilesPath = [];

        // Load all plugins so any plugin can register a crontab.
        PluginRegistry::loadAllPlugins();

        // Let plugins register their scheduled tasks too.
        HookRegistry::call('AcronPlugin::parseCronTab', [&$taskFilesPath]); // Reference needed.

        $tasks = [];
        foreach ($taskFilesPath as $filePath) {
            $tree = $xmlParser->parse($filePath);

            if (!$tree) {
                fatalError('Error parsing scheduled tasks XML file: ' . $filePath);
            }

            foreach ($tree->getChildren() as $task) {
                $frequency = $task->getChildByName('frequency');

                $args = ScheduledTaskHelper::getTaskArgs($task);

                // Tasks without a frequency defined, or defined to zero, will run on every request.
                // To avoid that happening (may cause performance problems) we
                // setup a default period of time.
                $setDefaultFrequency = true;
                $minHoursRunPeriod = 24;
                if ($frequency) {
                    $frequencyAttributes = $frequency->getAttributes();
                    if (is_array($frequencyAttributes)) {
                        foreach ($frequencyAttributes as $key => $value) {
                            if ($value != 0) {
                                $setDefaultFrequency = false;
                                break;
                            }
                        }
                    }
                }
                $tasks[] = [
                    'className' => $task->getAttribute('class'),
                    'frequency' => $setDefaultFrequency ? ['hour' => $minHoursRunPeriod] : $frequencyAttributes,
                    'args' => $args
                ];
            }
        }

        // Store the object.
        $this->updateSetting(0, 'crontab', $tasks, 'object');
    }

    /**
     * Get all scheduled tasks that needs to be executed.
     *
     * @return array
     */
    public function _getTasksToRun()
    {
        $tasksToRun = [];
        $isEnabled = $this->getSetting(0, 'enabled');

        if ($isEnabled) {
            $taskDao = DAORegistry::getDAO('ScheduledTaskDAO');

            // Grab the scheduled scheduled tree
            $scheduledTasks = $this->getSetting(0, 'crontab');
            if (is_null($scheduledTasks)) {
                $this->_parseCrontab();
                $scheduledTasks = $this->getSetting(0, 'crontab');
            }

            foreach ($scheduledTasks as $task) {
                // We don't allow tasks without frequency, see _parseCronTab().
                $frequency = new XMLNode();
                $frequency->setAttribute(key($task['frequency']), current($task['frequency']));
                $canExecute = ScheduledTaskHelper::checkFrequency($task['className'], $frequency);

                if ($canExecute) {
                    $tasksToRun[] = $task;
                }
            }
        }

        return $tasksToRun;
    }
}

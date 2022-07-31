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
use Illuminate\Support\Facades\Event;
use PKP\core\JSONMessage;
use PKP\core\PKPPageRouter;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxAction;
use PKP\notification\PKPNotification;
use PKP\observers\events\PluginEnabledChanged;
use PKP\plugins\GenericPlugin;
use PKP\plugins\HookRegistry;
use PKP\plugins\PluginRegistry;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\xml\PKPXMLParser;
use PKP\xml\XMLNode;

// TODO: Error handling. If a scheduled task encounters an error...?

class PKPAcronPlugin extends GenericPlugin
{
    private array $_tasksToRun;

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null): bool
    {
        $success = parent::register($category, $path, $mainContextId);
        HookRegistry::register('Installer::postInstall', fn (string $hookName, array $args) => $this->_callbackPostInstall($hookName, $args));

        if (Application::isUnderMaintenance()) {
            return $success;
        }
        if ($success) {
            $this->addLocaleData();
            HookRegistry::register('LoadHandler', fn (string $hookName, array $args) => $this->_callbackLoadHandler($hookName, $args));
            // Reload cron tab when a plugin is enabled/disabled
            Event::listen(PluginEnabledChanged::class, fn (PluginEnabledChanged $event) => $this->_callbackManage($event));
        }
        return $success;
    }

    /**
     * @copydoc Plugin::isSitePlugin()
     */
    public function isSitePlugin(): bool
    {
        // This is a site-wide plugin.
        return true;
    }

    /**
     * @copydoc LazyLoadPlugin::getName()
     */
    public function getName(): string
    {
        return 'acronPlugin';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName(): string
    {
        return __('plugins.generic.acron.name');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription(): string
    {
        return __('plugins.generic.acron.description');
    }

    /**
     * @copydoc Plugin::getInstallSitePluginSettingsFile()
     */
    public function getInstallSitePluginSettingsFile(): string
    {
        return PKP_LIB_PATH . "/{$this->getPluginPath()}/settings.xml";
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs): array
    {
        $router = $request->getRouter();
        $actions = parent::getActions($request, $actionArgs);
        if ($this->getEnabled()) {
            $url = $router->url($request, null, null, 'manage', null, ['verb' => 'reload', 'plugin' => $this->getName(), 'category' => 'generic']);
            array_unshift($actions, new LinkAction('reload', new AjaxAction($url), __('plugins.generic.acron.reload')));
        }
        return $actions;
    }

    /**
     * @see Plugin::manage()
     */
    public function manage($args, $request): JSONMessage
    {
        if ($request->getUserVar('verb') !== 'reload') {
            return parent::manage($args, $request);
        }

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

    /**
     * Post install hook to flag cron tab reload on every install/upgrade.
     * @see Installer::postInstall() for the hook call.
     */
    private function _callbackPostInstall(string $hookName, array $args): bool
    {
        $this->_parseCrontab();
        return false;
    }

    /**
     * Load handler hook to check for tasks to run.
     * @see PKPPageRouter::loadHandler() for the hook call.
     */
    private function _callbackLoadHandler(string $hookName, array $args): bool
    {
        $request = Application::get()->getRequest();
        $router = $request->getRouter();
        // Avoid controllers requests because of the shutdown function usage.
        if (!($router instanceof PKPPageRouter)) {
            return false;
        }

        $tasksToRun = $this->_getTasksToRun();
        if (empty($tasksToRun)) {
            return false;
        }

        // Save the current working directory, so we can fix
        // it inside the shutdown function.
        $workingDir = getcwd();

        // Save the tasks to be executed.
        $this->_tasksToRun = $tasksToRun;

        // Need output buffering to send a finish message
        // to browser inside the shutdown function. Couldn't
        // do without the buffer.
        ob_start();

        // This callback will be used as soon as the main script
        // is finished. It will not stop running, even if the user cancels
        // the request or the time limit is reach.
        register_shutdown_function(fn () => $this->_shutdownFunction($workingDir));

        return false;
    }

    /**
     * Synchronize crontab with lazy load plugins management.
     * @see PluginHandler::plugin() for the hook call.
     */
    private function _callbackManage(PluginEnabledChanged $event): bool
    {
        // Check if the plugin wants to add its own scheduled task into the cron tab.
        foreach (HookRegistry::getHooks('AcronPlugin::parseCronTab') ?? [] as $hookPriorityList) {
            foreach ($hookPriorityList as [$callback]) {
                if ($callback == $event->plugin) {
                    $this->_parseCrontab();
                    break 2;
                }
            }
        }

        return false;
    }

    /**
     * Shutdown callback.
     */
    private function _shutdownFunction(string $workingDir): void
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
        chdir($workingDir);

        /** @var ScheduledTaskDAO */
        $taskDao = DAORegistry::getDAO('ScheduledTaskDAO');
        foreach ($this->_tasksToRun as $task) {
            // Strip off the package name(s) to get the base class name
            $className = $task['className'];
            $pos = strrpos($className, '.');
            $baseClassName = $pos === false ? $className : substr($className, $pos + 1);
            $taskArgs = $task['args'] ?? [];

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

    /**
     * Parse all scheduled tasks files and
     * save the result object in database.
     */
    private function _parseCrontab(): void
    {
        $xmlParser = new PKPXMLParser();

        $taskFilesPath = [];

        // Load all plugins so any plugin can register a crontab.
        PluginRegistry::loadAllPlugins();

        // Let plugins register their scheduled tasks too.
        HookRegistry::call('AcronPlugin::parseCronTab', [&$taskFilesPath]); // Reference needed.

        // Add the default tasks file.
        $taskFilesPath[] = 'registry/scheduledTasks.xml'; // TODO: make this a plugin setting, rather than assuming.

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
                        foreach ($frequencyAttributes as $value) {
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
     */
    private function _getTasksToRun(): array
    {
        $isEnabled = $this->getSetting(0, 'enabled');
        if (!$isEnabled) {
            return [];
        }

        $tasksToRun = [];
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

        return $tasksToRun;
    }
}

<?php

/**
 * @defgroup tools Tools
 * Implements command-line management tools for PKP software.
 */

/**
 * @file classes/cliTool/CommandLineTool.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CommandLineTool
 * @ingroup tools
 *
 * @brief Initialization code for command-line scripts.
 *
 * FIXME: Write a PKPCliRequest and PKPCliRouter class and use the dispatcher
 *  to bootstrap and route tool requests.
 */

namespace PKP\cliTool;

use APP\core\Application;
use APP\core\PageRouter;
use APP\facades\Repo;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\session\SessionManager;

/** Initialization code */
define('PWD', getcwd());
chdir(dirname(INDEX_FILE_LOCATION)); /* Change to base directory */
if (!defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}
SessionManager::disable();
require('./lib/pkp/includes/bootstrap.inc.php');

class CommandLineTool
{
    /** @var string the script being executed */
    public $scriptName;

    /** @vary array Command-line arguments */
    public $argv;

    /** @var string the username provided */
    public $username;

    /** @var User the user provided */
    public $user;

    public function __construct($argv = [])
    {
        // Initialize the request object with a page router
        $application = Application::get();
        $request = $application->getRequest();

        // FIXME: Write and use a CLIRouter here (see classdoc)
        $router = new PageRouter();
        $router->setApplication($application);
        $request->setRouter($router);

        // Initialize the locale and load generic plugins.
        PluginRegistry::loadCategory('generic');

        $this->argv = isset($argv) && is_array($argv) ? $argv : [];

        if (isset($_SERVER['SERVER_NAME'])) {
            exit('This script can only be executed from the command-line');
        }

        $this->scriptName = isset($this->argv[0]) ? array_shift($this->argv) : '';

        $this->checkArgsForUsername();

        if (isset($this->argv[0]) && $this->argv[0] == '-h') {
            $this->exitWithUsageMessage();
        }
    }

    public function usage()
    {
    }

    private function checkArgsForUsername()
    {
        $usernameKeyPos = array_search('--user_name', $this->argv);
        if (!$usernameKeyPos) {
            $usernameKeyPos = array_search('-u', $this->argv);
        }

        if ($usernameKeyPos) {
            $usernamePos = $usernameKeyPos + 1;
            if (count($this->argv) >= $usernamePos + 1) {
                $this->username = $this->argv[$usernamePos];

                unset($this->argv[$usernamePos]);
            }

            unset($this->argv[$usernameKeyPos]);
        }

        if ($this->username) {
            $user = Repo::user()->getByUsername($this->username, true);

            $this->setUser($user);
        }

        if (!$this->user) {
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
            $adminGroups = $userGroupDao->getUserGroupIdsByRoleId(Role::ROLE_ID_SITE_ADMIN);

            if (count($adminGroups)) {
                $groupUsers = $userGroupDao->getUsersById($adminGroups[0])->toArray();

                if (count($groupUsers) > 0) {
                    $this->setUser($groupUsers[0]);
                } else {
                    $this->exitWithUsageMessage();
                }
            }
        }
    }

    /**
     * Sets the user for the CLI Tool
     *
     * @param User $user The user to set as the execution user of this CLI command
     */
    public function setUser($user)
    {
        $registeredUser = Registry::get('user', true, null);
        if (!isset($registeredUser)) {
            /**
             * This is used in order to reconcile with possible $request->getUser()
             * used inside import processes, when the import is done by CLI tool.
             */
            if ($user) {
                Registry::set('user', $user);
                $this->user = $user;
            }
        } else {
            $this->user = $registeredUser;
        }
    }

    /**
     * Exit the CLI tool if an error occurs
     */
    public function exitWithUsageMessage()
    {
        $this->usage();
        exit(0);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\cliTool\CommandLineTool', '\CommandLineTool');
}

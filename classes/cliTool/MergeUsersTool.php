<?php

/**
 * @file lib/pkp/classes/cliTool/MergeUsersTool.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class mergeUsers
 *
 * @ingroup tools
 *
 * @brief CLI tool for merging two user accounts.
 */

namespace PKP\cliTool;

use APP\facades\Repo;

class MergeUsersTool extends \PKP\cliTool\CommandLineTool
{
    /** @var string $targetSpecifier */
    public $targetSpecifier;

    /** @var array $mergeSpecifier */
    public $mergeSpecifiers;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (!isset($this->argv[0]) || !isset($this->argv[1])) {
            $this->usage();
            exit(1);
        }

        $this->targetSpecifier = $this->argv[0];
        $this->mergeSpecifiers = array_slice($this->argv, 1);
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Merge users tool\n"
            . "Use this tool to merge two or more user accounts.\n\n"
            . "Usage: {$this->scriptName} targetUsername mergeUsername1 [mergeUsername2] [...]\n"
            . "targetUsername: The target username for assets to be transferred to.\n"
            . "mergeUsername1: The username for the account to be merged. All assets (e.g.\n"
            . "                submissions) associated with this user account will be\n"
            . "                transferred to the user account that corresponds to\n"
            . "                targetUsername. The user account that corresponds\n"
            . "                to mergeUsername1 will be deleted.\n\n"
            . "Multiple users to merge can be specified in the same command, e.g.:\n\n"
            . "{$this->scriptName} myUsername spamUser1 spamUser2 spamUser3\n\n"
            . "This will merge users with username \"spamUser1\", \"spamUser2\", and\n"
            . "\"spamUser3\" into the account with username \"myUsername\".\n\n"
            . "Users can be specified by ID by entering usernames of the form \"id=x\"\n"
            . "with the user ID in place of \"x\", e.g.:\n\n"
            . "{$this->scriptName} myUsername id=234 id=456\n\n"
            . "Usernames and IDs may be mixed as desired.\n";
    }

    /**
     * Execute the merge users command.
     */
    public function execute()
    {
        $targetUser = $this->_getUserBySpecifier($this->targetSpecifier);
        if (!$targetUser) {
            echo "Error: \"{$this->targetSpecifier}\" does not specify a valid user.\n";
            exit(1);
        }

        // Build a list of usernames and IDs, checking for missing users before doing anything.
        $mergeArray = [];
        foreach ($this->mergeSpecifiers as $specifier) {
            $mergeUser = $this->_getUserBySpecifier($specifier);
            if (!$mergeUser) {
                echo "Error: \"{$specifier}\" does not specify a valid user.\n";
                exit(2);
            }
            if ($mergeUser->getId() == $targetUser->getId()) {
                echo "Error: Cannot merge an account into itself.\n";
                exit(3);
            }
            $mergeArray[$mergeUser->getId()] = $mergeUser->getUsername();
        }

        // Merge the accounts.
        foreach ($mergeArray as $userId => $username) {
            Repo::user()->mergeUsers((int) $userId, $targetUser->getId());
        }

        if (count($mergeArray) == 1) {
            echo "Merge completed: \"{$username}\" merged into \"" . $targetUser->getUsername() . "\".\n";
        } else {
            echo 'Merge completed: ' . count($mergeArray) . ' users merged into "' . $targetUser->getUsername() . "\".\n";
        }
    }

    /**
     * Get a username by specifier, i.e. username or id=xyz.
     *
     * @param string $specifier The specifier
     *
     * @return \PKP\user\User|null
     */
    protected function _getUserBySpecifier($specifier)
    {
        if (substr($specifier, 0, 3) == 'id=') {
            $userId = substr($specifier, 3);
            if (!ctype_digit($userId)) {
                return null;
            }
            return Repo::user()->get((int) $userId, true);
        }
        return Repo::user()->getByUsername($specifier, true);
    }
}

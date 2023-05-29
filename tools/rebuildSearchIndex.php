<?php

/**
 * @file tools/rebuildSearchIndex.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class rebuildSearchIndex
 *
 * @ingroup tools
 *
 * @brief CLI tool to rebuild the preprint keyword search database.
 */

require(dirname(__FILE__) . '/bootstrap.php');

use APP\core\Application;
use PKP\cliTool\CommandLineTool;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;

class rebuildSearchIndex extends CommandLineTool
{
    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Script to rebuild preprint search index\n"
            . "Usage: {$this->scriptName} [options] [server_path]\n\n"
            . "options: The standard index implementation does\n"
            . "         not support any options. For other\n"
            . "         implementations please see the corresponding\n"
            . "         plugin documentation (e.g. 'plugins/generic/\n"
            . "         lucene/README').\n";
    }

    /**
     * Rebuild the search index for all preprints in all servers.
     */
    public function execute()
    {
        // Check whether we have (optional) switches.
        $switches = [];
        while (count($this->argv) && substr($this->argv[0], 0, 1) == '-') {
            $switches[] = array_shift($this->argv);
        }

        // If we have another argument that this must be a server path.
        $server = null;
        if (count($this->argv)) {
            $serverPath = array_shift($this->argv);
            $serverDao = DAORegistry::getDAO('ServerDAO'); /** @var ServerDAO $serverDao */
            $server = $serverDao->getByPath($serverPath);
            if (!$server) {
                exit(__('search.cli.rebuildIndex.unknownServer', ['serverPath' => $serverPath]) . "\n");
            }
        }

        // Register a router hook so that we can construct
        // useful URLs to server content.
        Hook::add('Request::getBaseUrl', [$this, 'callbackBaseUrl']);

        // Let the search implementation re-build the index.
        $preprintSearchIndex = Application::getSubmissionSearchIndex();
        $preprintSearchIndex->rebuildIndex(true, $server, $switches);
    }

    /**
     * Callback to patch the base URL which will be required
     * when constructing galley/supp file download URLs.
     *
     * @see \App\core\Request::getBaseUrl()
     */
    public function callbackBaseUrl($hookName, $params)
    {
        $baseUrl = & $params[0];
        $baseUrl = Config::getVar('general', 'base_url');
        return true;
    }
}

$tool = new rebuildSearchIndex($argv ?? []);
$tool->execute();

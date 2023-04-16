<?php

/**
 * @file plugins/importexport/native/PKPNativeImportExportCLIDeployment.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPNativeImportExportCLIDeployment
 *
 * @ingroup plugins_importexport_native
 *
 * @brief CLI Deployment for Import/Export operations
 */

namespace PKP\plugins\importexport\native;

class PKPNativeImportExportCLIDeployment
{
    /** @var string The import/export script name */
    private $scriptName;

    /** @var array The import/export arguments */
    public $args;

    /** @var array The import/export additional directives */
    public $opts;

    /** @var string The import/export command */
    public $command;

    /** @var string The import/export xml file name */
    public $xmlFile;

    /** @var string The import/export operation context path */
    public $contextPath;

    /** @var string The import/export operation user name */
    public $userName;

    /** @var string The export entity */
    public $exportEntity;

    /**
     * Constructor
     */
    public function __construct($scriptName, $args)
    {
        $this->scriptName = $scriptName;
        $this->args = $args;

        $this->parseCLI();
    }

    /**
     * Parse CLI Command to populate the Deployment's variables
     */
    public function parseCLI()
    {
        $this->opts = $this->parseOpts($this->args, ['no-embed', 'use-file-urls']);
        $this->command = array_shift($this->args);
        $this->xmlFile = array_shift($this->args);
        $this->contextPath = array_shift($this->args);

        switch ($this->command) {
            case 'import':
                $this->userName = array_shift($this->args);
                break;
            case 'export':
                $this->exportEntity = array_shift($this->args);
                break;
            case 'usage':
                break;
            default:
                throw new \BadMethodCallException(__('plugins.importexport.common.error.unknownCommand', ['command' => $this->command]));
        }
    }

    /**
     * Pull out getopt style long options.
     *
     * @param array $args
     * @param array $optCodes
     */
    public function parseOpts(&$args, $optCodes)
    {
        $newArgs = [];
        $opts = [];
        $sticky = null;
        foreach ($args as $arg) {
            if ($sticky) {
                $opts[$sticky] = $arg;
                $sticky = null;
                continue;
            }
            if (substr($arg, 0, 2) != '--') {
                $newArgs[] = $arg;
                continue;
            }
            $opt = substr($arg, 2);
            if (in_array($opt, $optCodes)) {
                $opts[$opt] = true;
                continue;
            }
            if (in_array($opt . ':', $optCodes)) {
                $sticky = $opt;
                continue;
            }
        }
        $args = $newArgs;
        return $opts;
    }
}

<?php

/**
 * @file tools/installEmailTemplate.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class installEmailTemplate
 * @ingroup tools
 *
 * @brief CLI tool to install email templates from PO files into the database.
 */

use PKP\facades\Repo;

require(dirname(__FILE__, 4) . '/tools/bootstrap.inc.php');


class installEmailTemplates extends CommandLineTool
{
    /** @var string The email key of the email template to install. */
    public $_emailKey;

    /** @var string The list of locales in which to install the template. */
    public $_locales;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        $this->_emailKey = array_shift($this->argv);
        $this->_locales = array_shift($this->argv);

        if ($this->_emailKey === null || $this->_locales === null) {
            $this->usage();
            exit;
        }
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Command-line tool for installing email templates.\n"
            . "Usage:\n"
            . "\t{$this->scriptName} emailKey aa_BB[,cc_DD,...] [path/to/emails.po]\n"
            . "\t\temailKey: The email key of the email to install, e.g. ANNOUNCEMENT\n"
            . "\t\taa_BB[,cc_DD,...]: The comma-separated list of locales to install\n";
    }

    /**
     * Execute upgrade task
     */
    public function execute()
    {
        // Load the necessary locale data
        $locales = explode(',', $this->_locales);

        // Install to the database
        Repo::emailTemplate()->dao->installEmailTemplates(Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(), $locales, $this->_emailKey);
    }
}

$tool = new installEmailTemplates($argv ?? []);
$tool->execute();

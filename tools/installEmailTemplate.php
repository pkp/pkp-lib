<?php

/**
 * @file tools/installEmailTemplate.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class installEmailTemplate
 * @ingroup tools
 *
 * @brief CLI tool to install email templates from PO files into the database.
 */

require(dirname(dirname(dirname(dirname(__FILE__)))) . '/tools/bootstrap.inc.php');

import('lib.pkp.classes.cliTool.CliTool');

class installEmailTemplates extends CommandLineTool {
	/** @var string The email key of the email template to install. */
	var $_emailKey;

	/** @var string The list of locales in which to install the template. */
	var $_locales;

	/**
	 * Constructor.
	 * @param $argv array command-line arguments
	 */
	function __construct($argv = array()) {
		parent::__construct($argv);

		$this->_emailKey = array_shift($this->argv);
		$this->_locales = array_shift($this->argv);

		if ($this->_emailKey === null || $this->_locales === null) {
			$this->usage();
			exit();
		}
	}

	/**
	 * Print command usage information.
	 */
	function usage() {
		echo "Command-line tool for installing email templates.\n"
			. "Usage:\n"
			. "\t{$this->scriptName} emailKey aa_BB[,cc_DD,...] [path/to/emails.po]\n"
			. "\t\temailKey: The email key of the email to install, e.g. ANNOUNCEMENT\n"
			. "\t\taa_BB[,cc_DD,...]: The comma-separated list of locales to install\n";
	}

	/**
	 * Execute upgrade task
	 */
	function execute() {
		// Load the necessary locale data
		$locales = explode(',', $this->_locales);
		foreach ($locales as $locale) AppLocale::requireComponents(LOCALE_COMPONENT_APP_EMAIL, $locale);

		// Install to the database
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$emailTemplateDao->installEmailTemplates($emailTemplateDao->getMainEmailTemplatesFilename(), $locales, false, $this->_emailKey);
	}
}

$tool = new installEmailTemplates(isset($argv) ? $argv : array());
$tool->execute();


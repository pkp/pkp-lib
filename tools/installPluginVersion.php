<?php

/**
 * @file tools/installPluginVersionTool.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallPluginVersionTool
 * @ingroup tools
 *
 * @brief CLI tool for installing a plugin version descriptor.
 */

require(dirname(__FILE__, 4) . '/tools/bootstrap.inc.php');

use APP\core\Application;
use APP\install\Upgrade;
use PKP\site\VersionCheck;

class InstallPluginVersionTool extends \PKP\cliTool\CommandLineTool
{
    /** @var string Path to descriptor file to install */
    private $_descriptor;

    /**
     * Constructor.
     *
     * @param array $argv command-line arguments
     */
    public function __construct($argv = [])
    {
        parent::__construct($argv);

        if (!isset($this->argv[0]) || !file_exists($this->argv[0])) {
            $this->usage();
            exit(1);
        }

        $this->_descriptor = $this->argv[0];
    }

    /**
     * Print command usage information.
     */
    public function usage()
    {
        echo "Install plugin version tool\n"
            . "Usage: {$this->scriptName} path/to/version.xml\n";
    }

    /**
     * Execute the specified command.
     */
    public function execute()
    {
        $versionInfo = VersionCheck::parseVersionXML($this->_descriptor);
        $pluginVersion = $versionInfo['version'];

        $productType = $pluginVersion->getProductType();
        if (!preg_match('/^plugins\.(.+)$/', $productType, $matches) || !in_array($matches[1], Application::get()->getPluginCategories())) {
            error_log("Invalid type \"${productType}\".");
            return false;
        }

        $versionDao = DAORegistry::getDAO('VersionDAO');
        $versionDao->insertVersion($pluginVersion, true);

        $pluginPath = dirname($this->_descriptor);
        $plugin = @include("${pluginPath}/index.php");
        if ($plugin && is_object($plugin)) {
            PluginRegistry::register($matches[1], $plugin, $pluginPath);
        }
        $plugin = PluginRegistry::getPlugin($matches[1], $plugin->getName());

        $installer = new Upgrade([]);
        $result = true;
        $param = [&$installer, &$result];

        if ($plugin->getInstallMigration()) {
            $plugin->updateSchema('Installer::postInstall', $param);
        }
        if ($plugin->getInstallSitePluginSettingsFile()) {
            $plugin->installSiteSettings('Installer::postInstall', $param);
        }
        if ($plugin->getInstallEmailTemplatesFile()) {
            $plugin->installEmailTemplates('Installer::postInstall', $param);
        }
        if ($plugin->getInstallEmailTemplateDataFile()) {
            $plugin->installEmailTemplateData('Installer::postInstall', $param);
        }
        $plugin->installFilters('Installer::postInstall', $param);
        return $result;
    }
}

Application::upgrade();
$tool = new InstallPluginVersionTool($argv ?? []);
$tool->execute();

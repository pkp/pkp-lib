<?php

/**
 * @file classes/plugins/ImportExportPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ImportExportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for import/export plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class ImportExportPlugin extends Plugin {
	/** @var Request Request made available for plugin URL generation */
	var $_request;


	/**
	 * Execute import/export tasks using the command-line interface.
	 * @param $scriptName The name of the command-line script (displayed as usage info)
	 * @param $args Parameters to the plugin
	 */
	abstract function executeCLI($scriptName, &$args);

	/**
	 * Display the command-line usage information
	 * @param $scriptName string
	 */
	abstract function usage($scriptName);

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		$dispatcher = $request->getDispatcher();
		import('lib.pkp.classes.linkAction.request.RedirectAction');
		return array_merge(
			array(
				new LinkAction(
					'settings',
					new RedirectAction($dispatcher->url(
						$request, ROUTE_PAGE,
						null, 'management', 'importexport', array('plugin', $this->getName())
					)),
					__('manager.importExport'),
					null
				),
			),
			parent::getActions($request, $actionArgs)
		);
	}

	/**
	 * Display the import/export plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function display($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->registerPlugin('function', 'plugin_url', array($this, 'pluginUrl'));
		$this->_request = $request; // Store this for use by the pluginUrl function
		$templateMgr->assign([
			'breadcrumbs' => [
				[
					'id' => 'tools',
					'name' => __('navigation.tools'),
					'url' => $request->getRouter()->url($request, null, 'management', 'tools'),
				],
				[
					'id' => $this->getPluginPath(),
					'name' => $this->getDisplayName()
				],
			],
			'pageTitle' => $this->getDisplayName(),
		]);
	}

	/**
	 * Generate a URL into the plugin.
	 * @see calling conventions at http://www.smarty.net/docsv2/en/api.register.function.tpl
	 * @param $params array
	 * @param $smarty Smarty
	 * @return string
	 */
	function pluginUrl($params, $smarty) {
		$dispatcher = $this->_request->getDispatcher();
		return $dispatcher->url($this->_request, ROUTE_PAGE, null, 'management', 'importexport', array_merge(array('plugin', $this->getName(), isset($params['path'])?$params['path']:array())));
	}

	/**
	 * Check if this is a relative path to the xml document
	 * that describes public identifiers to be imported.
	 * @param $url string path to the xml file
	 */
	function isRelativePath($url) {
		// FIXME This is not very comprehensive, but will work for now.
		if ($this->isAllowedMethod($url)) return false;
		if ($url[0] == '/') return false;
		return true;
	}

	/**
	 * Determine whether the specified URL describes an allowed protocol.
	 * @param $url string
	 * @return boolean
	 */
	function isAllowedMethod($url) {
		$allowedPrefixes = array(
			'http://',
			'ftp://',
			'https://',
			'ftps://'
		);
		foreach ($allowedPrefixes as $prefix) {
			if (substr($url, 0, strlen($prefix)) === $prefix) return true;
		}
		return false;
	}

	/**
	 * Get the plugin ID used as plugin settings prefix.
	 * @return string
	 */
	function getPluginSettingsPrefix() {
		return '';
	}

	/**
	 * Return the plugin export directory.
	 * @return string The export directory path.
	 */
	function getExportPath() {
		return Config::getVar('files', 'files_dir') . '/temp/';
	}

	/**
	 * Return the whole export file name.
	 * @param $basePath string Base path for temporary file storage
	 * @param $objectsFileNamePart string Part different for each object type.
	 * @param $context Context
	 * @param $extension string
	 * @return string
	 */
	function getExportFileName($basePath, $objectsFileNamePart, $context, $extension = '.xml') {
		return $basePath . $this->getPluginSettingsPrefix() . '-' . date('Ymd-His') .'-' . $objectsFileNamePart .'-' . $context->getId() . $extension;
	}

	/**
	 * Display XML validation errors.
	 * @param $errors array
	 * @param $xml string
	 */
	function displayXMLValidationErrors($errors, $xml) {
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER);
		if (defined('SESSION_DISABLE_INIT')) {
			echo __('plugins.importexport.common.validationErrors') . "\n";
			foreach ($errors as $error) {
				echo trim($error->message) . "\n";
			}
			libxml_clear_errors();
			echo __('plugins.importexport.common.invalidXML') . "\n";
			echo $xml . "\n";
		} else {
			$charset = Config::getVar('i18n', 'client_charset');
			header('Content-type: text/html; charset=' . $charset);
			echo '<html><body>';
			echo '<h2>' . __('plugins.importexport.common.validationErrors') . '</h2>';
			foreach ($errors as $error) {
				echo '<p>' . trim($error->message) . '</p>';
			}
			libxml_clear_errors();
			echo '<h3>' . __('plugins.importexport.common.invalidXML') . '</h3>';
			echo '<p><pre>' . htmlspecialchars($xml) . '</pre></p>';
			echo '</body></html>';
		}
		throw new Exception(__('plugins.importexport.common.error.validation'));
	}

}



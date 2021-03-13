<?php

/**
 * @file plugins/importexport/crossref/CrossrefInfoSender.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CrossrefInfoSender
 * @ingroup plugins_importexport_crossref
 *
 * @brief Scheduled task to send deposits to Crossref and update statuses.
 */

import('lib.pkp.classes.scheduledTask.ScheduledTask');


class CrossrefInfoSender extends ScheduledTask {
	/** @var $_plugin CrossRefExportPlugin */
	var $_plugin;

	/**
	 * Constructor.
	 * @param $argv array task arguments
	 */
	function __construct($args) {
		PluginRegistry::loadCategory('importexport');
		$plugin = PluginRegistry::getPlugin('importexport', 'CrossRefExportPlugin'); /* @var $plugin CrossRefExportPlugin */
		$this->_plugin = $plugin;

		if (is_a($plugin, 'CrossRefExportPlugin')) {
			$plugin->addLocaleData();
		}

		parent::__construct($args);
	}

	/**
	 * @copydoc ScheduledTask::getName()
	 */
	function getName() {
		return __('plugins.importexport.crossref.senderTask.name');
	}

	/**
	 * @copydoc ScheduledTask::executeActions()
	 */
	function executeActions() {
		if (!$this->_plugin) return false;

		$plugin = $this->_plugin;
		$servers = $this->_getServers();

		foreach ($servers as $server) {
			$notify = false;

			$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $server->getId());
			$doiPubIdPlugin = $pubIdPlugins['doipubidplugin'];

			if ($doiPubIdPlugin->getSetting($server->getId(), 'enablePublicationDoi')) {
				// Get unregistered preprints
				$unregisteredPreprints = $plugin->getUnregisteredPreprints($server);
				// If there are preprints to be deposited
				if (count($unregisteredPreprints)) {
					$this->_registerObjects($unregisteredPreprints, 'preprint=>crossref-xml', $server, 'preprints');
				}
			}
		}
		return true;
	}

	/**
	 * Get all servers that meet the requirements to have
	 * their preprints or issues DOIs sent to Crossref.
	 * @return array
	 */
	function _getServers() {
		$plugin = $this->_plugin;
		$contextDao = Application::getContextDAO(); /* @var $contextDao ServerDAO */
		$serverFactory = $contextDao->getAll(true);

		$servers = array();
		while($server = $serverFactory->next()) {
			$serverId = $server->getId();
			if (!$plugin->getSetting($serverId, 'username') || !$plugin->getSetting($serverId, 'password') || !$plugin->getSetting($serverId, 'automaticRegistration')) continue;

			$doiPrefix = null;
			$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $serverId);
			if (isset($pubIdPlugins['doipubidplugin'])) {
				$doiPubIdPlugin = $pubIdPlugins['doipubidplugin'];
				if (!$doiPubIdPlugin->getSetting($serverId, 'enabled')) continue;
				$doiPrefix = $doiPubIdPlugin->getSetting($serverId, 'doiPrefix');
			}

			if ($doiPrefix) {
				$servers[] = $server;
			} else {
				$this->addExecutionLogEntry(__('plugins.importexport.common.senderTask.warning.noDOIprefix', array('path' => $server->getPath())), SCHEDULED_TASK_MESSAGE_TYPE_WARNING);
			}
		}
		return $servers;
	}

	/**

	 * Register objects
	 * @param $objects array
	 * @param $filter string
	 * @param $server Server
	 * @param $objectsFileNamePart string
	 */
	function _registerObjects($objects, $filter, $server, $objectsFileNamePart) {
		$plugin = $this->_plugin;
		import('lib.pkp.classes.file.FileManager');
		$fileManager = new FileManager();
		// The new Crossref deposit API expects one request per object.
		// On contrary the export supports bulk/batch object export, thus
		// also the filter expects an array of objects.
		// Thus the foreach loop, but every object will be in an one item array for
		// the export and filter to work.
		foreach ($objects as $object) {
			// export XML
			$exportXml = $plugin->exportXML(array($object), $filter, $server);
			// Write the XML to a file.
			// export file name example: crossref-20160723-160036-preprints-1-1.xml
			$objectsFileNamePartId = $objectsFileNamePart . '-' . $object->getId();
			$exportFileName = $plugin->getExportFileName($plugin->getExportPath(), $objectsFileNamePartId, $server, '.xml');
			$fileManager->writeFile($exportFileName, $exportXml);
			// Deposit the XML file.
			$result = $plugin->depositXML($object, $server, $exportFileName);
			if ($result !== true) {
				$this->_addLogEntry($result);
			}
			// Remove all temporary files.
			$fileManager->deleteByPath($exportFileName);
		}
	}

	/**
	 * Add execution log entry
	 * @param $result array
	 */
	function _addLogEntry($result) {
		if (is_array($result)) {
			foreach($result as $error) {
				assert(is_array($error) && count($error) >= 1);
				$this->addExecutionLogEntry(
					__($error[0], array('param' => (isset($error[1]) ? $error[1] : null))),
					SCHEDULED_TASK_MESSAGE_TYPE_WARNING
				);
			}
		}
	}

}


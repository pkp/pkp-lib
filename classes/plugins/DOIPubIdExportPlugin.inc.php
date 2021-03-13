<?php

/**
 * @file classes/plugins/DOIPubIdExportPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DOIPubIdExportPlugin
 * @ingroup plugins
 *
 * @brief Basis class for DOI XML metadata export plugins
 */

import('classes.plugins.PubObjectsExportPlugin');

// Configuration errors.
define('DOI_EXPORT_CONFIG_ERROR_DOIPREFIX', 0x01);

// The name of the setting used to save the registered DOI.
define('DOI_EXPORT_REGISTERED_DOI', 'registeredDoi');

abstract class DOIPubIdExportPlugin extends PubObjectsExportPlugin {
	/**
	 * @copydoc ImportExportPlugin::display()
	 */
	function display($args, $request) {
		parent::display($args, $request);
		$context = $request->getContext();
		switch (array_shift($args)) {
			case 'index':
			case '':
				$templateMgr = TemplateManager::getManager($request);
				// Check for configuration errors:
				$configurationErrors = $templateMgr->getTemplateVars('configurationErrors');
				// missing DOI prefix
				$doiPrefix = null;
				$pubIdPlugins = PluginRegistry::loadCategory('pubIds', true);
				if (isset($pubIdPlugins['doipubidplugin'])) {
					$doiPlugin = $pubIdPlugins['doipubidplugin'];
					$doiPrefix = $doiPlugin->getSetting($context->getId(), $doiPlugin->getPrefixFieldName());
					$templateMgr->assign(array(
						'exportPreprints' => $doiPlugin->getSetting($context->getId(), 'enablePublicationDoi'),
						'exportRepresentations' => $doiPlugin->getSetting($context->getId(), 'enableRepresentationDoi'),
					));
				}
				if (empty($doiPrefix)) {
					$configurationErrors[] = DOI_EXPORT_CONFIG_ERROR_DOIPREFIX;
				}
				$templateMgr->display($this->getTemplateResource('index.tpl'));
				break;
		}
	}

	/**
	 * Get pub ID type
	 * @return string
	 */
	function getPubIdType() {
		return 'doi';
	}

	/**
	 * Get pub ID display type
	 * @return string
	 */
	function getPubIdDisplayType() {
		return 'DOI';
	}

	/**
	 * Mark selected submissions as registered.
	 * @param $context Context
	 * @param $objects array Array of published submissions or galleys
	 */
	function markRegistered($context, $objects) {
		foreach ($objects as $object) {
			$object->setData($this->getDepositStatusSettingName(), EXPORT_STATUS_MARKEDREGISTERED);
			$this->saveRegisteredDoi($context, $object);
		}
	}

	/**
	 * Saving object's DOI to the object's
	 * "registeredDoi" setting.
	 * We prefix the setting with the plugin's
	 * id so that we do not get name clashes
	 * when several DOI registration plug-ins
	 * are active at the same time.
	 * @param $context Context
	 * @param $object Submission|PreprintGalley
	 * @param $testPrefix string
	 */
	function saveRegisteredDoi($context, $object, $testPrefix = '10.1234') {
		$registeredDoi = $object->getStoredPubId('doi');
		assert(!empty($registeredDoi));
		if ($this->isTestMode($context)) {
			$registeredDoi = PKPString::regexp_replace('#^[^/]+/#', $testPrefix . '/', $registeredDoi);
		}
		$object->setData($this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI, $registeredDoi);
		$this->updateObject($object);
	}

	/**
	 * Get a list of additional setting names that should be stored with the objects.
	 * @return array
	 */
	protected function _getObjectAdditionalSettings() {
		return array_merge(parent::_getObjectAdditionalSettings(), array(
			$this->getPluginSettingsPrefix() . '::' . DOI_EXPORT_REGISTERED_DOI
		));
	}

	/**
	 * Retrieve all unregistered preprints.
	 * @param $context Context
	 * @return array
	 */
	function getUnregisteredPreprints($context) {
		// Retrieve all published submissions that have not yet been registered.
		$submissionDao = DAORegistry::getDAO('SubmissionDAO'); /* @var $submissionDao SubmissionDAO */
		$preprints = $submissionDao->getExportable(
			$context->getId(),
			$this->getPubIdType(),
			null,
			null,
			$this->getPluginSettingsPrefix(). '::' . DOI_EXPORT_REGISTERED_DOI,
			null,
			null
		);
		return $preprints->toArray();
	}

	/**
	 * Retrieve all unregistered galleys.
	 * @param $context Context
	 * @return array
	 */
	function getUnregisteredGalleys($context) {
		// Retrieve all galleys that have not yet been registered.
		$galleyDao = DAORegistry::getDAO('PreprintGalleyDAO'); /* @var $galleyDao PreprintGalleyDAO */
		$galleys = $galleyDao->getExportable(
			$context?$context->getId():null,
			$this->getPubIdType(),
			null,
			null,
			null,
			$this->getPluginSettingsPrefix(). '::' . DOI_EXPORT_REGISTERED_DOI,
			null,
			null
		);
		return $galleys->toArray();
	}

	/**
	 * Get published submissions with a DOI assigned from submission IDs.
	 * @param $submissionIds array
	 * @param $context Context
	 * @return array
	 */
	function getPublishedSubmissions($submissionIds, $context) {
		$submissions = array_map(function($submissionId) {
			return Services::get('submission')->get($submissionId);
		}, $submissionIds);
		return array_filter($submissions, function($submission) {
			return $submission->getData('status') === STATUS_PUBLISHED && !!$submission->getStoredPubId('doi');
		});
	}

	/**
	 * Get preprint galleys with a DOI assigned from gallley IDs.
	 * @param $galleyIds array
	 * @return array
	 */
	function getPreprintGalleys($galleyIds) {
		$galleys = array();
		$preprintGalleyDao = DAORegistry::getDAO('PreprintGalleyDAO');
		foreach ($galleyIds as $galleyId) {
			$preprintGalley = $preprintGalleyDao->getById($galleyId);
			if ($preprintGalley && $preprintGalley->getStoredPubId('doi')) $galleys[] = $preprintGalley;
		}
		return $galleys;
	}
}



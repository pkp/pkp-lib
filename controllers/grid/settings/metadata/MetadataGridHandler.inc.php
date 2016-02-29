<?php

/**
 * @file controllers/grid/settings/metadata/MetadataGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MetadataGridHandler
 * @ingroup controllers_grid_settings_metadata
 *
 * @brief Handle metadata grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.settings.metadata.MetadataGridCellProvider');

class MetadataGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function MetadataGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			ROLE_ID_MANAGER,
			array('saveMetadataSetting')
		);
	}


	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load user-related translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_READER
		);

		// Basic grid configuration.
		$this->setTitle('submission.metadata');

		$cellProvider = new MetadataGridCellProvider($request->getContext());

		// Field name.
		$this->addColumn(
			new GridColumn(
				'name',
				'common.name',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('width' => 60)
			)
		);

		$this->addColumn(
			new GridColumn(
				'submission',
				'submission.submission',
				null,
				'controllers/grid/common/cell/selectStatusCell.tpl',
				$cellProvider,
				array('alignment' => 'center')
			)
		);

		$this->addColumn(
			new GridColumn(
				'workflow',
				'manager.workflow',
				null,
				'controllers/grid/common/cell/selectStatusCell.tpl',
				$cellProvider,
				array('alignment' => 'center')
			)
		);
	}

	/**
	 * Get the list of configurable metadata fields.
	 */
	static function getNames() {
		return array(
			'coverage' => array('name' => __('rt.metadata.dublinCore.coverage')),
			'languages' => array('name' => __('rt.metadata.dublinCore.language')),
			'rights' => array('name' => __('rt.metadata.dublinCore.rights')),
			'source' => array('name' => __('rt.metadata.dublinCore.source')),
			'subject' => array('name' => __('rt.metadata.dublinCore.subject')),
			'type' => array('name' => __('rt.metadata.dublinCore.type')),
			'disciplines' => array('name' => __('rt.metadata.pkp.discipline')),
			'keywords' => array('name' => __('rt.metadata.pkp.subject')),
			'agencies' => array('name' => __('submission.supportingAgencies')),
			'references' => array('name' => __('submission.citations')),
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		return $this->getNames();
	}

	//
	// Public handler methods.
	//
	/**
	 * Save metadata settings.
	 * @param $args array
	 * @param $request Request
	 * @return JSONObject JSON message
	 */
	function saveMetadataSetting($args, $request) {
		$field = (string) $request->getUserVar('rowId');
		$settingName = (string) $request->getUserVar('setting');
		$settingValue = (boolean) $request->getUserVar('value');
die('FIXME');
		$availableLocales = $this->getGridDataElements($request);
		$context = $request->getContext();

		$permittedSettings = array('supportedFormLocales', 'supportedSubmissionLocales', 'supportedLocales');
		if (in_array($settingName, $permittedSettings) && $locale) {
			$currentSettingValue = (array) $context->getSetting($settingName);
			if (AppLocale::isLocaleValid($locale) && array_key_exists($locale, $availableLocales)) {
				if ($settingValue) {
					array_push($currentSettingValue, $locale);
				} else {
					$key = array_search($locale, $currentSettingValue);
					if ($key !== false) unset($currentSettingValue[$key]);
				}
			}
		}

		$context->updateSetting($settingName, $currentSettingValue);

		$notificationManager = new NotificationManager();
		$user = $request->getUser();
		$notificationManager->createTrivialNotification(
			$user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.localeSettingsSaved')));

		return DAO::getDataChangedEvent($locale);
	}
}

?>

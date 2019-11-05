<?php
/**
 * @file classes/services/PKPAnnouncementService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for publications
 */

namespace PKP\Services;

use \Core;
use \DAOResultFactory;
use \DAORegistry;
use \DBResultRange;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\interfaces\EntityWriteInterface;
use PKP\Services\QueryBuilders\PKPAnnouncementQueryBuilder;
use \PKP\Services\traits\EntityReadTrait;

import('lib.pkp.classes.db.DBResultRange');

class PKPAnnouncementService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface {
	use EntityReadTrait;

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($announcementId) {
		return DAORegistry::getDAO('AnnouncementDAO')->getById($announcementId);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMany()
	 */
	public function getMany($args = null) {
		$announcementQB = $this->_getQueryBuilder($args);
		$announcementQO = $announcementQB->get();
		$range = $this->getRangeByArgs($args);
		$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
		$result = $announcementDao->retrieveRange($announcementQO->toSql(), $announcementQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $announcementDao, '_fromRow');

		return $queryResults->toIterator();

	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = null) {
		$announcementQB = $this->_getQueryBuilder($args);
		$countQO = $announcementQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$announcementDao = DAORegistry::getDAO('AnnouncementDAO');
		$countResult = $announcementDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);

		return (int) $countResult->Fields('count');
	}

	/**
	 * Compile the filter params for the query builder
	 *
	 * @param array $args
	 * @return object query object
	 */
	private function _getQueryBuilder($args = []) {

		$defaultArgs = [
			'contextIds' => null,
			'typeIds' => null,
		];

		$args = array_merge($defaultArgs, $args);

		$announcementQB = new PKPAnnouncementQueryBuilder();
		if (!empty($args['contextIds'])) {
			$announcementQB->filterByContextIds($args['contextIds']);
		}
		if (!empty($args['typeIds'])) {
			$announcementQB->filterByTypeIds($args['typeIds']);
		}

		\HookRegistry::call('Announcement::getMany::queryBuilder', [$announcementQB, $args]);

		return $announcementQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($announcement, $props, $args = null) {
		$request = $args['request'];
		$announcementContext = $args['announcementContext'];
		$dispatcher = $request->getDispatcher();

		$values = [];

		foreach ($props as $prop) {
			switch ($prop) {
				case '_href':
					$values[$prop] = $dispatcher->url(
						$request,
						ROUTE_API,
						$announcementContext->getData('urlPath'),
						'announcements/' . $announcement->getId()
					);
					break;
				default:
					$values[$prop] = $announcement->getData($prop);
					break;
			}
		}

		\HookRegistry::call('Announcement::getProperties', [&$values, $announcement, $props, $args]);

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($announcement, $args = null) {
		$props = Services::get('schema')->getSummaryProps(SCHEMA_ANNOUNCEMENT);

		return $this->getProperties($announcement, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($announcement, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_ANNOUNCEMENT);

		return $this->getProperties($announcement, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityWriteInterface::validate()
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
		\AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);
		$schemaService = Services::get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_ANNOUNCEMENT, $allowedLocales),
			[
				'dateExpire.date_format' => __('stats.dateRange.invalidDate'),
			]
		);

		// Check required fields if we're adding a context
		if ($action === VALIDATE_ACTION_ADD) {
			\ValidatorFactory::required(
				$validator,
				$schemaService->getRequiredProps(SCHEMA_ANNOUNCEMENT),
				$schemaService->getMultilingualProps(SCHEMA_ANNOUNCEMENT),
				$primaryLocale
			);
		}

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_ANNOUNCEMENT), $allowedLocales);

		// Don't allow an empty value for the primary locale of the title field
		\ValidatorFactory::requirePrimaryLocale(
			$validator,
			['title'],
			$props,
			$allowedLocales,
			$primaryLocale
		);

		if ($validator->fails()) {
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_ANNOUNCEMENT), $allowedLocales);
		}

		\HookRegistry::call('Announcement::validate', array(&$errors, $action, $props, $allowedLocales, $primaryLocale));

		return $errors;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::add()
	 */
	public function add($announcement, $request) {
		$announcement->setData('datePosted', Core::getCurrentDate());
		DAORegistry::getDao('AnnouncementDAO')->insertObject($announcement);
		\HookRegistry::call('Announcement::add', [$announcement, $request]);

		return $announcement;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($announcement, $params, $request) {

		$newAnnouncement = DAORegistry::getDAO('AnnouncementDAO')->newDataObject();
		$newAnnouncement->_data = array_merge($announcement->_data, $params);

		\HookRegistry::call('Announcement::edit', array($newAnnouncement, $announcement, $params, $request));

		DAORegistry::getDAO('AnnouncementDAO')->updateObject($newAnnouncement);
		$newAnnouncement = $this->get($newAnnouncement->getId());

		return $newAnnouncement;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($announcement) {
		\HookRegistry::call('Announcement::delete::before', array($announcement));
		DAORegistry::getDao('AnnouncementDAO')->deleteObject($announcement);
		\HookRegistry::call('Announcement::delete', array($announcement));
	}
}

<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/Report.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Report
 * @ingroup lib_pkp_classes_user
 *
 * @brief Responsible to retrieve and provide users data to the Mapping objects.
 */

namespace PKP\User\Report;

import('lib.pkp.classes.user.UserDAO');

class Report implements \IteratorAggregate {
	/** @var Mapping[] An array of Mapping objects responsible to feed the report's header and body */
	private $_mappings = [];

	/** @var QueryBuilder QueryBuilder instance */
	private $_queryBuilder;

	/**
	 * Constructor
	 * @param bool $addDefaultMappings Whether default mappings should be automatically added (defaults to true)
	 */
	public function __construct(?bool $addDefaultMappings = true)
	{
		$this->_queryBuilder = new QueryBuilder();

		if ($addDefaultMappings) {
			new Mappings\Standard($this);
			new Mappings\UserGroups($this);
			new Mappings\Notifications($this);
		}
	}

	/**
	 * Retrieves the data mappings
	 * @return Mapping[] A list of Mapping objects
	 */
	public function getMappings(): array
	{
		return $this->_mappings;
	}

	/**
	 * Replaces the data mappings
	 * @param Mapping[] $mappings A list of Mapping objects
	 * @return $this
	 */
	public function setMappings(array $mappings): self
	{
		$this->_mappings = $mappings;
		return $this;
	}

	/**
	 * Appends data mappings
	 * @param Mapping ...$mappings A list of Mapping objects
	 * @return $this
	 */
	public function addMappings(Mapping ...$mappings): self
	{
		array_push($this->_mappings, ...$mappings);
		return $this;
	}

	/**
	 * Implements the IteratorAggregate interface
	 * @return Traversable A data row generator, which yields each user
	 */
	public function getIterator(): \Traversable
	{
		// Outputs each user
		foreach ($this->_queryBuilder->getQuery()->get() as $userRecord) {
			yield $this->_getRawData($userRecord);
		}
	}

	/**
	 * Retrieves the query builder
	 * @return QueryBuilder
	 */
	public function getQueryBuilder(): QueryBuilder
	{
		return $this->_queryBuilder;
	}

	/**
	 * Converts a raw user record from the QueryBuilder to an User model
	 * @param object $userRecord The database row
	 * @return \User
	 */
	private function _toUserEntity(object $userRecord): \User
	{
		$userDao = \DAORegistry::getDAO('UserDAO');
		$user = $userDao->_returnUserFromRow((array) $userRecord);
		$user->setGivenName($userRecord->user_given, null);
		$user->setFamilyName($userRecord->user_family, null);

		foreach(['orcid', 'biography', 'signature', 'affiliation', 'preferredPublicName'] as $setting) {
			$identifier = strtolower($setting);
			$user->setData($setting, $userDao->convertFromDB($userRecord->$identifier, $userRecord->{$identifier . '_type'}));
		}

		return $user;
	}

	/**
	 * Retrieves the report headings
	 * @return string[]
	 */
	public function getHeadings(): array
	{
		return array_map(
			function (Mapping $mapping): ?string
			{
				return $mapping->getCaption();
			},
			$this->_mappings
		);
	}

	/**
	 * Retrieves a report data row
	 * @param object $userRecord The database row
	 * @return string[]
	 */	
	private function _getRawData(object $userRecord): array
	{
		$user = $this->_toUserEntity($userRecord);
		return array_map(
			function (Mapping $mapping) use ($user, $userRecord): ?string
			{
				return $mapping($user, $userRecord);
			},
			$this->_mappings
		);
	}
}

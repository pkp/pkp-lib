<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/mappings/UserGroups.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class UserGroups
 * @ingroup lib_pkp_classes_user
 *
 * @brief Retrieves a list of Mapping objects to display the user roles.
 */

namespace PKP\User\Report\Mappings;
use PKP\User\Report\{Report, Mapping, QueryBuilder, QueryOptions, QueryListenerInterface};
use Illuminate\Database\Capsule\Manager as Capsule;

class UserGroups implements QueryListenerInterface {
	/** @var array A dictionary [id => name] containing all user groups available in the application */
	private $_userGroups;

	/**
	 * Constructor
	 * @param Report $report
	 */
	public function __construct(Report $report)
	{
		$this->_loadUserGroups();
		$report->getQueryBuilder($this);
		$report->addMappings(...$this->_getMappings());
	}

	/**
	 * @copydoc QueryListenerInterface::onQuery()
	 */
	public function onQuery(\Illuminate\Database\Query\Builder $query, QueryOptions $options): void
	{
		switch (Capsule::connection()->getDriverName()) {
			case 'mysql':
				$options->columns[] = Capsule::raw("GROUP_CONCAT(DISTINCT CONCAT('[', uug.user_group_id, ']')) AS user_groups");
				break;
			default:
				$options->columns[] = Capsule::raw("STRING_AGG(DISTINCT CONCAT('[', uug.user_group_id, ']'), ',') AS user_groups");
				break;
		}
	}

	/**
	 * Retrieves the user group mappings
	 * @return Mapping[] A list of Mapping objects
	 */
	private function _getMappings(): array
	{
		$mappings = [];
		foreach ($this->_userGroups as $userGroupId => $name) {
			array_push(
				$mappings,
				new Mapping(
					$name,
					function (\User $user, object $userRecord) use ($userGroupId): string
					{
						return $this->_getStatus($userRecord, $userGroupId);
					}
				)
			);
		}
		return $mappings;
	}

	/**
	 * Loads the existing user groups from the system
	 */
	private function _loadUserGroups(): void
	{
		$userGroups = [];
		foreach(\DAORegistry::getDAO('UserGroupDAO')->getByContextId()->toIterator() as $userGroup) {
			$userGroups[$userGroup->getId()] = $userGroup->getLocalizedName();
		}

		$this->_userGroups = $userGroups;
	}

	/**
	 * Retrieves whether the user has the given user group
	 * @param object $userRecord The user object
	 * @param int $userGroupId The user group ID
	 * @return string Localized text with Yes or No
	 */
	private static function _getStatus(object $userRecord, int $userGroupId): string
	{
		return __(is_int(strpos($userRecord->user_groups, '[' . $userGroupId . ']')) ? 'common.yes' : 'common.no');
	}
}

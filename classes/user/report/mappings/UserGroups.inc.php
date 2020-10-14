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
use PKP\User\Report\{Report, Mapping};
use Illuminate\Database\Capsule\Manager as Capsule;

class UserGroups {
	/** @var array A dictionary [id => name] containing all user groups available in the application */
	private $_userGroups;

	/**
	 * Constructor
	 * @param Report $report
	 */
	public function __construct(Report $report)
	{
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR);
		$this->_loadUserGroups();
		$report->addMappings(...$this->_getMappings());
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
					function (\User $user) use ($userGroupId): string
					{
						return $this->_getStatus($user, $userGroupId);
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
		foreach (\DAORegistry::getDAO('UserGroupDAO')->getByContextId()->toIterator() as $userGroup) {
			$userGroups[$userGroup->getId()] = $userGroup->getLocalizedName();
		}

		$this->_userGroups = $userGroups;
	}

	/**
	 * Retrieves whether the user has the given user group
	 * @param \User $user The user object
	 * @param int $userGroupId The user group ID
	 * @return string Localized text with Yes or No
	 */
	private static function _getStatus(\User $user, int $userGroupId): string
	{
		static $lastUserId = null;
		static $groups = null;

		if ($lastUserId != $user->getId()) {
			['groups' => $groups] = \Services::get('user')->getProperties($user, ['groups'], ['request' => \Application::get()->getRequest()]);
			$lastUserId = $user->getId();
		}

		foreach ($groups as ['id' => $id]) {
			if ($id == $userGroupId) {
				return __('common.yes');
			}
		}

		return __('common.no');
	}
}

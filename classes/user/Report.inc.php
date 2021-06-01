<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/Report.inc.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Report
 * @ingroup lib_pkp_classes_user
 *
 * @brief Generates a CSV report with basic user information given a list of users and an output stream.
 */

namespace PKP\User;

class Report {
	/** @var iterable The report data source, should yield /User objects */
	private $_dataSource;

	/**
	 * Constructor
	 * @param iterable $dataSource The data source, should yield /User objects
	 */
	public function __construct(iterable $dataSource) {
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_COMMON);
		$this->_dataSource = $dataSource;
		
	}

	/**
	 * Serializes the report to the given output
	 * @param resource $output A ready to write stream
	 */
	public function serialize($output) : void {
		// Adds BOM (byte order mark) to enforce the UTF-8 format
		fwrite($output, "\xEF\xBB\xBF");

		// Outputs column headings
		fputcsv($output, $this->_getHeadings());

		// Outputs each user
		foreach ($this->_dataSource as $user) {
			fputcsv($output, $this->_getDataRow($user));
		}
	}

	/**
	 * Retrieves the report headings
	 * @return string[]
	 */
	private function _getHeadings() : array {
		return array_merge([
			__('common.id'),
			__('user.givenName'),
			__('user.familyName'),
			__('user.email'),
			__('user.phone'),
			__('common.country'),
			__('common.mailingAddress'),
			__('user.dateRegistered'),
			__('common.updated'),
			], array_map(function($userGroup) {
				return $userGroup->getLocalizedName();
			}, $this->_getUserGroups())
		);
	}

	/**
	 * Retrieves the report row
	 * @param \User $user
	 * @return string[]
	 */
	private function _getDataRow(\User $user) : array {
		$userGroups = \Services::get('user')->getProperties($user, ['groups'], ['request' => \Application::get()->getRequest()])['groups'];
		$groups = [];
		foreach ($userGroups as ['id' => $id]) {
			$groups[$id] = 0;
		}

		return array_merge([
			$user->getId(),
			$user->getLocalizedGivenName(),
			$user->getFamilyName(\AppLocale::getLocale()),
			$user->getEmail(),
			$user->getPhone(),
			$user->getCountryLocalized(),
			$user->getMailingAddress(),
			$user->getDateRegistered(),
			$user->getLocalizedData('dateProfileUpdated'),
			],
                        array_map(function($userGroup) use ($groups) {
				return __(isset($groups[$userGroup->getId()]) ? 'common.yes' : 'common.no');
			}, $this->_getUserGroups())
		);
	}

	/**
	 * Retrieves the user groups
	 * @return array
	 */
	private function _getUserGroups() : array {
		static $cache = null;
		return $cache ?? $cache = iterator_to_array(\DAORegistry::getDAO('UserGroupDAO')->getByContextId()->toIterator());
	}
}

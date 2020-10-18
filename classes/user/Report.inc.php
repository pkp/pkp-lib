<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/Report.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Report
 * @ingroup lib_pkp_classes_user
 *
 * @brief Generates a CSV report with basic user information given a list of users and an output stream.
 */

namespace PKP\User;

class Report {
	/** @var array An array of mappings. Each mapping is an array with two elements where:
	 * - First: string containing the column header
	 * - Second: callable receiving an \User and returning a ?string
	 */
	private $_mappings = [];

	/** @var iterable The report data source, should yield /User objects */
	private $_dataSource;

	/**
	 * Constructor
	 * @param iterable $dataSource The data source, should yield /User objects
	 */
	public function __construct(iterable $dataSource)
	{
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR, LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_COMMON);
		$this->_dataSource = $dataSource;
		$this->addMappings($this->_getDefaultMappings());
		$this->addMappings($this->_getUserGroupMappings());
	}

	/**
	 * Retrieves the data mappings
	 * @return array A list of mappings
	 */
	public function getMappings(): array
	{
		return $this->_mappings;
	}

	/**
	 * Replaces the data mappings
	 * @param array $mappings A list of mappings
	 * @return $this
	 */
	public function setMappings(array $mappings): self
	{
		$this->_mappings = $mappings;
		return $this;
	}

	/**
	 * Appends mappings
	 * @param array $mappings A list of mappings
	 * @return $this
	 */
	public function addMappings(array $mappings): self
	{
		array_push($this->_mappings, ...$mappings);
		return $this;
	}

	/**
	 * Serializes the report to the given output
	 * @param resource $output A ready to write stream
	 */
	public function serialize($output): void
	{
		// Adds BOM (byte order mark) to enforce the UTF-8 format
		fwrite($output, "\xEF\xBB\xBF");

		// Outputs column headings
		fputcsv($output, array_map(
			function(?string $heading): ?string
			{
				return \PKPString::html2text($heading);
			},
			$this->getHeadings()
		));

		// Outputs each user
		foreach ($this->_dataSource as $user) {
			fputcsv($output, array_map(
				function (?string $data): ?string
				{
					return \PKPString::html2text($data);
				},
				$this->_getDataRow($user)
			));
		}
	}

	/**
	 * Retrieves the report headings
	 * @return string[]
	 */
	public function getHeadings(): array
	{
		return array_map(
			function (array $mapping): ?string
			{
				return reset($mapping);
			},
			$this->_mappings
		);
	}

	/**
	 * Retrieves a report data row
	 * @param \User $user An user instance
	 * @return string[]
	 */
	private function _getDataRow(\User $user): array
	{
		return array_map(
			function (array $mapping) use ($user): ?string
			{
				return end($mapping)($user);
			},
			$this->_mappings
		);
	}

	/**
	 * Retrieves the default mappings
	 * @return array
	 */	
	private function _getDefaultMappings(): array
	{
		return [
			[
				__('common.id'),
				function (\User $user): ?string
				{
					return $user->getId();
				}
			],
			[
				__('user.givenName'),
				function (\User $user): ?string
				{
					return $user->getLocalizedGivenName();
				}
			],
			[
				__('user.familyName'),
				function (\User $user): ?string
				{
					return $user->getFamilyName(\AppLocale::getLocale());
				}
			],
			[
				__('user.email'),
				function (\User $user): ?string
				{
					return $user->getEmail();
				}
			],
			[
				__('user.phone'),
				function (\User $user): ?string
				{
					return $user->getPhone();
				}
			],
			[
				__('common.country'),
				function (\User $user): ?string
				{
					return $user->getCountryLocalized();
				}
			],
			[
				__('common.mailingAddress'),
				function (\User $user): ?string
				{
					return $user->getMailingAddress();
				}
			],
			[
				__('user.dateRegistered'),
				function (\User $user): ?string
				{
					return $user->getDateRegistered();
				}
			],
			[
				__('common.updated'),
				function (\User $user): ?string
				{
					return $user->getLocalizedData('dateProfileUpdated');
				}
			],
		];
	}

	/**
	 * Retrieves the user group mappings
	 * @return array
	 */
	private function _getUserGroupMappings(): array
	{
		$mappings = [];
		$cache = (object) [
			'lastUserId' => null,
			'groups' => null
		];
		foreach (\DAORegistry::getDAO('UserGroupDAO')->getByContextId()->toIterator() as $userGroup) {
			$mappings[] = [
				$userGroup->getLocalizedName(),
				function (\User $user) use ($userGroup, $cache): string
				{
					if ($cache->lastUserId != $user->getId()) {
						['groups' => $cache->groups] = \Services::get('user')->getProperties($user, ['groups'], ['request' => \Application::get()->getRequest()]);
						$cache->lastUserId = $user->getId();
					}
			
					foreach ($cache->groups as ['id' => $id]) {
						if ($id == $userGroup->getId()) {
							return __('common.yes');
						}
					}
			
					return __('common.no');
				}
			];
		}
		return $mappings;
	}
}

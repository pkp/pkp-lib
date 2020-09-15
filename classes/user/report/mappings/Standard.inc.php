<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/mappings/Standard.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Standard
 * @ingroup lib_pkp_classes_user
 *
 * @brief Retrieves a list of default Mapping objects.
 */

namespace PKP\User\Report\Mappings;
use PKP\User\Report\{Report, Mapping};

class Standard {
	/**
	 * Constructor
	 * @param Report $report
	 */
	public function __construct(Report $report)
	{
		$report->addMappings(...$this->_getMappings());
	}

	/**
	 * Retrieves the default mappings
	 * @return Mapping[] A list of Mapping objects
	 */
	private static function _getMappings(): array
	{
		// Loads required locales
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_COMMON);
		
		return [
			new Mapping(__('common.id'), function (\User $user, object $userRecord): ?string
			{
				return $user->getId();
			}),
			new Mapping(__('user.givenName'), function (\User $user, object $userRecord): ?string
			{
				return $user->getLocalizedGivenName();
			}),
			new Mapping(__('user.familyName'), function (\User $user, object $userRecord): ?string
			{
				return $user->getFamilyName(null);
			}),
			new Mapping(__('user.email'), function (\User $user, object $userRecord): ?string
			{
				return $user->getEmail();
			}),
			new Mapping(__('user.phone'), function (\User $user, object $userRecord): ?string
			{
				return $user->getPhone();
			}),
			new Mapping(__('common.country'), function (\User $user, object $userRecord): ?string
			{
				return $user->getCountryLocalized();
			}),
			new Mapping(__('common.mailingAddress'), function (\User $user, object $userRecord): ?string
			{
				return $user->getMailingAddress();
			}),
			new Mapping(__('user.dateRegistered'), function (\User $user, object $userRecord): ?string
			{
				return $user->getDateRegistered();
			}),
			new Mapping(__('common.updated'), function (\User $user, object $userRecord): ?string
			{
				return $user->getLocalizedData('dateProfileUpdated');
			})
		];
	}
}

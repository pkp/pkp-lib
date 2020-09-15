<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/QueryBuilder.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class QueryBuilder
 * @ingroup lib_pkp_classes_user
 *
 * @brief Builds the query to retrieve the users.
 */

namespace PKP\User\Report;
use \PKP\Services\QueryBuilders\PKPUserQueryBuilder;
use Illuminate\Database\Capsule\Manager as Capsule;

class QueryBuilder extends PKPUserQueryBuilder {
	/** @var int[] User group IDs */
	private $_userGroupIds = null;

	/** @var callable[] List of listeners that will be called before the query is dispatched */
	private $_listeners = [];

	/**
	 * Sets the user group filter
	 * @param int[] $userGroupIds
	 * @return $this
	 */
	public function filterByUserGroup(array $userGroupIds): self
	{
		$this->_userGroupIds = $userGroupIds;
		return $this;
	}

	/**
	 * @inheritDoc
	 */
	public function getQuery(): \Illuminate\Database\Query\Builder
	{
		$appLocale = \AppLocale::getLocale();
		// The users register for the site, thus the site primary locale should be the default locale
		$site = \Application::get()->getRequest()->getSite();
		$primaryLocale = $site->getPrimaryLocale();

		$query = parent::getQuery();
		$options = new QueryOptions();
		$options->columns = $this->columns;

		if (count($this->_userGroupIds) > 0) {
			$query->whereIn('ug.user_group_id', $this->_userGroupIds);
		}

		// Inlining the settings to avoid issuing extra queries
		$settings = ['orcid' => false, 'biography' => true, 'signature' => true, 'affiliation' => true, 'preferredPublicName' => true];
		foreach ($settings as $setting => $isLocalized) {
			$identifier = strtolower($setting);
			$locales = ['' => $appLocale];
			if ($isLocalized) {
				$locales['_pl'] = $primaryLocale;
			}
			foreach ($locales as $suffix => $locale) {
				$entity = $identifier . $suffix;
				$query->leftJoin(
					'user_settings AS ' . $entity,
					function ($join) use ($setting, $entity, $locale, $isLocalized) {
						$join
							->on($entity . '.user_id', 'u.user_id')
							->where($entity . '.setting_name', $setting);
						if ($isLocalized) {
							$join->where($entity . '.locale', $locale);
						}
					}
				);
			}

			// If it's a localized setting, the preference is given to the AppLocale
			if ($isLocalized) {
				array_push($options->columns,
					Capsule::raw(sprintf('CASE WHEN %1$s.setting_value <> \'\' THEN %1$s.setting_value ELSE %1$s_pl.setting_value END AS %1$s', $identifier)),
					Capsule::raw(sprintf('COALESCE(%1$s.setting_type, %1$s_pl.setting_type) AS %1$s_type', $identifier))
				);
			}
			else {
				array_push($options->columns,
					Capsule::raw(sprintf('%1$s.setting_value AS %1$s', $identifier)),
					Capsule::raw(sprintf('%1$s.setting_type AS %1$s_type', $identifier))
				);
			}
			array_push($options->groupBy, $identifier, $identifier . '_type');
		}

		foreach($this->_listeners as $listener) {
			$listener->onQuery($query, $options);
		}

		return $query
			->select($options->columns)
			->groupBy(...$options->groupBy);
	}

	/**
	 * Adds a new listener, will be called right before executing the getQuery method
	 * @param QueryListenerInterface $listener
	 * @return $this
	 */
	public function addListener(QueryListenerInterface $listener): self
	{
		$this->_listeners[] = $listener;
		return $this;
	}
}

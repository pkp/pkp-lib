<?php
/**
 * @file classes/services/QueryBuilders/PKPEmailTemplateQueryBuilder.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailTemplateQueryBuilder
 * @ingroup query_builders
 *
 * @brief Base class for context (journals/presses) list query builder
 */

namespace PKP\Services\QueryBuilders;

use Illuminate\Database\Capsule\Manager as Capsule;

class PKPEmailTemplateQueryBuilder extends BaseQueryBuilder {

	/** @var integer journal or press ID */
	protected $contextId = null;

	/** @var boolean enabled or disabled contexts */
	protected $isEnabled = null;

	/** @var array filter by sender role IDs */
	protected $fromRoleIds = [];

	/** @var array filter by recipient role IDs */
	protected $toRoleIds = [];

	/** @var array filter by email keys */
	protected $keys = [];

	/** @var string search phrase */
	protected $searchPhrase = null;

	/**
	 * Set context filter
	 *
	 * @param $contextId integer
	 *
	 * @return \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder
	 */
	public function filterByContext($contextId) {
		$this->contextId = $contextId;
		return $this;
	}

	/**
	 * Set isEnabled filter
	 *
	 * @param $isEnabled boolean
	 *
	 * @return \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder
	 */
	public function filterByIsEnabled($isEnabled) {
		$this->isEnabled = $isEnabled;
		return $this;
	}

	/**
	 * Set sender roles filter
	 *
	 * @param $fromRoleIds array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder
	 */
	public function filterByFromRoleIds($fromRoleIds) {
		$this->fromRoleIds = $fromRoleIds;
		return $this;
	}

	/**
	 * Set recipient roles filter
	 *
	 * @param $toRoleIds array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder
	 */
	public function filterByToRoleIds($toRoleIds) {
		$this->toRoleIds = $toRoleIds;
		return $this;
	}

	/**
	 * Set email keys filter
	 *
	 * @param $keys array
	 *
	 * @return \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder
	 */
	public function filterByKeys($keys) {
		$this->keys = $keys;
		return $this;
	}

	/**
	 * Set query search phrase
	 *
	 * @param $phrase string
	 *
	 * @return \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder
	 */
	public function searchPhrase($phrase) {
		$this->searchPhrase = $phrase;
		return $this;
	}

	/**
	 * Execute query builder
	 *
	 * @return object Query object
	 */
	public function get() {
		$this->columns = [
			'etd.can_disable',
			'etd.can_edit',
			Capsule::raw('COALESCE(etd.email_key, et.email_key) as email_key'),
			'etd.from_role_id',
			'etd.to_role_id',
			'et.email_id',
			'et.assoc_type',
			'et.assoc_id',
			'et.enabled',
		];

		$q = Capsule::table('email_templates_default as etd')
			->orderBy('email_key', 'asc')
			->groupBy('etd.email_key')
			->groupBy('etd.can_disable')
			->groupBy('etd.can_edit')
			->groupBy('etd.from_role_id')
			->groupBy('etd.to_role_id')
			->groupBy('et.email_id');

		if (!is_null($this->contextId)) {
			$contextId = $this->contextId;
			$q->leftJoin('email_templates as et', function ($table) use ($contextId) {
				$table->on('etd.email_key', '=', 'et.email_key')
					->on('et.assoc_type', '=', Capsule::raw(\Application::getContextAssocType()))
					->on('et.assoc_id', '=', Capsule::raw((int) $contextId));
			});
		} else {
			$q->leftJoin('email_templates as et', 'etd.email_key', '=', 'et.email_key');
		}

		// Use a UNION to ensure the query will match rows in email_templates and
		// email_templates_default. This ensures that custom templates which have
		// no default in email_templates_default are still returned. These templates
		// should not be returned when a role filter is used.
		if (empty($this->fromRoleIds) && empty($this->toRoleIds)) {
			$customTemplates = Capsule::table('email_templates as et')
				->leftJoin('email_templates_default as etd', 'etd.email_key', '=', 'et.email_key');
		}

		if (!is_null($this->contextId)) {
			$contextId = $this->contextId;
			$q->where(function($q) use ($contextId) {
				$q->whereNull('et.assoc_type')
					->orWhere(function($q) use ($contextId) {
						$q->where('et.assoc_type', '=', \Application::getContextAssocType());
						$q->where('et.assoc_id', '=', $this->contextId);
					});
			});
			if (isset($customTemplates)) {
				$customTemplates->where(function($customTemplates) use ($contextId) {
					$customTemplates->whereNull('et.assoc_type')
						->orWhere(function($customTemplates) use ($contextId) {
							$customTemplates->where('et.assoc_type', '=', \Application::getContextAssocType());
							$customTemplates->where('et.assoc_id', '=', $this->contextId);
						});
				});
			}
		}

		if (!empty($this->isEnabled)) {
			$q->where(function($q) {
				// Unmodified default templates are considered enabled
				$q->whereNull('et.enabled')
					->orWhere('et.enabled', '=', 1);
			});
			if (isset($customTemplates)) {
				$customTemplates->where('et.enabled', '=', 1);
			}
		} elseif ($this->isEnabled === false) {
			$q->where('et.enabled', '!=', 1);
			if (isset($customTemplates)) {
				$customTemplates->where('et.enabled', '!=', 1);
			}
		}

		if (!empty($this->fromRoleIds)) {
			$q->whereIn('etd.from_role_id', $this->fromRoleIds);
		}

		if (!empty($this->toRoleIds)) {
			$q->whereIn('etd.to_role_id', $this->toRoleIds);
		}

		// search phrase
		if (!empty($this->searchPhrase)) {
			$words = explode(' ', $this->searchPhrase);
			if (count($words)) {
				$q->leftJoin('email_templates_settings as ets', 'et.email_id', '=', 'ets.email_id');
				$q->leftJoin('email_templates_default_data as etddata', 'etd.email_key', '=', 'etddata.email_key');
				foreach ($words as $word) {
					$word = strtolower(addcslashes($word, '%_'));
					$q->where(function($q) use ($word) {
						$q->where(Capsule::raw('lower(et.email_key)'), 'LIKE', "%{$word}%")
							->orWhere(function($q) use ($word) {
								$q->where('ets.setting_name', 'subject');
								$q->where(Capsule::raw('lower(ets.setting_value)'), 'LIKE', "%{$word}%");
							})
							->orWhere(function($q) use ($word) {
								$q->where('ets.setting_name', 'body');
								$q->where(Capsule::raw('lower(ets.setting_value)'), 'LIKE', "%{$word}%");
							})
							->orWhere(Capsule::raw('lower(etd.email_key)'), 'LIKE', "%{$word}%")
							->orWhere(Capsule::raw('lower(etddata.subject)'), 'LIKE', "%{$word}%")
							->orWhere(Capsule::raw('lower(etddata.body)'), 'LIKE', "%{$word}%")
							->orWhere(Capsule::raw('lower(etddata.description)'), 'LIKE', "%{$word}%");
					});
				}

				if (isset($customTemplates)) {
					$customTemplates->leftJoin('email_templates_settings as ets', 'et.email_id', '=', 'ets.email_id');
					foreach ($words as $word) {
						$word = strtolower(addcslashes($word, '%_'));
						$customTemplates->where(function ($customTemplates) use ($word) {
							$customTemplates->where(Capsule::raw('lower(et.email_key)'), 'LIKE', "%{$word}%")
								->orWhere(function($q) use ($word) {
									$q->where('ets.setting_name', 'subject');
									$q->where(Capsule::raw('lower(ets.setting_value)'), 'LIKE', "%{$word}%");
								})
								->orWhere(function($q) use ($word) {
									$q->where('ets.setting_name', 'body');
									$q->where(Capsule::raw('lower(ets.setting_value)'), 'LIKE', "%{$word}%");
								});
						});
					}
				}
			}
		}

		if (!empty($this->keys)) {
			$keys = $this->keys;
			$q->where(function($q) use ($keys) {
				$q->whereIn('etd.email_key', $this->keys)
					->orWhereIn('et.email_key', $this->keys);
			});
			if (isset($customTemplates)) {
				$customTemplates->where(function($customTemplates) use ($keys) {
					$customTemplates->whereIn('etd.email_key', $this->keys)
						->orWhereIn('et.email_key', $this->keys);
				});
			}
		}

		if (!empty($this->toRoleIds)) {
			$q->whereIn('etd.to_role_id', $this->toRoleIds);
		}

		// Add app-specific query statements
		\HookRegistry::call('EmailTemplate::getMany::queryObject', array(&$q, $this));

		$q->select($this->columns);

		if (isset($customTemplates)) {
			$customTemplates->select($this->columns);
			$q->union($customTemplates);
		}

		return $q;
	}

	/**
	 * Retrieve count of matches from query builder
	 *
	 * @return integer
	 */
	public function getCount() {
		$q = $this->get();
		return Capsule::table(Capsule::raw('(' . $q->toSql() . ') as email_template_count'))
			->mergeBindings($q)
			->count();
	}
}

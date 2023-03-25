<?php
/**
 * @file classes/services/QueryBuilders/PKPContextQueryBuilder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextQueryBuilder
 * @ingroup query_builders
 *
 * @brief Base class for context (journals/presses) list query builder
 */

namespace PKP\services\queryBuilders;

use Illuminate\Support\Facades\DB;

use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\services\queryBuilders\interfaces\EntityQueryBuilderInterface;

abstract class PKPContextQueryBuilder implements EntityQueryBuilderInterface
{
    /** @var string The database name for this context: `journals` or `presses` */
    protected $db;

    /** @var string The database name for this context's settings: `journal_settings` or `press_settings` */
    protected $dbSettings;

    /** @var string The column name for a context ID: `journal_id` or `press_id` */
    protected $dbIdColumn;

    /** @var bool enabled or disabled contexts */
    protected $isEnabled = null;

    /** @var int Filter contexts by whether or not this user can access it when logged in */
    protected $userId;

    /** @var string search phrase */
    protected $searchPhrase = null;

    /** @var string[] Selected columns */
    protected $columns = [];

    /**
     * Set isEnabled filter
     *
     * @param bool $isEnabled
     *
     * @return \PKP\services\queryBuilders\PKPContextQueryBuilder
     */
    public function filterByIsEnabled($isEnabled)
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    /**
     * Set userId filter
     *
     * The user id can access contexts where they are assigned to
     * a user group. If the context is disabled, they must be
     * assigned to ROLE_ID_MANAGER user group.
     *
     * @param bool $userId
     *
     * @return \PKP\services\queryBuilders\PKPContextQueryBuilder
     */
    public function filterByUserId($userId)
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set query search phrase
     *
     * @param string $phrase
     *
     * @return \PKP\services\queryBuilders\PKPContextQueryBuilder
     */
    public function searchPhrase($phrase)
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * @copydoc PKP\services\queryBuilders\interfaces\EntityQueryBuilderInterface::getCount()
     */
    public function getCount()
    {
        return $this
            ->getQuery()
            ->select('c.' . $this->dbIdColumn)
            ->get()
            ->count();
    }

    /**
     * @copydoc PKP\services\queryBuilders\interfaces\EntityQueryBuilderInterface::getIds()
     */
    public function getIds()
    {
        return $this
            ->getQuery()
            ->select('c.' . $this->dbIdColumn)
            ->pluck('c.' . $this->dbIdColumn)
            ->toArray();
    }

    /**
     * Get the name and basic data for a set of contexts
     *
     * This returns data from the main table and the name
     * of the context in its primary locale.
     *
     * @return array
     */
    public function getManySummary()
    {
        return $this
            ->getQuery()
            ->select([
                'c.' . $this->dbIdColumn . ' as id',
                'c.enabled',
                'cst.setting_value as name',
                'c.path as urlPath',
                'c.seq',
            ])
            ->leftJoin($this->dbSettings . ' as cst', function ($q) {
                $q->where('cst.' . $this->dbIdColumn, '=', DB::raw('c.' . $this->dbIdColumn))
                    ->where('cst.setting_name', '=', 'name')
                    ->where('cst.locale', '=', DB::raw('c.primary_locale'));
            })
            ->orderBy('c.seq')
            ->get()
            ->toArray();
    }

    /**
     * @copydoc PKP\services\queryBuilders\interfaces\EntityQueryBuilderInterface::getQuery()
     */
    public function getQuery()
    {
        $this->columns[] = 'c.*';
        $q = DB::table($this->db . ' as c');

        if (!empty($this->isEnabled)) {
            $q->where('c.enabled', '=', 1);
        } elseif ($this->isEnabled === false) {
            $q->where('c.enabled', '!=', 1);
        }

        // Filter for user id if present
        $q->when(!empty($this->userId), function($q){
            $q->whereIn('c.' . $this->dbIdColumn, function($q){
                $q->select('context_id')
                    ->from('user_groups')
                    ->where(function($q){
                        $q->where('role_id', '=', Role::ROLE_ID_MANAGER)
                            ->orWhere('c.enabled', '=', 1);
                    })
                    ->whereIn('user_group_id', function($q){
                        $q->select('user_group_id')
                            ->from('user_user_groups')
                            ->where('user_id', '=', $this->userId);
                    });
            });
        });

        // search phrase
        $q->when($this->searchPhrase !== null, function ($query) {
            $words = explode(' ', $this->searchPhrase);
            foreach ($words as $word) {
                $query->whereIn('c.' . $this->dbIdColumn, function ($query) use ($word) {
                    return $query->select($this->dbIdColumn)
                        ->from($this->dbSettings)
                        ->whereIn('setting_name', ['description', 'acronym', 'abbreviation'])
                        ->where(DB::raw('LOWER(setting_value)'), 'LIKE', DB::raw("CONCAT('%', LOWER(?), '%')"))->addBinding($word);
                });
            }
        });

        // Add app-specific query statements
        Hook::call('Context::getContexts::queryObject', [&$q, $this]);
        $q->select($this->columns);

        return $q;
    }
}

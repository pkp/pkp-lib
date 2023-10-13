<?php
/**
 * @file classes/emailTemplate/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of email templates
 */

namespace PKP\emailTemplate;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of EmailTemplate
 */
class Collector implements CollectorInterface
{
    public DAO $dao;

    /**
     * Retrieve all matches from query builder limited by those
     * which are custom templates or have been modified from the
     * default.
     */
    public ?bool $isModified = null;
    public int $contextId;
    public ?array $keys = null;
    public ?string $searchPhrase = null;
    public ?int $count = null;
    public ?int $offset = null;
    public ?array $alternateTo = null;

    public const EMAIL_TEMPLATE_STAGE_DEFAULT = 0;

    public function __construct(DAO $dao, int $contextId)
    {
        $this->dao = $dao;
        $this->contextId = $contextId;
    }

    /**
     * @copydoc DAO::getMany()
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function isModified(?bool $isModified): self
    {
        $this->isModified = $isModified;
        return $this;
    }

    /**
     * Set email keys filter
     */
    public function filterByKeys(?array $keys): self
    {
        $this->keys = $keys;
        return $this;
    }

    /**
     * Set query search phrase
     */
    public function searchPhrase(?string $phrase): self
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Filter results by custom templates that are alternates of another other email
     *
     * @param string[] $emailTemplateKeys One or more default email template keys
     */
    public function alternateTo(?array $emailTemplateKeys): self
    {
        $this->alternateTo = $emailTemplateKeys;
        return $this;
    }

    /**
     * This method performs a UNION on the default and custom template
     * tables, and returns the final SQL string and merged bindings.
     *
     * Use a UNION to ensure the query will match rows in email_templates and
     * email_templates_default. This ensures that custom templates which have
     * no default in email_templates_default are still returned. These templates
     * should not be returned when a role filter is used.
     */
    public function getQueryBuilder(): Builder
    {
        $q = $this->isModified === true || !is_null($this->alternateTo)
            ? $this->getCustomQueryBuilder()
            : $this->getDefaultQueryBuilder()->union($this->getCustomQueryBuilder());

        $q
            ->when(!is_null($this->count), function (Builder $q) {
                return $q->limit($this->count);
            })

            ->when(!is_null($this->count) && !is_null($this->offset), function (Builder $q) {
                return $q->offset($this->offset);
            });

        $q->orderBy('email_key');

        return $q;
    }

    /**
     * If you try to execute the query returned by this method, it will
     * cause an error in PostgreSQL.
     *
     * Call `->distinct()` on the query builder returned by this method
     * before executing it, or use `->union()` to join it with
     * `self::getCustomQueryBuilder()` (see self::getQueryBuilder()).
     *
     * @see self::getCompiledQuery()
     *
     * @hook EmailTemplate::Collector::default [[$q, $this]]
     */
    protected function getDefaultQueryBuilder(): Builder
    {
        $q = DB::table('email_templates_default_data as etddata')
            ->select('email_key')
            ->selectRaw('NULL as email_id')
            ->selectRaw($this->contextId . ' as context_id')
            ->selectRaw('NULL as alternate_to')

            ->whereNotIn('etddata.email_key', function (Builder $q) {
                $q->select('et.email_key')
                    ->from('email_templates as et')
                    ->where('et.context_id', $this->contextId);
            })

            ->when(!is_null($this->keys), function (Builder $q) {
                return $q->whereIn('email_key', $this->keys);
            })

            // search phrase
            ->when(!is_null($this->searchPhrase), function (Builder $q) {
                $words = explode(' ', $this->searchPhrase);
                $likePattern = DB::raw("CONCAT('%', LOWER(?), '%')");
                foreach ($words as $word) {
                    $q->where(function (Builder $q) use ($word, $likePattern) {
                        $q->where(DB::raw('LOWER(etddata.subject)'), 'LIKE', $likePattern)->addBinding($word)
                            ->orWhere(DB::raw('LOWER(etddata.body)'), 'LIKE', $likePattern)->addBinding($word)
                            ->orWhere(DB::raw('LOWER(etddata.email_key)'), 'LIKE', $likePattern)->addBinding($word);
                    });
                }
            });

        // Add app-specific query statements
        Hook::call('EmailTemplate::Collector::default', [$q, $this]);

        return $q;
    }

    /**
     * Execute query builder for custom email templates
     * and email templates that have been modified from
     * the default.
     *
     * @see self::getCompiledQuery()
     *
     * @hook EmailTemplate::Collector::custom [[$q, $this]]
     */
    protected function getCustomQueryBuilder(): Builder
    {
        $q = DB::table($this->dao->table . ' as et')
            ->select([
                'et.email_key',
                'et.email_id',
                'et.context_id',
                'et.alternate_to',
            ])

            ->where('et.context_id', $this->contextId)

            ->when(!is_null($this->keys), function (Builder $q) {
                return $q->whereIn('et.email_key', $this->keys);
            })

            ->when(!is_null($this->alternateTo), function (Builder $q) {
                return $q->whereIn('et.alternate_to', $this->alternateTo);
            })

            ->when(!is_null($this->searchPhrase), function (Builder $q) {
                $words = explode(' ', $this->searchPhrase);
                $likePattern = DB::raw("CONCAT('%', LOWER(?), '%')");
                foreach ($words as $word) {
                    $q->where(function (Builder $q) use ($word, $likePattern) {
                        $q->whereIn('et.email_id', function ($q) use ($word, $likePattern) {
                            return $q->select('ets.email_id')
                                ->from('email_templates_settings as ets')
                                ->where(function ($q) use ($word, $likePattern) {
                                    $q->where('ets.setting_name', 'subject');
                                    $q->where(DB::raw('LOWER(ets.setting_value)'), 'LIKE', $likePattern)->addBinding($word);
                                })
                                ->orWhere(function ($q) use ($word, $likePattern) {
                                    $q->where('ets.setting_name', 'body');
                                    $q->where(DB::raw('LOWER(ets.setting_value)'), 'LIKE', $likePattern)->addBinding($word);
                                });
                        })
                            ->orWhere(DB::raw('LOWER(et.email_key)'), 'LIKE', $likePattern)->addBinding($word);
                    });
                }
            });

        // Add app-specific query statements
        Hook::call('EmailTemplate::Collector::custom', [$q, $this]);

        return $q;
    }
}

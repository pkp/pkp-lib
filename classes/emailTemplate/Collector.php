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

class Collector implements CollectorInterface
{
    public DAO $dao;

    /**
     * Retrieve all matches from query builder limited by those
     * which are custom templates or have been modified from the
     * default.
     */
    public ?bool $isModified = null;
    public ?int $contextId = null;
    public ?bool $isEnabled = null;
    public ?bool $isCustom = null;
    public ?array $fromRoleIds = null;
    public ?array $toRoleIds = null;
    public ?array $keys = null;
    public ?string $searchPhrase = null;
    public ?array $stageIds = null;
    public ?int $count = null;
    public ?int $offset = null;
    public ?array $mailables = null;

    public const EMAIL_TEMPLATE_STAGE_DEFAULT = 0;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    public function filterByIsModified(?bool $isModified): self
    {
        $this->isModified = $isModified;
        return $this;
    }

    /**
     * Set context filter
     */
    public function filterByContext(?int $contextId): self
    {
        $this->contextId = $contextId;
        return $this;
    }

    /**
     * Set isEnabled filter
     */
    public function filterByIsEnabled(?bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    /**
     * Set isCustom filter
     */
    public function filterByIsCustom(?bool $isCustom): self
    {
        $this->isCustom = $isCustom;
        return $this;
    }

    /**
     * Set sender roles filter
     */
    public function filterByFromRoleIds(?array $fromRoleIds): self
    {
        $this->fromRoleIds = $fromRoleIds;
        return $this;
    }

    /**
     * Set recipient roles filter
     */
    public function filterByToRoleIds(?array $toRoleIds): self
    {
        $this->toRoleIds = $toRoleIds;
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
     * Set stage ID filter
     */
    public function filterByStageIds(?array $stageIds): self
    {
        $this->stageIds = $stageIds;
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
     * Filter results by those assigned to one or more mailables
     *
     * @param string[] $mailables One or more mailable class names
     */
    public function filterByMailables(?array $mailables): self
    {
        $this->mailables = $mailables;
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
        $q = $this->isModified === true || $this->isEnabled === false || !is_null($this->mailables) || $this->isCustom === true ?
            $this->getCustomQueryBuilder() :
            $this->getDefaultQueryBuilder()->union($this->getCustomQueryBuilder());

        $q
            ->when($this->isCustom === true, function (Builder $q) {
                return $q->whereNotIn('et.email_key', function (Builder $q) {
                    return $q->select('email_key')
                        ->from('email_templates_default_data as etddata');
                });
            })

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
     * Execute query builder for default email templates
     *
     * @see self::getCompiledQuery()
     *
     * @return
     */
    protected function getDefaultQueryBuilder(): Builder
    {
        $q = DB::table('email_templates_default_data as etddata')
            ->select('email_key')->distinct()
            ->selectRaw('NULL as email_id')
            ->selectRaw('1 as enabled')
            ->selectRaw('NULL as context_id')

            ->whereNotIn('etddata.email_key', function (Builder $q) {
                $q->select('et.email_key')->from('email_templates as et');
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
     */
    protected function getCustomQueryBuilder(): Builder
    {
        $q = DB::table($this->dao->table . ' as et')
            ->select('et.email_key')
            ->addSelect('et.email_id')
            ->addSelect('et.enabled')
            ->addSelect('et.context_id')

            ->when(!is_null($this->contextId), function (Builder $q) {
                return $q->where('et.context_id', $this->contextId);
            })

            ->when(!is_null($this->isEnabled), function (Builder $q) {
                return $q->when(
                    $this->isEnabled === true,
                    function (Builder $q) {
                        return $q->where('et.enabled', '=', 1);
                    },
                    function (Builder $q) {
                        return $q->where('et.enabled', '!=', 1);
                    }
                );
            })

            ->when(!is_null($this->keys), function (Builder $q) {
                return $q->whereIn('et.email_key', $this->keys);
            })

            ->when(!is_null($this->mailables), function (Builder $q) {
                return $q->whereIn('et.email_id', function (Builder $q) {
                    return $q->select('email_id')
                        ->from('mailable_templates')
                        ->whereIn('mailable_id', $this->mailables);
                });
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

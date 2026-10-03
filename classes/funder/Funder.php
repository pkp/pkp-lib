<?php

/**
 * @file classes/funder/Funder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Funder
 *
 * @brief Basic class describing Funders existing in the system.
 */

namespace PKP\funder;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\core\traits\ModelWithSettings;
use PKP\i18n\LocaleConversion;
use PKP\ror\Ror;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;

class Funder extends Model
{
    use ModelWithSettings;

    protected $table = 'funders';
    protected $primaryKey = 'funder_id';
    public const CREATED_AT = null;
    public const UPDATED_AT = null;
    protected string $settingsTable = 'funder_settings';

    protected $guarded = ['funderId', 'id'];

    /**
     * Container binding key for the request-scoped, in-memory store that memoizes
     * each submission's resolved locale set.
     */
    private const RESOLVED_LOCALE_CACHE = 'funder.resolvedPublicationLocales';

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return PKPSchemaService::SCHEMA_FUNDER;
    }

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return $this->settingsTable;
    }

    /**
     * Filter by submission IDs
     */
    protected function scopeWithSubmissionIds(EloquentBuilder $builder, array $submissionIds): EloquentBuilder
    {
        return $builder->whereIn('submission_id', $submissionIds);
    }

    /**
     * Order by seq
     */
    protected function scopeOrderBySeq(EloquentBuilder $builder): EloquentBuilder
    {
        return $builder->orderBy('seq')->orderBy('funder_id');
    }

    /**
     * The parent Submission, lazily loaded and memoized.
     */
    protected function submission(): Attribute
    {
        return Attribute::make(
            get: function (): ?PKPSubmission {
                $id = (int) $this->getRawOriginal('submission_id');
                return $id ? Repo::submission()->get($id) : null;
            },
        )->shouldCache();
    }

    /**
     * The submission's Context, lazily loaded and memoized.
     */
    protected function context(): Attribute
    {
        return Attribute::make(
            get: function (): ?Context {
                $contextId = (int) $this->submission?->getData('contextId');
                return $contextId ? Application::getContextDAO()->getById($contextId) : null;
            },
        )->shouldCache();
    }

    /**
     * The resolved ROR object, lazily loaded and memoized.
     */
    protected function rorObject(): Attribute
    {
        return Attribute::make(
            get: function (): ?Ror {
                $ror = $this->getRawOriginal('ror');
                if (empty($ror)) {
                    return null;
                }
                return Repo::ror()
                    ->getCollector()
                    ->filterByRor($ror)
                    ->getMany()
                    ->first();
            },
        )->shouldCache();
    }

    /**
     * Funder name.
     * - If a multilingual value is stored in settings, return it unchanged.
     * - Else, if the funder has a ROR, derive a multilingual array by mapping
     *   the ROR's names onto the submission's publication languages.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($value) || empty($attributes['ror'])) {
                    return $value;
                }
                $rorObject = $this->rorObject;
                if (!$rorObject) {
                    return $value;
                }
                $locales = $this->resolvedPublicationLanguages();
                if (empty($locales)) {
                    return $value;
                }
                $names = [];
                foreach ($locales as $locale) {
                    $rorLocale = LocaleConversion::getIso1FromLocale($locale);
                    $names[$locale] = $rorObject->getName($rorLocale)
                        ?? $rorObject->getName($rorObject->getDisplayLocale());
                }
                return empty($names) ? $value : $names;
            },
        )->shouldCache();
    }

    /**
     * Used by ModelWithSettings::getLocalizedData() as the last-resort locale
     * in its fallback chain. Returns the submission's primary locale.
     */
    public function getDefaultLocale(): ?string
    {
        return $this->submission?->getData('locale');
    }

    /**
     * The submission's full locale set, memoized per submission for one web request
     * so the several funders of a submission resolve their shared set once, not once
     * a piece (article page with N ROR funders: N identical queries → 1). Bypassed in
     * CLI, where a long-lived process would accumulate/serve stale entries.
     *
     * @return string[]
     */
    private function resolvedPublicationLanguages(): array
    {
        $submissionId = (int) $this->getRawOriginal('submission_id');
        if (!$submissionId) {
            return [];
        }

        if (app()->runningInConsole()) {
            return $this->computeResolvedPublicationLanguages($submissionId);
        }

        app()->scopedIf(
            self::RESOLVED_LOCALE_CACHE,
            fn (): CacheRepository => new CacheRepository(new ArrayStore())
        );

        return app(self::RESOLVED_LOCALE_CACHE)->rememberForever(
            "submission-{$submissionId}",
            fn (): array => $this->computeResolvedPublicationLanguages($submissionId)
        );
    }

    /**
     * Build the submission's locale set with a single union query.
     *
     * Union of the
     *  - submission's primary locale
     *  - data-locales of every multilingual publication/author setting
     *  - context's supported submission metadata locales
     * into one single query load.
     *
     * Each publication's own `locale` is a readOnly property derived from (and
     * equal to) the submission's primary locale, so `submissions.locale` covers it.
     * The `locale <> ''` filter isolates the per-locale (multilingual-stored)
     * settings rows as where, non-multilingual rows store an empty locale.
     *
     * See {@see resolvedPublicationLanguages()} for the request-scoped memoization
     * that wraps this.
     *
     * @return string[]
     */
    private function computeResolvedPublicationLanguages(int $submissionId): array
    {
        $contextDao = Application::getContextDAO();
        $rows = DB::table('submissions as s')
            ->leftJoin(
                $contextDao->settingsTableName . ' as cs',
                fn ($join) => $join
                    ->on('cs.' . $contextDao->primaryKeyColumn, '=', 's.context_id')
                    ->where('cs.setting_name', '=', 'supportedSubmissionMetadataLocales')
            )
            ->where('s.submission_id', $submissionId)
            ->select('s.locale', 'cs.setting_value as supported_json')
            ->union(
                DB::table('publication_settings as ps')
                    ->join('publications as p', 'p.publication_id', '=', 'ps.publication_id')
                    ->where('p.submission_id', $submissionId)
                    ->where('ps.locale', '<>', '')
                    ->select('ps.locale', DB::raw('null as supported_json'))
            )
            ->union(
                DB::table('author_settings as aus')
                    ->join('authors as au', 'au.author_id', '=', 'aus.author_id')
                    ->join('publications as p2', 'p2.publication_id', '=', 'au.publication_id')
                    ->where('p2.submission_id', $submissionId)
                    ->where('aus.locale', '<>', '')
                    ->select('aus.locale', DB::raw('null as supported_json'))
            )
            ->get();

        $locales = $rows->pluck('locale')->all();
        $supportedJson = $rows->pluck('supported_json')->filter()->first();
        $supported = $supportedJson ? json_decode($supportedJson, true) : [];

        return array_values(array_unique(array_filter(array_merge(
            $locales,
            is_array($supported) ? $supported : []
        ))));
    }

    /**
     * Invalidate the funder facet cache when a funder is saved or deleted.
     */
    protected static function booted(): void
    {
        static::saved(function (Funder $funder): void {
            if ($contextId = $funder->context?->getId()) {
                Repo::funder()->forgetFunderFacetCache($contextId);
            }
        });

        static::deleted(function (Funder $funder): void {
            if ($contextId = $funder->context?->getId()) {
                Repo::funder()->forgetFunderFacetCache($contextId);
            }
        });
    }
}

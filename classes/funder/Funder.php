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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\Events\Looping;
use PKP\core\traits\ModelWithSettings;
use PKP\i18n\LocaleConversion;
use PKP\ror\Ror;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use PKP\context\Context;

class Funder extends Model
{
    use ModelWithSettings;

    protected $table = 'funders';
    protected $primaryKey = 'funder_id';
    public const CREATED_AT = null;
    public const UPDATED_AT = null;
    protected string $settingsTable = 'funder_settings';

    protected $guarded = ['funderId', 'id'];

    /** Per-request caches keyed by id — shared across all Funder instances. */
    private static array $submissionCache = [];
    private static array $contextCache    = [];
    private static array $rorCache        = [];
    private static array $localesCache    = [];

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
     * Filter by submission ID
     */
    protected function scopeWithSubmissionId(EloquentBuilder $builder, int $submissionId): EloquentBuilder
    {
        return $builder->where('submission_id', $submissionId);
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
                if (!$id) {
                    return null;
                }
                if (!array_key_exists($id, self::$submissionCache)) {
                    self::$submissionCache[$id] = Repo::submission()->get($id);
                }
                return self::$submissionCache[$id];
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
                $submission = $this->submission;
                if (!$submission) {
                    return null;
                }
                $contextId = (int) $submission->getData('contextId');
                if (!$contextId) {
                    return null;
                }
                if (!array_key_exists($contextId, self::$contextCache)) {
                    self::$contextCache[$contextId] = Application::getContextDAO()->getById($contextId);
                }
                return self::$contextCache[$contextId];
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
                if (!array_key_exists($ror, self::$rorCache)) {
                    self::$rorCache[$ror] = Repo::ror()
                        ->getCollector()
                        ->filterByRor($ror)
                        ->getMany()
                        ->first();
                }
                return self::$rorCache[$ror];
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
     * Compute the full locale set for this funder's submission, memoized.
     *
     * @return string[]
     */
    private function resolvedPublicationLanguages(): array
    {
        $submissionId = (int) $this->getRawOriginal('submission_id');
        if (!$submissionId) {
            return [];
        }
        if (isset(self::$localesCache[$submissionId])) {
            return self::$localesCache[$submissionId];
        }
        $submission = $this->submission;
        $context = $this->context;
        if (!$submission || !$context) {
            return self::$localesCache[$submissionId] = [];
        }
        return self::$localesCache[$submissionId] = $submission->getPublicationLanguages(
            $context->getSupportedSubmissionMetadataLocales() ?? []
        );
    }

    /**
     * Reset per-process caches.
     */
    public static function clearResolverCaches(): void
    {
        self::$submissionCache = [];
        self::$contextCache    = [];
        self::$rorCache        = [];
        self::$localesCache    = [];
    }

    /**
     * Between each worker loop iteration (CLI mode only), flush the per-request
     * static caches so data from the previous job can't bleed into the next one.
     */
    protected static function booted(): void
    {
        app('events')->listen(
            Looping::class,
            function (Looping $event): void {
                if (!app()->runningInConsole() || app()->runningUnitTests()) {
                    return;
                }
                self::clearResolverCaches();
            }
        );
    }
}

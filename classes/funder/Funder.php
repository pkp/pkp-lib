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
use PKP\core\traits\ModelWithSettings;
use PKP\i18n\LocaleConversion;
use PKP\services\PKPSchemaService;

/**
 * @method static \Illuminate\Database\Eloquent\Builder withSubmissionId (int $submissionId) accepts valid submission ID
 */
class Funder extends Model
{
    use ModelWithSettings;

    protected $table = 'funders';
    protected $primaryKey = 'funder_id';
    public const CREATED_AT = null;
    public const UPDATED_AT = null;
    protected string $settingsTable = 'funder_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['funderId', 'id'];

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
        return $builder->orderBy('seq');
    }

    /**
     * Get the funder name, falling back to the ROR name if not set
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                if (!empty($value) || empty($attributes['ror'])) {
                    return $value;
                }

                $ror = Repo::ror()
                    ->getCollector()
                    ->filterByRor($attributes['ror'])
                    ->getMany()
                    ->first();

                if (!$ror) {
                    return $value;
                }

                $names = [];
                foreach (Application::get()->getRequest()->getContext()->getSupportedLocales() as $allowedLocale) {
                    $rorLocale = LocaleConversion::getIso1FromLocale($allowedLocale);
                    $names[$allowedLocale] = $ror->getName($rorLocale) ?? $ror->getName($ror->getDisplayLocale());
                }

                return empty($names) ? $value : $names;
            }
        );
    }
}
<?php

/**
 * @file classes/dataCitation/DataCitation.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataCitation
 *
 * @brief Basic class describing Data Citation existing in the system.
 *
 */

namespace PKP\dataCitation;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use PKP\core\traits\ModelWithSettings;
use PKP\dataCitation\pid\PidResolver;
use PKP\services\PKPSchemaService;

/**
 * @method static \Illuminate\Database\Eloquent\Builder withPublicationIds (array $publicationId) accepts valid publication IDs
 */
class DataCitation extends Model
{
    use ModelWithSettings;

    protected $table = 'data_citations';
    protected $primaryKey = 'data_citation_id';
    public const CREATED_AT = null;
    public const UPDATED_AT = null;
    protected string $settingsTable = 'data_citation_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = ['dataCitationId', 'id'];

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return PKPSchemaService::SCHEMA_DATA_CITATION;
    }

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return $this->settingsTable;
    }

    /**
     * Override saving to strip known prefixes and Base URL's from identifier
     *
     * @return static
     *
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function (self $model) {
            $dirty = $model->getDirty();
            $identifierType = $dirty['identifier_type'] ?? $model->identifierType;

            if ((isset($dirty['identifier']) || isset($dirty['identifier_type'])) && !empty($model->identifier) && !empty($identifierType)) {
                $pidClass = PidResolver::resolveByIdentifierType($identifierType);

                if ($pidClass) {
                    $identifier = $pidClass::extractFromString($model->identifier);

                    if ($identifier !== '') {
                        $model->identifier = $identifier;
                    } else {
                        $model->identifier = $pidClass::removePrefix($model->identifier);
                    }
                }
            }
        });
    }

    /**
     * Filter by publication IDs
     *
     *
     */
    protected function scopeWithPublicationIds(EloquentBuilder $builder, array $publicationIds): EloquentBuilder
    {
        return $builder->whereIn('publication_id', $publicationIds);
    }

    /**
     * Order by seq
     *
     *
     */
    protected function scopeOrderBySeq(EloquentBuilder $builder): EloquentBuilder
    {
        return $builder->orderBy('seq');
    }
}

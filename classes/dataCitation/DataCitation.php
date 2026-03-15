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
use PKP\services\PKPSchemaService;

/**
 * @method static \Illuminate\Database\Eloquent\Builder withPublicationId (int $publicationId) accepts valid publication ID
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
     * Override fill to strip known base URLs from identifier before saving
     * 
     * @return static
     * 
     */
    public function fill(array $attributes): static
    {
        if (!empty($attributes['identifier']) && !empty($attributes['identifierType'])) {
            $identifierBaseUrls = self::getIdentifierBaseUrls();
            if (isset($identifierBaseUrls[$attributes['identifierType']])) {
                $attributes['identifier'] = preg_replace($identifierBaseUrls[$attributes['identifierType']], '', $attributes['identifier']);
            }
        }

        return parent::fill($attributes);
    }

    /**
     * Get the base URLs for identifier types that use URL formats.
     * Used to strip the base URL before validation and storage.
     * 
     * @return array
     * 
     */
    public static function getIdentifierBaseUrls(): array
    {
        return [
            'DOI'    => '/^https?:\/\/(?:dx\.)?doi\.org\//i',
            'PMID'   => '/^https?:\/\/pubmed\.ncbi\.nlm\.nih\.gov\//i',
            'PMCID'  => '/^https?:\/\/(?:www\.)?ncbi\.nlm\.nih\.gov\/pmc\/articles\//i',
            'Handle' => '/^https?:\/\/hdl\.handle\.net\//i',
        ];
    }

    /**
     * Filter by publication ID
     * 
     * @return EloquentBuilder
     * 
     */
    protected function scopeWithPublicationId(EloquentBuilder $builder, int $publicationId): EloquentBuilder
    {
        return $builder->where('publication_id', $publicationId);
    }

    /**
     * Order by seq
     * 
     * @return EloquentBuilder
     * 
     */
    protected function scopeOrderBySeq(EloquentBuilder $builder): EloquentBuilder
    {
        return $builder->orderBy('seq');
    }
}

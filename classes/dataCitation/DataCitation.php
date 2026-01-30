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
use PKP\plugins\Hook;
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
     * @return bool
     * @inheritDoc
     * @hook DataCitation::add [[$this]]
     * @hook DataCitation::edit [[$this]]
     */
    public function save(array $options = []): bool
    {

        $isNew = !$this->exists;
        $saved = parent::save($options);

        if (!$saved) {
           return false;
        }

        // Reload the model to ensure all relationships and settings are loaded
        $this->refresh();

        if ($isNew) {
            // This is a new record
            Hook::call('DataCitation::add', [$this]);
        } else {
            // This is an update
            Hook::call('DataCitation::edit', [$this]);
        }

        return $saved;        
    }

    /**
     * Filter by publication ID
     */
    protected function scopeWithPublicationId(EloquentBuilder $builder, int $publicationId): EloquentBuilder
    {
        return $builder->where('publication_id', $publicationId);
    }
}

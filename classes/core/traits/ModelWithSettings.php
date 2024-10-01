<?php

/**
 * @file classes/core/traits/ModelWithSettings.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ModelWithSettings
 *
 * @ingroup core_traits
 *
 * @brief A trait for Eloquent Model classes that can be used with entities that have a settings table.
 *
 */

namespace PKP\core\traits;

use Eloquence\Behaviours\HasCamelCasing;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PKP\core\maps\Schema;
use PKP\core\SettingsBuilder;
use PKP\facades\Locale;
use PKP\services\PKPSchemaService;
use stdClass;

trait ModelWithSettings
{
    use HasCamelCasing;

    // The list of attributes associated with the model settings
    protected array $settings = [];

    // The list of multilingual attributes
    protected array $multilingualProps = [];

    /**
     * Get main table name
     *
     * @return string
     */
    abstract public function getTable();

    /**
     * Get settings table name
     */
    abstract public function getSettingsTable();

    /**
     * The name of the schema for the Model if exists, null otherwise
     */
    abstract public static function getSchemaName(): ?string;

    /**
     * See Illuminate\Database\Eloquent\Concerns\HasAttributes::mergeCasts()
     *
     * @param array $casts
     *
     * @return array
     */
    abstract protected function ensureCastsAreStringValues($casts);

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (static::getSchemaName()) {
            $this->setSchemaData();
        }
    }

    /**
     * Create a new Eloquent query builder for the model that supports settings table
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new SettingsBuilder($query);
    }

    /**
     * Get a list of attributes from the settings table associated with the Model
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get multilingual attributes associated with the Model
     */
    public function getMultilingualProps(): array
    {
        return $this->multilingualProps;
    }

    /**
     * @param string $data Model's localized attribute
     * @param ?string $locale Locale to retrieve data for, default - current locale
     *
     * @throws Exception
     *
     * @return mixed Localized value
     */
    public function getLocalizedData(string $data, ?string $locale = null): mixed
    {
        if (is_null($locale)) {
            $locale = Locale::getLocale();
        }

        if (!in_array($data, $this->getMultilingualProps())) {
            throw new Exception(
                sprintf('Given localized property %s does not exist in %s model', $data, static::class)
            );
        }

        $multilingualProp = $this->getAttribute($data);

        return $multilingualProp[$locale] ?? null;
    }

    /**
     * Sets the schema for the current Model
     */
    protected function setSchemaData(): void
    {
        $schemaService = app()->get('schema'); /** @var PKPSchemaService $schemaService */
        $schema = $schemaService->get($this->getSchemaName());
        $this->convertSchemaToCasts($schema);
        $this->settings = array_merge($this->getSettings(), $schemaService->groupPropsByOrigin($this->getSchemaName())[Schema::ATTRIBUTE_ORIGIN_SETTINGS] ?? []);
        $this->multilingualProps = array_merge($this->getMultilingualProps(), $schemaService->getMultilingualProps($this->getSchemaName()));

        $writableProps = $schemaService->groupPropsByOrigin($this->getSchemaName(), true);
        $this->fillable = array_merge(
            $writableProps[Schema::ATTRIBUTE_ORIGIN_SETTINGS],
            $writableProps[Schema::ATTRIBUTE_ORIGIN_MAIN],
            $this->fillable,
        );
    }

    /**
     * Set casts by deriving proper types from schema
     * FIXME pkp/pkp-lib#10476 casts on multilingual properties. Keep in mind that overriding Model::attributesToArray() might conflict with HasCamelCasing trait
     */
    protected function convertSchemaToCasts(stdClass $schema): void
    {
        $propCast = [];
        foreach ($schema->properties as $propName => $propSchema) {
            // Don't cast multilingual values as Eloquent tries to convert them from string to arrays with json_decode()
            if (isset($propSchema->multilingual)) {
                continue;
            }
            $propCast[$propName] = $propSchema->type;
        }

        $propCasts = $this->ensureCastsAreStringValues($propCast);
        $this->casts = array_merge($propCasts, $this->casts);
    }

    /**
     * Override method from HasCamelCasing to retrieve values from setting attributes as it leads to the conflict
     */
    public function getAttribute($key): mixed
    {
        if (in_array($key, $this->getSettings())) {
            return parent::getAttribute($key);
        }

        return $this->isRelation($key) ? parent::getAttribute($key) : parent::getAttribute($this->getSnakeKey($key));
    }

    /**
     * Create an id attribute for the models
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }
}

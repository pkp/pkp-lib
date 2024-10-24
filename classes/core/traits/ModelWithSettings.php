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

use Exception;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PKP\core\casts\MultilingualSettingAttribute;
use PKP\core\maps\Schema;
use PKP\core\SettingsBuilder;
use PKP\facades\Locale;
use PKP\services\PKPSchemaService;
use stdClass;

trait ModelWithSettings
{
    use HasCamelCasing;

    /**
     * @see \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guardableColumns
     */
    protected static $guardableColumns = [];

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
    abstract public function getSettingsTable(): string;

    /**
     * The name of the schema for the Model if exists, null otherwise
     */
    abstract public static function getSchemaName(): ?string;

    /**
     * @see Illuminate\Database\Eloquent\Concerns\HasAttributes::mergeCasts()
     *
     * @param array $casts
     *
     * @return array
     */
    abstract protected function ensureCastsAreStringValues($casts);

    /**
     * @see \Illuminate\Database\Eloquent\Model::__construct()
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        if (static::getSchemaName()) {
            $this->setSchemaData();
        } else {
            $this->generateAttributeCast(
                collect($this->getMultilingualProps())
                    ->flatMap(
                        fn (string $attribute): array => [$attribute => MultilingualSettingAttribute::class]
                    )
                    ->toArray()
            );

            if (!empty($this->fillable)) {
                $this->mergeFillable(array_merge($this->getSettings(), $this->getMultilingualProps()));
            }
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
        $this->fillable = array_values(array_unique(array_merge(
            $writableProps[Schema::ATTRIBUTE_ORIGIN_SETTINGS],
            $writableProps[Schema::ATTRIBUTE_ORIGIN_MAIN],
            $this->fillable,
        )));
    }

    /**
     * Set casts by deriving proper types from schema
     * FIXME pkp/pkp-lib#10476 casts on multilingual properties. Keep in mind that overriding Model::attributesToArray() might conflict with HasCamelCasing trait
     */
    protected function convertSchemaToCasts(stdClass $schema): void
    {
        $propCast = [];

        foreach ($schema->properties as $propName => $propSchema) {

            $propCast[$propName] = isset($propSchema->multilingual) && $propSchema->multilingual == true
                ? MultilingualSettingAttribute::class
                : $propSchema->type;
        }

        $this->generateAttributeCast($propCast);
    }

    /**
     * Generate the final cast from dynamically generated attr casts
     */
    protected function generateAttributeCast(array $attrCast): void
    {
        $attrCasts = $this->ensureCastsAreStringValues($attrCast);
        $this->casts = array_merge($attrCasts, $this->casts);
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

    /**
     * @see \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::isGuardableColumn()
     */
    protected function isGuardableColumn($key)
    {
        // Need the snake like to key to check for main table to compare with column listing
        $key = Str::snake($key);
        
        if (! isset(static::$guardableColumns[get_class($this)])) {
            $columns = $this->getConnection()
                        ->getSchemaBuilder()
                        ->getColumnListing($this->getTable());

            if (empty($columns)) {
                return true;
            }
            static::$guardableColumns[get_class($this)] = $columns;
        }


        $settingsWithMultilingual = array_merge($this->getSettings(), $this->getMultilingualProps());
        $camelKey = Str::camel($key);
        
        // Check if this column included in setting and multilingula props and not set to guarded
        if (in_array($camelKey, $settingsWithMultilingual) && !in_array($camelKey, $this->getGuarded())) {
            return true;
        }
        
        return in_array($key, (array)static::$guardableColumns[get_class($this)]);
    }
}

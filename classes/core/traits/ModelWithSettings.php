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
 * @brief A trait for Eloquent Model classes that can be used with entities that have a settings table.
 *
 */

namespace PKP\core\traits;

use Eloquence\Behaviours\HasCamelCasing;
use Exception;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use PKP\core\casts\MultilingualSettingAttribute;
use PKP\core\maps\Schema;
use PKP\core\SettingsBuilder;
use PKP\services\PKPSchemaService;
use stdClass;

trait ModelWithSettings
{
    use HasCamelCasing;
    use LocalizedData;

    public const LOCALE_MATCH_STRICT = true;

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
     * @param string    $data           Model's localized attribute
     * @param ?string   $locale         Locale to retrieve data for, default - current locale
     * @param ?string   $selectedLocale Optional param to contain the final selected locale
     *                                  that has been returned if no match found for $locale param
     *
     * @throws Exception
     *
     * @return mixed Localized value
     */
    public function getLocalizedData(
        string $data,
        ?string $locale = null,
        bool $localeMatch = !self::LOCALE_MATCH_STRICT,
        ?string &$selectedLocale = null,
    ): mixed {
        if (!in_array($data, $this->getMultilingualProps())) {
            throw new Exception(
                sprintf('Given localized property %s does not exist in %s model', $data, static::class)
            );
        }

        $multilingualProp = $this->getAttribute($data);

        if ($localeMatch === self::LOCALE_MATCH_STRICT) {
            return $multilingualProp[$locale] ?? null;
        }

        return $multilingualProp
            ? $this->getBestLocalizedData($multilingualProp, $locale, $selectedLocale)
            : null;
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

        return $this->isRelation($key)
            ? parent::getAttribute($key)
            : parent::getAttribute($this->getSnakeKey($key));
    }

    /**
     * Override HasCamelCasing::getCasts() so casts for non-multilingual setting props
     * resolve under their camelCase attribute key. Such settings are hydrated by
     * SettingsBuilder verbatim from the camelCase setting_name and read back under
     * that same camelCase key, so their cast must be keyed camelCase too.
     *
     * Everything else keeps HasCamelCasing's snake_case behaviour:
     *  - primary-table columns are stored snake_case and must be snake_case to match
     *  - multilingual props cast via the inbound-only MultilingualSettingAttribute,
     *    which fires on the snake-cased set path (and is not applied on read), so
     *    their cast key must stay snake_case to keep firing on write.
     *
     * @see \Illuminate\Database\Eloquent\Concerns\HasAttributes::getCasts()
     */
    public function getCasts()
    {
        // parent::getCasts() resolves to HasAttributes (the framework's raw cast
        // store), NOT HasCamelCasing — we replace its snake-everything behaviour.
        $camelCaseSettings = array_flip(array_diff($this->getSettings(), $this->getMultilingualProps()));

        return collect(parent::getCasts())
            ->mapWithKeys(function ($cast, $key) use ($camelCaseSettings) {
                // Normalise the cast key to camelCase before testing membership so
                // both registration styles are covered: schema models register casts
                // in camelCase, pure-Eloquent models in snake_case.
                $camelKey = Str::camel($key);

                return isset($camelCaseSettings[$camelKey])
                    ? [$camelKey => $cast]          // non-multilingual setting → match camelCase hydration key
                    : [Str::snake($key) => $cast];  // primary column / multilingual prop → snake_case
            })
            ->toArray();
    }

    /**
     * Override HasCamelCasing::setAttribute() so non-multilingual setting props are
     * stored under their camelCase key — the same key they are hydrated, read
     * (see getAttribute()) and cast (see getCasts()) under. This keeps the set/get
     * path symmetric for these props: their primitive cast (e.g. array → JSON) fires
     * on write, the value round-trips in memory, and SettingsBuilder persists the
     * already-cast value.
     *
     * Everything else keeps HasCamelCasing's snake_case behaviour: primary columns
     * map to their snake_case DB column, and multilingual props keep snake_case so
     * their inbound MultilingualSettingAttribute cast keeps firing on the set path.
     *
     * @see \Eloquence\Behaviours\HasCamelCasing::setAttribute()
     */
    public function setAttribute($key, $value)
    {
        $camelKey = Str::camel($key);

        if (in_array($camelKey, $this->getSettings()) && !in_array($camelKey, $this->getMultilingualProps())) {
            return parent::setAttribute($camelKey, $value);
        }

        return parent::setAttribute($this->getSnakeKey($key), $value);
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

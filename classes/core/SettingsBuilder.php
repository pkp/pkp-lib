<?php

/**
 * @file classes/core/SettingsBuilder.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsBuilder
 *
 * @brief The class that extends Eloquent's builder to support settings tables for Models
 */

namespace PKP\core;

use Closure;
use Illuminate\Contracts\Database\Query\ConditionExpression;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PKP\core\traits\EntityUpdate;
use stdClass;

class SettingsBuilder extends Builder
{
    use EntityUpdate;

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array|string  $columns
     *
     * @return \Illuminate\Database\Eloquent\Model[]|static[]
     */
    public function getModels($columns = ['*'])
    {
        $rows = $this->getModelWithSettings($columns);

        $returner = $this->model->hydrate(
            $rows
        )->all();

        return $returner;
    }

    /**
     * Update records in the database, including settings
     *
     * @return int
     */
    public function update(array $values)
    {
        // Separate Model's primary values from settings
        [$settingValues, $primaryValues] = collect($values)->partition(
            fn (mixed $value, string $key) => in_array(Str::camel($key), $this->model->getSettings())
        );

        // Don't update settings if they aren't set
        if ($settingValues->isEmpty()) {
            return parent::update($primaryValues->toArray());
        }

        if ($primaryValues->isNotEmpty()) {
            $count = parent::update($primaryValues->toArray());
        }

        // FIXME pkp/pkp-lib#10485 Eloquent transforms attributes to snake case, find and override instead of transforming here
        $settingValues = $settingValues->mapWithKeys(
            fn (mixed $value, string $key) => [Str::camel($key) => $value]
        );

        $schema = null;
        if (!$this->getSchemaName()) {

            // Casts are always defined in snake key based column name to cast type
            // Need to convert the snake key based column names to camel case
            $casts = collect($this->model->getCasts())->mapWithKeys(
                fn (string $cast, string $columnName): array => [
                    Str::camel($columnName) => $cast
                ]
            )->toArray();

            foreach ($this->model->getSettings() as $settingName) {

                // If this settings column is not intened to update,
                // no need to set any type for it
                if (!$settingValues->has($settingName)) {
                    continue;
                }

                $schema['properties'][$settingName]['multilingual'] = in_array(
                    $settingName,
                    $this->model->getMultilingualProps()
                );

                if (array_key_exists($settingName, $casts)) {
                    $type = $casts[$settingName];
                } else {
                    $type = 'string';
                    trigger_error(
                        "The setting {$settingName} doesn\'t have a defined type, using {$type} instead",
                        E_USER_WARNING
                    );
                }

                $schema['properties'][$settingName]['type'] = $type;
            }
        }

        $this->updateSettings(
            $settingValues->toArray(),
            $this->model->getKey(),
            !is_null($schema) ? json_decode(json_encode($schema)) : null
        );

        return $count ?? 0;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     * Overrides Builder's method to insert setting values for a models with settings
     *
     * @param  string|null  $sequence
     *
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        // Separate Model's primary values from settings
        [$settingValues, $primaryValues] = collect($values)->partition(
            fn (mixed $value, string $key) => in_array(Str::camel($key), $this->model->getSettings())
        );


        $id = parent::insertGetId($primaryValues->toArray(), $sequence);

        if ($settingValues->isEmpty()) {
            return $id;
        }

        $rows = $this->getSettingRows($settingValues, $id);
        DB::table($this->getSettingsTable())->insert($rows);

        return $id;
    }

    /**
     * Delete model with settings
     */
    public function delete(): int
    {
        $id = parent::delete();
        if (!$id) {
            return $id;
        }

        DB::table($this->getSettingsTable())->where(
            $this->getPrimaryKeyName(),
            $this->model->getRawOriginal($this->getPrimaryKeyName()) ?? $this->model->getKey()
        )->delete();

        return $id;
    }

    /**
     * Add a basic where clause to the query.
     * Overrides Eloquent Builder method to support settings table
     *
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  string  $boolean
     * @param null|mixed $operator
     * @param null|mixed $value
     *
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column instanceof ConditionExpression || $column instanceof Closure) {
            return parent::where($column, $operator, $value, $boolean);
        }

        $settings = [];
        $primaryColumn = false;

        // See Illuminate\Database\Query\Builder::where()
        [$value, $operator] = $this->query->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        $modelSettingsList = $this->model->getSettings();

        if (is_string($column)) {
            if (in_array($column, $modelSettingsList)) {
                $settings[$column] = $value;
            } else {
                $primaryColumn = $column;
            }
        }

        if (is_array($column)) {
            $settings = array_intersect($column, $modelSettingsList);
            $primaryColumn = array_diff($column, $modelSettingsList);
        }

        if (empty($settings)) {
            return parent::where($column, $operator, $value, $boolean);
        }

        $where = [];
        foreach ($settings as $settingName => $settingValue) {
            $where = array_merge($where, [
                'setting_name' => $settingName,
                'setting_value' => $settingValue,
            ]);
        }

        $this->query->whereIn(
            $this->model->getKeyName(),
            fn (QueryBuilder $query) =>
            $query->select($this->getPrimaryKeyName())->from($this->getSettingsTable())->where($where, null, null, $boolean)
        );

        if (!empty($primaryColumn)) {
            parent::where($primaryColumn, $operator, $value, $boolean);
        }

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     * Overrides Illuminate\Database\Query\Builder to support settings in select queries
     *
     * @param  \Illuminate\Contracts\Database\Query\Expression|string  $column
     * @param  string  $boolean
     * @param  bool  $not
     *
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if ($column instanceof Expression || !in_array($column, $this->model->getSettings())) {
            return parent::whereIn($column, $values, $boolean, $not);
        }

        $this->query->whereIn(
            $this->model->getKeyName(),
            fn (QueryBuilder $query) =>
            $query
                ->select($this->getPrimaryKeyName())
                ->from($this->getSettingsTable())
                ->where('setting_name', $column)
                ->whereIn('setting_value', $values, $boolean, $not)
        );

        return $this;
    }

    /**
     * @see \PKP\core\traits\EntityUpdate::getSettingsTable()
     */
    public function getSettingsTable(): ?string
    {
        return $this->model->getSettingsTable();
    }

    /**
     * @see \PKP\core\traits\EntityUpdate::getPrimaryKeyName()
     */
    public function getPrimaryKeyName(): string
    {
        return $this->model->getKeyName();
    }

    /**
     * @return bool whether the property is a setting
     */
    public function isSetting(string $settingName): bool
    {
        return in_array($settingName, $this->model->getSettings());
    }

    /**
     * @see \PKP\core\traits\EntityUpdate::getSchemaName()
     */
    public function getSchemaName(): ?string
    {
        return $this->model->getSchemaName();
    }

    /*
     * Augment model with data from the settings table
     */
    protected function getModelWithSettings(array|string $columns = ['*']): array
    {
        // First, get all Model columns from the main table
        $primaryKey = $this->model->getKeyName();

        $rows = $this->query->get()->keyBy($primaryKey);
        if ($rows->isEmpty()) {
            return $rows->all();
        }

        // Retrieve records from the settings table associated with the primary Model IDs
        $ids = $rows->pluck($primaryKey)->toArray();
        $settings = DB::table($this->model->getSettingsTable())
            ->whereIn($primaryKey, $ids)
            ->get();

        $rows = $rows->all();

        $settings->each(function (\stdClass $setting) use (&$rows, $primaryKey, $columns) {
            $settingModelId = $setting->{$primaryKey};

            // Even for empty('') locale, the multilingual props need to be an array
            if (isset($setting->locale) && $this->isMultilingual($setting->setting_name)) {
                $rows[$settingModelId]->{$setting->setting_name}[$setting->locale] = $setting->setting_value;
            } else {
                $rows[$settingModelId]->{$setting->setting_name} = $setting->setting_value;
            }
        });

        // Include only specified columns
        foreach ($ids as $id) {
            $this->filterRow($rows[$id], $columns);
        }

        return $rows;
    }

    /**
     * If specific columns are selected to fill the Model with, iterate and filter all, which aren't specified
     */
    protected function filterRow(stdClass $row, string|array $columns = ['*']): void
    {
        if ($columns == ['*']) {
            return;
        }

        $columns = Arr::wrap($columns);

        // TODO : Investigate how to handle the camel to snake case issue. related to pkp/pkp-lib#10485
        $settingColumns = $this->model->getSettings();
        $columns = collect($columns)
            ->map(
                fn (string $column): string => in_array($column, $settingColumns)
                    ? $column
                    : Str::snake($column)
            )
            ->toArray();

        foreach ($row as $property => $value) {
            if (!in_array($property, $columns) && isset($row->{$property})) {
                unset($row->{$property});
            }
        }
    }

    /**
     * Checks if setting is multilingual
     */
    protected function isMultilingual(string $settingName): bool
    {
        return in_array($settingName, $this->model->getMultilingualProps());
    }

    /**
     * Get correspondent rows from a settings table
     */
    protected function getSettingRows(mixed $settingValues, int $id): array
    {
        $rows = [];

        $settingValues->each(function (mixed $settingValue, string $settingName) use ($id, &$rows) {
            $settingName = Str::camel($settingName);
            if ($this->isMultilingual($settingName)) {
                foreach ($settingValue as $locale => $localizedValue) {
                    $rows[] = [
                        $this->getPrimaryKeyName() => $id,
                        'locale' => $locale,
                        'setting_name' => $settingName,
                        'setting_value' => $localizedValue,
                    ];
                }
            } else {
                $rows[] = [
                    $this->getPrimaryKeyName() => $id,
                    'locale' => '',
                    'setting_name' => $settingName,
                    'setting_value' => $settingValue,
                ];
            }
        });

        return $rows;
    }
}

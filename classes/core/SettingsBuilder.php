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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class SettingsBuilder extends Builder
{
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
            $rows->all()
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

        $newQuery = clone $this->query;

        if ($primaryValues->isNotEmpty()) {
            $count = parent::update($primaryValues->toArray());
        }

        // FIXME pkp/pkp-lib#10485 Eloquent transforms attributes to snake case, find and override instead of transforming here
        $settingValues = $settingValues->mapWithKeys(
            fn (mixed $value, string $key) => [Str::camel($key) => $value]
        );

        $us = $this->model->getSettingsTable();
        $primaryKey = $this->model->getKeyName();


        $sql = $this->buildUpdateSql($settingValues, $us, $newQuery);

        // Build a query for update
        $settingCount = DB::table($us)->whereIn($us . '.' . $primaryKey, $newQuery->select($primaryKey))
            ->update([$us . '.setting_value' => DB::raw($sql)]);

        return ($count ?? 0) + $settingCount;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     * Overrides Builder's method to insert setting values for a models with
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

        $rows = [];
        $settingValues->each(function (mixed $settingValue, string $settingName) use ($id, &$rows) {
            $settingName = Str::camel($settingName);
            if ($this->isMultilingual($settingName)) {
                foreach ($settingValue as $locale => $localizedValue) {
                    $rows[] = [
                        $this->model->getKeyName() => $id, 'locale' => $locale, 'setting_name' => $settingName, 'setting_value' => $localizedValue
                    ];
                }
            } else {
                $rows[] = [
                    $this->model->getKeyName() => $id, 'locale' => '', 'setting_name' => $settingName, 'setting_value' => $settingValue
                ];
            }
        });

        DB::table($this->model->getSettingsTable())->insert($rows);

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

        DB::table($this->model->getSettingsTable())->where(
            $this->model->getKeyName(),
            $this->model->getRawOriginal($this->model->getKeyName()) ?? $this->model->getKey()
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
            $query->select($this->model->getKeyName())->from($this->model->getSettingsTable())->where($where, null, null, $boolean)
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
                ->select($this->model->getKeyName())
                ->from($this->model->getSettingsTable())
                ->where('setting_name', $column)
                ->whereIn('setting_value', $values, $boolean, $not)
        );

        return $this;
    }

    /*
     * Augment model with data from the settings table
     */
    protected function getModelWithSettings(array|string $columns = ['*']): Collection
    {
        // First, get all Model columns from the main table
        $primaryKey = $this->model->getKeyName();
        $rows = $this->query->get()->keyBy($primaryKey);
        if ($rows->isEmpty()) {
            return $rows;
        }

        // Retrieve records from the settings table associated with the primary Model IDs
        $ids = $rows->pluck($primaryKey)->toArray();
        $settings = DB::table($this->model->getSettingsTable())
            ->whereIn($primaryKey, $ids)
            ->get();

        $settings->each(function (\stdClass $setting) use ($rows, $primaryKey, $columns) {
            $settingModelId = $setting->{$primaryKey};

            // Retract the row and fill it with data from a settings table
            $exactRow = $rows->pull($settingModelId);
            if ($setting->locale) {
                $exactRow->{$setting->setting_name}[$setting->locale] = $setting->setting_value;
            } else {
                $exactRow->{$setting->setting_name} = $setting->setting_value;
            }

            // Include only specified columns
            $exactRow = $this->filterRow($exactRow, $columns);
            $rows->put($settingModelId, $exactRow);
        });

        return $rows;
    }

    /**
     * If specific columns are selected to fill the Model with, iterate and filter all, which aren't specified
     */
    protected function filterRow(stdClass $row, string|array $columns = ['*']): stdClass
    {
        if ($columns == ['*']) {
            return $row;
        }

        $columns = Arr::wrap($columns);
        foreach ($row as $property) {
            if (!in_array($property, $columns)) {
                unset($row->{$property});
            }
        }

        return $row;
    }

    /**
     * @param Collection $settingValues list of setting names as keys and setting values to be updated
     * @param string $us name of the settings table
     * @param QueryBuilder $query original query associated with the Model
     *
     * @return string raw SQL statement
     *
     * Helper method to build a query to update settings with a conditional statement:
     * SET settings_value = CASE WHEN setting_name='' AND locale=''...
     */
    protected function buildUpdateSql(Collection $settingValues, string $us, QueryBuilder $query): string
    {
        $sql = 'CASE ';
        $bindings = [];
        $settingValues->each(function (mixed $settingValue, string $settingName) use (&$sql, &$bindings, $us) {
            if ($this->isMultilingual($settingName)) {
                foreach ($settingValue as $locale => $localizedValue) {
                    $sql .= 'WHEN ' . $us . '.setting_name=? AND ' . $us . '.locale=? THEN ? ';
                    $bindings = array_merge($bindings, [$settingName, $locale, $localizedValue]);
                }
            } else {
                $sql .= 'WHEN ' . $us . '.setting_name=? THEN ? ';
                $bindings = array_merge($bindings, [$settingName, $settingValue]);
            }
        });
        $sql .= 'ELSE setting_value END';

        // Fix the order of bindings in Laravel, user ID in the where statement should be the last
        $query->bindings['where'] = array_merge($bindings, $query->bindings['where']);

        return $sql;
    }

    /**
     * Checks if setting is multilingual
     */
    protected function isMultilingual(string $settingName): bool
    {
        return in_array($settingName, $this->model->getMultilingualProps());
    }
};

<?php

/**
 * @file classes/core/EntityDAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityUpdate
 *
 * @brief A trait for updating data from entity or model in the database
 * Remove this trait and transfer the logic to the PKP\core\SettingsBuilder after all entities are refactored according
 * to the Eloquent pattern
 */

namespace PKP\core\traits;

use Illuminate\Support\Facades\DB;
use PKP\core\DataObject;
use PKP\core\EntityDAO;
use PKP\services\PKPSchemaService;

/**
 * @template T of DataObject
 */
trait EntityUpdate
{
    /**
     * @return ?string One of the \PKP\services\PKPSchemaService::SCHEMA_... constants
     * or null if Schema isn't implemented for the current Model
     */
    abstract public function getSchemaName(): ?string;


    /**
     * @return string|null The name of the settings table
     */
    abstract public function getSettingsTable(): ?string;

    /**
     * @return string The name of the primary key column
     */
    abstract public function getPrimaryKeyName(): string;

    /**
     * Check if property is from a settings table
     */
    abstract public function isSetting(string $settingName): bool;


    /**
     * Update settings of a Model or Entity
     *
     * @param null|mixed $schema
     */
    public function updateSettings(array $props, int $modelId, $schema = null): void
    {
        $schemaService = app()->get('schema'); /** @var PKPSchemaService $schemaService */
        $schemaName = $this->getSchemaName();

        if (is_null($schema)) {
            $schema = $schemaService->get($schemaName);
        }

        if ($schemaName) {
            $props = $schemaService->sanitize($this->getSchemaName(), $props);
        }

        $deleteSettings = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (!$this->isSetting($propName)) {
                continue;
            } elseif (!isset($props[$propName])) {
                $deleteSettings[] = $propName;
                continue;
            }

            if (!empty($propSchema->multilingual)) {

                foreach ($props[$propName] as $localeKey => $localeValue) {
                    // Delete rows with a null value
                    if (is_null($localeValue)) {
                        DB::table($this->getSettingsTable())
                            ->where($this->getPrimaryKeyName(), '=', $modelId)
                            ->where('setting_name', '=', $propName)
                            ->where('locale', '=', $localeKey)
                            ->delete();
                    } else {
                        DB::table($this->getSettingsTable())
                            ->updateOrInsert(
                                [
                                    $this->getPrimaryKeyName() => $modelId,
                                    'locale' => $localeKey,
                                    'setting_name' => $propName,
                                ],
                                [
                                    'setting_value' => method_exists($this, 'convertToDB')
                                        ? $this->convertToDB($localeValue, $schema->properties->{$propName}->type)
                                        : $localeValue
                                ]
                            );
                    }
                }
            } else {
                DB::table($this->getSettingsTable())
                    ->updateOrInsert(
                        [
                            $this->getPrimaryKeyName() => $modelId,
                            'locale' => '',
                            'setting_name' => $propName,
                        ],
                        [
                            'setting_value' => method_exists($this, 'convertToDB')
                                ? $this->convertToDB($props[$propName], $schema->properties->{$propName}->type)
                                : $props[$propName]
                        ]
                    );
            }
        }

        // Entity DAO passes all properties for the update and removes all that aren't set
        if (count($deleteSettings) && is_a($this, EntityDAO::class)) {
            DB::table($this->getSettingsTable())
                ->where($this->getPrimaryKeyName(), '=', $modelId)
                ->whereIn('setting_name', $deleteSettings)
                ->delete();
        }
    }
}

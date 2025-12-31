<?php

/**
 * @file classes/site/SiteDAO.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SiteDAO
 *
 * @see Site
 *
 * @brief Operations for retrieving and modifying the Site object.
 */

namespace PKP\site;

use Illuminate\Support\Facades\DB;
use PKP\services\PKPSchemaService;

class SiteDAO extends \PKP\db\DAO
{
    /** @var array Maps schema properties for the primary table to their column names */
    public $primaryTableColumns = [
        'redirectContextId' => 'redirect_context_id',
        'primaryLocale' => 'primary_locale',
        'minPasswordLength' => 'min_password_length',
        'installedLocales' => 'installed_locales',
        'supportedLocales' => 'supported_locales',
    ];


    /**
     * Retrieve site information.
     */
    public function getSite(): ?site
    {
        $result = $this->retrieve(
            'SELECT * FROM site'
        );

        if ($row = (array) $result->current()) {
            return $this->_fromRow($row);
        }

        return null;
    }

    /**
     * Instantiate and return a new DataObject.
     */
    public function newDataObject(): Site
    {
        return new Site();
    }

    /**
     * @copydoc SchemaDAO::_fromRow()
     */
    public function _fromRow(array $primaryRow, bool $callHook = true): Site
    {
        $schemaService = app()->get('schema');
        $schema = $schemaService->get(PKPSchemaService::SCHEMA_SITE);

        $site = $this->newDataObject();

        foreach ($this->primaryTableColumns as $propName => $column) {
            if (isset($primaryRow[$column])) {
                $site->setData(
                    $propName,
                    $this->convertFromDb(
                        value: $primaryRow[$column],
                        type: $schema->properties->{$propName}->type,
                        encrypt: $schema->properties->{$propName}->encrypt ?? false
                    )
                );
            }
        }

        $result = $this->retrieve('SELECT * FROM site_settings');

        foreach ($result as $settingRow) {
            $settingRow = (array) $settingRow;
            if (!empty($schema->properties->{$settingRow['setting_name']})) {
                $site->setData(
                    $settingRow['setting_name'],
                    $this->convertFromDB(
                        value: $settingRow['setting_value'],
                        type: $schema->properties->{$settingRow['setting_name']}->type,
                        encrypt: $schema->properties->{$settingRow['setting_name']}->encrypt ?? false
                    ),
                    empty($settingRow['locale']) ? null : $settingRow['locale']
                );
            }
        }

        return $site;
    }

    /**
     * Insert site information.
     */
    public function insertSite(Site $site): void
    {
        $type = 'array';
        $this->update(
            'INSERT INTO site
				(redirect_context_id, min_password_length, primary_locale, installed_locales, supported_locales)
				VALUES
				(?, ?, ?, ?, ?)',
            [
                $site->getRedirect(),
                (int) $site->getMinPasswordLength(),
                $site->getPrimaryLocale(),
                $this->convertToDB($site->getInstalledLocales(), $type),
                $this->convertToDB($site->getInstalledLocales(), $type),
            ]
        );
    }

    /**
     * @copydoc SchemaDAO::updateObject
     */
    public function updateObject(Site $site): void
    {
        $schemaService = app()->get('schema');
        $schema = $schemaService->get(PKPSchemaService::SCHEMA_SITE);
        $sanitizedProps = $schemaService->sanitize(PKPSchemaService::SCHEMA_SITE, $site->_data);

        $set = $params = [];
        foreach ($this->primaryTableColumns as $propName => $column) {
            $set[] = $column . ' = ?';
            $property = $schema->properties->{$propName};
            $params[] = $this->convertToDb(
                value: $sanitizedProps[$propName], 
                type: $property->type, 
                nullable: in_array('nullable', $property->validation ?? []),
                encrypt: $property->encrypt ?? false
            );
        }
        $this->update('UPDATE site SET ' . join(',', $set), $params);

        $deleteSettings = [];
        foreach ($schema->properties as $propName => $propSchema) {
            if (array_key_exists($propName, $this->primaryTableColumns)) {
                continue;
            } elseif (!isset($sanitizedProps[$propName])) {
                $deleteSettings[] = $propName;
                continue;
            }
            if (!empty($propSchema->multilingual)) {
                foreach ($sanitizedProps[$propName] as $localeKey => $localeValue) {
                    // Delete rows with a null value
                    if (is_null($localeValue)) {
                        $this->update('DELETE FROM site_settings WHERE setting_name = ? AND locale = ?', [
                            $propName,
                            $localeKey,
                        ]);
                    } else {
                        DB::table('site_settings')->updateOrInsert(
                            ['locale' => $localeKey, 'setting_name' => $propName],
                            [
                                'setting_value' => $this->convertToDB(
                                    value: $localeValue, 
                                    type: $schema->properties->{$propName}->type,
                                    encrypt: $schema->properties->{$propName}->encrypt ?? false
                                )
                            ]
                        );
                    }
                }
            } else {
                DB::table('site_settings')->updateOrInsert(
                    ['locale' => '', 'setting_name' => $propName],
                    [
                        'setting_value' => $this->convertToDB(
                            value: $sanitizedProps[$propName],
                            type: $schema->properties->{$propName}->type,
                            encrypt: $schema->properties->{$propName}->encrypt ?? false
                        )
                    ]
                );
            }
        }

        if (count($deleteSettings)) {
            $deleteSettingNames = join(',', array_map(fn ($settingName) => "'{$settingName}'", $deleteSettings));
            $this->update("DELETE FROM site_settings WHERE setting_name in ({$deleteSettingNames})");
        }
    }
}

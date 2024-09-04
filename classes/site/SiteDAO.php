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
                    $this->convertFromDb($primaryRow[$column], $schema->properties->{$propName}->type)
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
                        $settingRow['setting_value'],
                        $schema->properties->{$settingRow['setting_name']}->type
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
            $params[] = $this->convertToDb($sanitizedProps[$propName], $property->type, in_array('nullable', $property->validation ?? []));
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
                            ['setting_value' => $this->convertToDB($localeValue, $schema->properties->{$propName}->type)]
                        );
                    }
                }
            } else {
                DB::table('site_settings')->updateOrInsert(
                    ['locale' => '', 'setting_name' => $propName],
                    ['setting_value' => $this->convertToDB($sanitizedProps[$propName], $schema->properties->{$propName}->type)]
                );
            }
        }

        if (count($deleteSettings)) {
            $deleteSettingNames = join(',', array_map(fn ($settingName) => "'{$settingName}'", $deleteSettings));
            $this->update("DELETE FROM site_settings WHERE setting_name in ({$deleteSettingNames})");
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\SiteDAO', '\SiteDAO');
}

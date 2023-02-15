<?php

/**
 * @file classes/site/SiteDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SiteDAO
 * @ingroup site
 *
 * @see Site
 *
 * @brief Operations for retrieving and modifying the Site object.
 */

namespace PKP\site;

use APP\core\Services;
use Illuminate\Support\Facades\DB;

use PKP\services\PKPSchemaService;

class SiteDAO extends \PKP\db\DAO
{
    /** @var array Maps schema properties for the primary table to their column names */
    public $primaryTableColumns = [
        'redirect' => 'redirect',
        'primaryLocale' => 'primary_locale',
        'minPasswordLength' => 'min_password_length',
        'installedLocales' => 'installed_locales',
        'supportedLocales' => 'supported_locales',
    ];


    /**
     * Retrieve site information.
     *
     * @return Site
     */
    public function getSite()
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
     *
     * @return Site
     */
    public function newDataObject()
    {
        return new Site();
    }

    /**
     * @copydoc SchemaDAO::_fromRow()
     */
    public function _fromRow($primaryRow, $callHook = true)
    {
        $schemaService = Services::get('schema');
        $schema = $schemaService->get(PKPSchemaService::SCHEMA_SITE);

        $site = $this->newDataObject();

        foreach ($this->primaryTableColumns as $propName => $column) {
            if (isset($primaryRow[$column])) {
                // Backwards-compatible handling of the installedLocales and
                // supportedLocales data. Before 3.2, these were stored as colon-separated
                // strings (eg - en:fr_CA:ar_IQ). In 3.2, these are migrated to
                // serialized arrays defined by the site.json schema. However, some of the
                // older upgrade scripts use site data before the migration is performed,
                // so SiteDAO must be able to return the correct array before the data
                // is migrated. This code checks the format and converts the old data so
                // that calls to $site->getInstalledLocales() and
                // $site->getSupportedLocales() return an appropriate array.
                if (in_array($column, ['installed_locales', 'supported_locales']) &&
                        !is_null($primaryRow[$column]) && strpos($primaryRow[$column], '{') === false && is_null(json_decode($primaryRow[$column]))) {
                    $site->setData($propName, explode(':', $primaryRow[$column]));
                } else {
                    $site->setData(
                        $propName,
                        $this->convertFromDb($primaryRow[$column], $schema->properties->{$propName}->type)
                    );
                }
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
     *
     * @param Site $site
     */
    public function insertSite($site)
    {
        $type = 'array';
        $returner = $this->update(
            'INSERT INTO site
				(redirect, min_password_length, primary_locale, installed_locales, supported_locales)
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
        return $returner;
    }

    /**
     * @copydoc SchemaDAO::updateObject
     */
    public function updateObject($site)
    {
        $schemaService = Services::get('schema');
        $schema = $schemaService->get(PKPSchemaService::SCHEMA_SITE);
        $sanitizedProps = $schemaService->sanitize(PKPSchemaService::SCHEMA_SITE, $site->_data);

        $set = $params = [];
        foreach ($this->primaryTableColumns as $propName => $column) {
            $set[] = $column . ' = ?';
            $params[] = $this->convertToDb($sanitizedProps[$propName], $schema->properties->{$propName}->type);
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
            $deleteSettingNames = join(',', array_map(function ($settingName) {
                return "'${settingName}'";
            }, $deleteSettings));
            $this->update("DELETE FROM site_settings WHERE setting_name in (${deleteSettingNames})");
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\site\SiteDAO', '\SiteDAO');
}

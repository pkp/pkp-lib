<?php

/**
 * @file classes/plugins/PluginSettingsDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginSettingsDAO
 *
 * @ingroup plugins
 *
 * @see Plugin
 *
 * @brief Operations for retrieving and modifying plugin settings.
 */

namespace PKP\plugins;

use APP\core\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\xml\PKPXMLParser;

class PluginSettingsDAO extends \PKP\db\DAO
{
    public const CACHE_LIFETIME = 24 * 60 * 60;

    protected function _getCacheId(?int $contextId, string $pluginName, bool $isNormalized = false): string
    {
        if (!$isNormalized) {
            $pluginName = static::_normalizePluginName($pluginName);
        }
        $contextId = (int) $contextId;
        return "pluginSettings-{$contextId}-{$pluginName}";
    }

    protected function _normalizePluginName(string $pluginName): string
    {
        return strtolower($pluginName);
    }

    /**
     * Retrieve a plugin setting value.
     */
    public function getSetting(?int $contextId, string $pluginName, string $settingName): mixed
    {
        $pluginSettings = Cache::remember(
            $this->_getCacheId($contextId, $pluginName),
            static::CACHE_LIFETIME,
            fn () => $this->getPluginSettings($contextId, $pluginName)
        );

        return $pluginSettings[$settingName] ?? null;
    }

    /**
     * Determine if the plugin setting exists in the database.
     */
    public function settingExists(?int $contextId, string $pluginName, string $settingName): bool
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM plugin_settings WHERE plugin_name = ? AND COALESCE(context_id, 0) = ? AND setting_name = ?',
            [static::_normalizePluginName($pluginName), (int) $contextId, $settingName]
        );
        $row = $result->current();
        return $row && $row->row_count;
    }

    /**
     * Retrieve all settings for a plugin from the database (and update the cache).
     */
    public function getPluginSettings(?int $contextId, string $pluginName): array
    {
        // Normalize plug-in name to lower case.
        $pluginName = $this->_normalizePluginName($pluginName);

        $result = DB::table('plugin_settings')->where('plugin_name', $pluginName)->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])->get();
        $settings = $result->mapWithKeys(fn ($row) => [$row->setting_name => $this->convertFromDB($row->setting_value, $row->setting_type)]);
        $settings = $settings->toArray();
        Cache::put($this->_getCacheId($contextId, $pluginName, true), $settings, static::CACHE_LIFETIME);
        return $settings;
    }

    /**
     * Add/update a plugin setting.
     *
     * @param $type data type of the setting. If omitted, type will be guessed
     */
    public function updateSetting(?int $contextId, string $pluginName, string $settingName, mixed $value, ?string $type = null): void
    {
        // Normalize the plug-in name to lower case.
        $pluginName = static::_normalizePluginName($pluginName);

        Cache::forget($this->_getCacheId($contextId, $pluginName, true));

        $value = $this->convertToDB($value, $type);

        DB::table('plugin_settings')->updateOrInsert(
            ['context_id' => $contextId, 'plugin_name' => $pluginName, 'setting_name' => $settingName],
            ['setting_value' => $value, 'setting_type' => $type]
        );
    }

    /**
     * Delete a plugin setting.
     */
    public function deleteSetting(?int $contextId, string $pluginName, string $settingName): void
    {
        // Normalize the plug-in name to lower case.
        $pluginName = static::_normalizePluginName($pluginName);

        Cache::forget($this->_getCacheId($contextId, $pluginName, true));

        DB::table('plugin_settings')
            ->where('plugin_Name', $pluginName)
            ->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])
            ->where('setting_name', $settingName)
            ->delete();
    }

    /**
     * Delete all settings for a plugin.
     */
    public function deleteSettingsByPlugin(?int $contextId, string $pluginName): void
    {
        // Normalize the plug-in name to lower case.
        $pluginName = static::_normalizePluginName($pluginName);

        Cache::forget($this->_getCacheId($contextId, $pluginName, true));

        DB::table('plugin_settings')
            ->where('plugin_Name', $pluginName)
            ->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])
            ->delete();
    }

    /**
     * Delete all settings for a context.
     */
    public function deleteByContextId(?int $contextId): void
    {
        DB::table('plugin_settings')->whereRaw('COALESCE(context_id, 0) = ?', [(int) $contextId])->delete();
    }

    /**
     * Used internally by installSettings to perform variable and translation replacements.
     *
     * @param $rawInput contains text including variable and/or translate replacements.
     * @param $paramArray contains variables for replacement
     *
     */
    public function _performReplacement(?string $rawInput, array $paramArray = []): string
    {
        $value = preg_replace_callback('{{translate key="([^"]+)"}}', fn ($matches) => __($matches[1]), (string) $rawInput);
        foreach ($paramArray as $pKey => $pValue) {
            $value = str_replace('{$' . $pKey . '}', $pValue, $value);
        }
        return $value;
    }

    /**
     * Used internally by installSettings to recursively build nested arrays.
     * Deals with translation and variable replacement calls.
     *
     * @param object $node XMLNode <array> tag
     * @param array $paramArray Parameters to be replaced in key/value contents
     */
    public function _buildObject($node, $paramArray = []): array
    {
        $value = [];
        foreach ($node->getChildren() as $element) {
            $key = $element->getAttribute('key');
            $childArray = $element->getChildByName('array');
            if (isset($childArray)) {
                $content = $this->_buildObject($childArray, $paramArray);
            } else {
                $content = $this->_performReplacement($element->getValue(), $paramArray);
            }
            if (!empty($key)) {
                $key = $this->_performReplacement($key, $paramArray);
                $value[$key] = $content;
            } else {
                $value[] = $content;
            }
        }
        return $value;
    }

    /**
     * Install plugin settings from an XML file.
     *
     * @param $pluginName name of plugin for settings to apply to
     * @param $filename Name of XML file to parse and install
     * @param $paramArray Optional parameters for variable replacement in settings
     */
    public function installSettings(?int $contextId, string $pluginName, string $filename, array $paramArray = []): bool
    {
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filename);

        if (!$tree) {
            return false;
        }

        // Check for existing settings and leave them if they are already in place.
        $currentSettings = $this->getPluginSettings($contextId, $pluginName);

        foreach ($tree->getChildren() as $setting) {
            $nameNode = $setting->getChildByName('name');
            $valueNode = $setting->getChildByName('value');

            if (isset($nameNode) && isset($valueNode)) {
                $type = $setting->getAttribute('type');
                $name = $nameNode->getValue();

                // If the setting already exists, respect it.
                if (isset($currentSettings[$name])) {
                    continue;
                }

                if ($type == 'object') {
                    $arrayNode = $valueNode->getChildByName('array');
                    $value = $this->_buildObject($arrayNode, $paramArray);
                } else {
                    $value = $this->_performReplacement($valueNode->getValue(), $paramArray);
                }

                // Replace translate calls with translated content
                $this->updateSetting($contextId, $pluginName, $name, $value, $type);
            }
        }
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PluginSettingsDAO', '\PluginSettingsDAO');
}

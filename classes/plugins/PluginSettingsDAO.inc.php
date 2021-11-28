<?php

/**
 * @file classes/plugins/PluginSettingsDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginSettingsDAO
 * @ingroup plugins
 *
 * @see Plugin
 *
 * @brief Operations for retrieving and modifying plugin settings.
 */

namespace PKP\plugins;

use PKP\cache\CacheManager;
use PKP\xml\PKPXMLParser;

class PluginSettingsDAO extends \PKP\db\DAO
{
    /**
     * Get the cache for plugin settings.
     *
     * @param int $contextId Context ID
     * @param string $pluginName Plugin symbolic name
     *
     * @return Cache
     */
    public function _getCache($contextId, $pluginName)
    {
        static $settingCache = [];
        return $settingCache[$contextId][$pluginName] ??= CacheManager::getManager()->getCache(
            'pluginSettings-' . $contextId,
            $pluginName,
            [$this, '_cacheMiss']
        );
    }

    /**
     * Retrieve a plugin setting value.
     *
     * @param int $contextId Context ID
     * @param string $pluginName Plugin symbolic name
     * @param string $name Setting name
     */
    public function getSetting($contextId, $pluginName, $name)
    {
        // Normalize the plug-in name to lower case.
        $pluginName = strtolower_codesafe($pluginName);

        // Retrieve the setting.
        $cache = $this->_getCache($contextId, $pluginName);
        return $cache->get($name);
    }

    /**
     * Does the plugin setting exist.
     *
     * @param int $contextId Context ID
     * @param string $pluginName Plugin symbolic name
     * @param string $name Setting name
     *
     * @return bool
     */
    public function settingExists($contextId, $pluginName, $name)
    {
        $pluginName = strtolower_codesafe($pluginName);
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM plugin_settings WHERE plugin_name = ? AND context_id = ? AND setting_name = ?',
            [$pluginName, (int) $contextId, $name]
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
    }

    /**
     * Callback for a cache miss.
     *
     * @param object $cache Cache object
     * @param string $id Identifier to look up in cache
     */
    public function _cacheMiss($cache, $id)
    {
        $contextParts = explode('-', $cache->getContext());
        $contextId = array_pop($contextParts);
        $settings = $this->getPluginSettings($contextId, $cache->getCacheId());
        if (!isset($settings[$id])) {
            // Make sure that even null values are cached
            $cache->setCache($id, null);
            return null;
        }
        return $settings[$id];
    }

    /**
     * Retrieve and cache all settings for a plugin.
     *
     * @param int $contextId Context ID
     * @param string $pluginName Plugin symbolic name
     *
     * @return array
     */
    public function getPluginSettings($contextId, $pluginName)
    {
        // Normalize plug-in name to lower case.
        $pluginName = strtolower_codesafe($pluginName);

        $result = $this->retrieve(
            'SELECT setting_name, setting_value, setting_type FROM plugin_settings WHERE plugin_name = ? AND context_id = ?',
            [$pluginName, (int) $contextId]
        );

        $pluginSettings = [];
        foreach ($result as $row) {
            $pluginSettings[$row->setting_name] = $this->convertFromDB($row->setting_value, $row->setting_type);
        }

        $cache = $this->_getCache($contextId, $pluginName);
        $cache->setEntireCache($pluginSettings);

        return $pluginSettings;
    }

    /**
     * Add/update a plugin setting.
     *
     * @param int $contextId Context ID
     * @param string $pluginName Symbolic plugin name
     * @param string $name Setting name
     * @param mixed $value Setting value
     * @param string $type data type of the setting. If omitted, type will be guessed
     *
     * @return int Return value from ADODB's replace() function.
     */
    public function updateSetting($contextId, $pluginName, $name, $value, $type = null)
    {
        // Normalize the plug-in name to lower case.
        $pluginName = strtolower_codesafe($pluginName);

        $cache = $this->_getCache($contextId, $pluginName);
        $cache->setCache($name, $value);

        $value = $this->convertToDB($value, $type);

        return $this->replace(
            'plugin_settings',
            [
                'context_id' => (int) $contextId,
                'plugin_name' => $pluginName,
                'setting_name' => $name,
                'setting_value' => $value,
                'setting_type' => $type,
            ],
            ['context_id', 'plugin_name', 'setting_name']
        );
    }

    /**
     * Delete a plugin setting.
     *
     * @param int $contextId
     * @param int $pluginName
     * @param string $name
     */
    public function deleteSetting($contextId, $pluginName, $name)
    {
        // Normalize the plug-in name to lower case.
        $pluginName = strtolower_codesafe($pluginName);

        $cache = $this->_getCache($contextId, $pluginName);
        $cache->setCache($name, null);

        return $this->update(
            'DELETE FROM plugin_settings WHERE plugin_name = ? AND setting_name = ? AND context_id = ?',
            [$pluginName, $name, (int) $contextId]
        );
    }

    /**
     * Delete all settings for a plugin.
     *
     * @param int $contextId
     * @param string $pluginName
     */
    public function deleteSettingsByPlugin($contextId, $pluginName)
    {
        // Normalize the plug-in name to lower case.
        $pluginName = strtolower_codesafe($pluginName);

        $cache = $this->_getCache($contextId, $pluginName);
        $cache->flush();

        return $this->update(
            'DELETE FROM plugin_settings WHERE context_id = ? AND plugin_name = ?',
            [(int) $contextId, $pluginName]
        );
    }

    /**
     * Delete all settings for a context.
     *
     * @param int $contextId
     */
    public function deleteByContextId($contextId)
    {
        return $this->update(
            'DELETE FROM plugin_settings WHERE context_id = ?',
            [(int) $contextId]
        );
    }

    /**
     * Used internally by installSettings to perform variable and translation replacements.
     *
     * @param string $rawInput contains text including variable and/or translate replacements.
     * @param array $paramArray contains variables for replacement
     *
     * @return string
     */
    public function _performReplacement($rawInput, $paramArray = [])
    {
        $value = preg_replace_callback('{{translate key="([^"]+)"}}', function ($matches) {
            return __($matches[1]);
        }, $rawInput);
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
    public function _buildObject($node, $paramArray = [])
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
     * @param string $pluginName name of plugin for settings to apply to
     * @param string $filename Name of XML file to parse and install
     * @param array $paramArray Optional parameters for variable replacement in settings
     */
    public function installSettings($contextId, $pluginName, $filename, $paramArray = [])
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
    }
}

/**
 * Used internally by plugin setting installation code to perform translation
 * function.
 *
 * @param array $matches
 *
 * @return string
 */
function _installer_plugin_regexp_callback($matches)
{
    return __($matches[1]);
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\PluginSettingsDAO', '\PluginSettingsDAO');
}

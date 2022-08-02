<?php

/**
 * @file classes/plugins/ThemePlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThemePlugin
 * @ingroup plugins
 *
 * @brief Abstract class for theme plugins
 */

namespace PKP\plugins;

define('LESS_FILENAME_SUFFIX', '.less');
define('THEME_OPTION_PREFIX', 'themeOption_');

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\statistics\StatisticsHelper;
use APP\template\TemplateManager;
use Exception;
use PKP\cache\CacheManager;
use PKP\cache\FileCache;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\session\SessionManager;

abstract class ThemePlugin extends LazyLoadPlugin
{
    /**
     * Collection of styles
     *
     * @see self::_registerStyles
     *
     * @var array $styles
     */
    public $styles = [];

    /**
     * Collection of scripts
     *
     * @see self::_registerScripts
     *
     * @var array $scripts
     */
    public $scripts = [];

    /**
     * Theme-specific options
     *
     * @var array; $options
     */
    public $options = [];

    /**
     * Theme-specific navigation menu areas
     *
     * @var array; $menuAreas
     */
    public $menuAreas = [];

    /**
     * Parent theme (optional)
     *
     * @var ThemePlugin $parent
     */
    public $parent;

    /**
     * Stored reference to option values
     *
     * A null value indicates that no lookup has occured. If no options are set,
     * the lookup will assign an empty array.
     *
     * @var null|array; $_optionValues
     */
    protected $_optionValues = null;

    /**
     * @copydoc Plugin::register
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        // Don't perform any futher operations if theme is not currently active
        if (!$this->isActive()) {
            return true;
        }

        // Themes must initialize their functionality after all theme plugins
        // have been loaded in order to make use of parent/child theme
        // relationships
        HookRegistry::register('PluginRegistry::categoryLoaded::themes', [$this, 'themeRegistered']);
        HookRegistry::register('PluginRegistry::categoryLoaded::themes', [$this, 'initAfter']);

        // Allow themes to override plugin template files
        HookRegistry::register('TemplateResource::getFilename', [$this, '_overridePluginTemplates']);

        return true;
    }

    /**
     * Fire the init() method when a theme is registered
     *
     * @param array $themes List of all loaded themes
     */
    public function themeRegistered($themes)
    {

        // Don't fully initialize the theme until OJS is installed, so that
        // there are no requests to the database before it exists
        if (SessionManager::isDisabled()) {
            return;
        }

        $this->init();
    }

    /**
     * The primary method themes should use to add styles, scripts and fonts,
     * or register hooks. This method is only fired for the currently active
     * theme.
     *
     */
    abstract public function init();

    /**
     * Perform actions after the theme has been initialized
     *
     * Registers templates, styles and scripts that have been added by the
     * theme or any parent themes
     */
    public function initAfter()
    {
        $this->_registerTemplates();
        $this->_registerStyles();
        $this->_registerScripts();
    }

    /**
     * Determine whether or not this plugin is currently active
     *
     * This only returns true if the theme is currently the selected theme
     * in a given context. Use self::getEnabled() if you want to know if the
     * theme is available for use on the site.
     *
     * @return bool
     */
    public function isActive()
    {
        if (SessionManager::isDisabled()) {
            return false;
        }
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if ($context instanceof Context) {
            $activeTheme = $context->getData('themePluginPath');
        } else {
            $site = $request->getSite();
            $activeTheme = $site->getData('themePluginPath');
        }

        return $activeTheme == basename($this->getPluginPath());
    }

    /**
     * Add a stylesheet to load with this theme
     *
     * Style paths with a .less extension will be compiled and redirected to
     * the compiled file.
     *
     * @param string $name A name for this stylesheet
     * @param string $style The stylesheet. Should be a path relative to the
     *   theme directory or, if the `inline` argument is included, style data to
     *   be output.
     * @param array $args Optional arguments hash. Supported args:
     *   'context': Whether to load this on the `frontend` or `backend`.
     *      default: `frontend`
     *   'priority': Controls order in which styles are printed
     *   'addLess': Additional LESS files to process before compiling. Array
     *   'addLessVariables': A string containing additional LESS variables to
     *      parse before compiling. Example: "@bg:#000;"
     *   `inline` bool Whether the $style value should be output directly as
     *      style data.
     */
    public function addStyle($name, $style, $args = [])
    {

        // Pass a file path for LESS files
        if (substr($style, (strlen(LESS_FILENAME_SUFFIX) * -1)) === LESS_FILENAME_SUFFIX) {
            $args['style'] = $this->_getBaseDir($style);

        // Pass a URL for other files
        } elseif (empty($args['inline'])) {
            if (isset($args['baseUrl'])) {
                $args['style'] = $args['baseUrl'] . $style;
            } else {
                $args['style'] = $this->_getBaseUrl($style);
            }

        // Leave inlined styles alone
        } else {
            $args['style'] = $style;
        }

        // Generate file paths for any additional LESS files to compile with
        // this style
        if (isset($args['addLess'])) {
            foreach ($args['addLess'] as &$file) {
                $file = $this->_getBaseDir($file);
            }
        }

        $this->styles[$name] = $args;
    }

    /**
     * Modify the params of an existing stylesheet
     *
     * @param string $name The name of the stylesheet to modify
     * @param array $args Parameters to modify.
     *
     * @see self::addStyle()
     */
    public function modifyStyle($name, $args = [])
    {
        $style = &$this->getStyle($name);

        if (empty($style)) {
            return;
        }

        if (isset($args['addLess'])) {
            foreach ($args['addLess'] as &$file) {
                $file = $this->_getBaseDir($file);
            }
        }

        if (isset($args['style']) && !isset($args['inline'])) {
            $args['style'] = substr($args['style'], (strlen(LESS_FILENAME_SUFFIX) * -1)) == LESS_FILENAME_SUFFIX ? $this->_getBaseDir($args['style']) : $this->_getBaseUrl($args['style']);
        }

        $style = array_merge_recursive($style, $args);
    }

    /**
     * Remove a registered stylesheet
     *
     * @param string $name The name of the stylesheet to remove
     *
     * @return bool Whether or not the stylesheet was found and removed.
     */
    public function removeStyle($name)
    {
        if (isset($this->styles[$name])) {
            unset($this->styles[$name]);
            return true;
        }

        return $this->parent ? $this->parent->removeStyle($name) : false;
    }

    /**
     * Get a style from this theme or any parent theme
     *
     * @param string $name The name of the style to retrieve
     *
     * @return array|null Reference to the style or null if not found
     */
    public function &getStyle($name)
    {

        // Search this theme
        if (isset($this->styles[$name])) {
            $style = &$this->styles[$name];
            return $style;
        }

        // If no parent theme, no style was found
        if (!isset($this->parent)) {
            $style = null;
            return $style;
        }

        return $this->parent->getStyle($name);
    }

    /**
     * Add a script to load with this theme
     *
     * @param string $name A name for this script
     * @param string $script The script to be included. Should be path relative
     *   to the theme or, if the `inline` argument is included, script data to
     *   be output.
     * @param array $args Optional arguments hash. Supported args:
     *   `context` string Whether to load this on the `frontend` or `backend`.
     *      default: frontend
     *   `priority` int Controls order in which scripts are printed
     *      default: TemplateManager::STYLE_SEQUENCE_NORMAL
     *   `inline` bool Whether the $script value should be output directly as
     *      script data. Used to pass backend data to the scripts.
     */
    public function addScript($name, $script, $args = [])
    {
        if (!empty($args['inline'])) {
            $args['script'] = $script;
        } elseif (isset($args['baseUrl'])) {
            $args['script'] = $args['baseUrl'] . $script;
        } else {
            $args['script'] = $this->_getBaseUrl($script);
        }

        $this->scripts[$name] = $args;
    }

    /**
     * Modify the params of an existing script
     *
     * @param string $name The name of the script to modify
     * @param array $args Parameters to modify.
     *
     * @see self::addScript()
     */
    public function modifyScript($name, $args = [])
    {
        $script = &$this->getScript($name);

        if (empty($script)) {
            return;
        }

        if (isset($args['path'])) {
            $args['path'] = $this->_getBaseUrl($args['path']);
        }

        $script = array_merge($script, $args);
    }

    /**
     * Remove a registered script
     *
     * @param string $name The name of the script to remove
     *
     * @return bool Whether or not the stylesheet was found and removed.
     */
    public function removeScript($name)
    {
        if (isset($this->scripts[$name])) {
            unset($this->scripts[$name]);
            return true;
        }

        return $this->parent ? $this->parent->removeScript($name) : false;
    }

    /**
     * Get a script from this theme or any parent theme
     *
     * @param string $name The name of the script to retrieve
     *
     * @return array|null Reference to the script or null if not found
     */
    public function &getScript($name)
    {

        // Search this theme
        if (isset($this->scripts[$name])) {
            $style = &$this->scripts[$name];
            return $style;
        }

        // If no parent theme, no script was found
        if (!isset($this->parent)) {
            return;
        }

        return $this->parent->getScript($name);
    }

    /**
     * Add a theme option
     *
     * Theme options are added programmatically to the Settings > Website >
     * Appearance form when this theme is activated. Common options are
     * colour and typography selectors.
     *
     * @param string $name Unique name for this setting
     * @param string $type One of the Field* class names
     * @param array $args Optional parameters defining this setting. Some setting
     *   types may accept or require additional arguments.
     *  `label` string Locale key for a label for this field.
     *  `description` string Locale key for a description for this field.
     *  `default` mixed A default value. Default: ''
     */
    public function addOption($name, $type, $args = [])
    {
        if (!empty($this->options[$name])) {
            return;
        }

        // Convert theme option types from before v3.2
        if (in_array($type, ['text', 'colour', 'radio'])) {
            if (isset($args['label'])) {
                $args['label'] = __($args['label']);
            }
            if (isset($args['description'])) {
                $args['description'] = __($args['description']);
            }
            switch ($type) {
                case 'text':
                    $type = 'FieldText';
                    break;
                case 'colour':
                    $type = 'FieldColor';
                    break;
                case 'radio':
                    $type = 'FieldOptions';
                    $args['type'] = 'radio';
                    if (!empty($args['options'])) {
                        $options = [];
                        foreach ($args['options'] as $optionValue => $optionLabel) {
                            $options[] = ['value' => $optionValue, 'label' => __($optionLabel)];
                        }
                        $args['options'] = $options;
                    }
                    break;
            }
        }

        $class = 'PKP\components\forms\\' . $type;
        try {
            $this->options[$name] = new $class($name, $args);
        } catch (Exception $e) {
            $class = 'APP\components\forms\\' . $type;
            try {
                $this->options[$name] = new $class($name, $args);
            } catch (Exception $e) {
                throw new Exception(sprintf(
                    'The %s class was not found for the theme option, %s,  defined by %s or one of its parent themes.',
                    $type,
                    $name,
                    $this->getDisplayName()
                ));
            }
        }
    }

    /**
     * Get the value of an option or default if the option is not set
     *
     * @param string $name The name of the option value to retrieve
     *
     * @return mixed The value of the option. Will return a default if set in
     *  the option config. False if no option exists. Null if no value or default
     *  exists.
     */
    public function getOption($name)
    {

        // Check if this is a valid option
        if (!isset($this->options[$name])) {
            return $this->parent ? $this->parent->getOption($name) : false;
        }

        // Retrieve option values if they haven't been loaded yet
        if (is_null($this->_optionValues)) {
            $context = Application::get()->getRequest()->getContext();
            $contextId = $context ? $context->getId() : \PKP\core\PKPApplication::CONTEXT_ID_NONE;
            $this->_optionValues = $this->getOptionValues($contextId);
        }

        if (isset($this->_optionValues[$name])) {
            return $this->_optionValues[$name];
        }

        // Return a default if no value is set
        if (isset($this->options[$name])) {
            $option = $this->options[$name];
        } elseif ($this->parent) {
            $option = $this->parent->getOption($name);
        }
        return $option->default ?? null;
    }

    /**
     * Get an option's configuration settings
     *
     * This retrives option settings for any option attached to this theme or
     * any parent theme.
     *
     * @param string $name The name of the option config to retrieve
     *
     * @return false|array The config array for this option. Or false if no
     *  config is found.
     */
    public function getOptionConfig($name)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }

        return $this->parent ? $this->parent->getOptionConfig($name) : false;
    }

    /**
     * Get all options' configuration settings.
     *
     * This retrieves a single array containing options settings for this
     * theme and any parent themes.
     *
     * @return array
     */
    public function getOptionsConfig()
    {
        if (!$this->parent) {
            return $this->options;
        }

        return array_merge(
            $this->parent->getOptionsConfig(),
            $this->options
        );
    }

    /**
     * Modify option configuration settings
     *
     * @deprecated Unnecessary since 3.2 because options are stored as objects,
     *  so changes can be made directly (via reference) and args don't need to be
     *  manually merged
     *
     * @param string $name The name of the option config to retrieve
     * @param array $args The new configuration settings for this option
     *
     * @return bool Whether the option was found and the config was updated.
     */
    public function modifyOptionsConfig($name, $args = [])
    {
        $option = $this->getOption($name);
        foreach ($args as $key => $value) {
            if (property_exists($option, $key)) {
                $option->{$key} = $value;
            }
        }
    }

    /**
     * Remove an option
     *
     * @param string $name The name of the option to remove
     *
     * @return bool Whether the option was found and removed
     */
    public function removeOption($name)
    {
        if (isset($this->options[$name])) {
            unset($this->options[$name]);
            return true;
        }

        return $this->parent ? $this->parent->removeOption($name) : false;
    }

    /**
     * Get all option values
     *
     * This retrieves a single array containing option values for this theme
     * and any parent themes.
     *
     * @param int $contextId
     *
     * @return array
     */
    public function getOptionValues($contextId)
    {
        $pluginSettingsDAO = DAORegistry::getDAO('PluginSettingsDAO');

        $return = [];
        $values = $pluginSettingsDAO->getPluginSettings($contextId, $this->getName());
        foreach ($this->options as $optionName => $optionConfig) {
            $value = $values[$optionName] ?? null;
            // Convert values stored in the db to the type of the default value
            if (!is_null($optionConfig->default)) {
                switch (gettype($optionConfig->default)) {
                    case 'boolean':
                        $value = !$value || $value === 'false' ? false : true;
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'array':
                        $value = $value === null ? [] : unserialize($value);
                        break;
                }
            }
            $return[$optionName] = $value;
        }

        if (!$this->parent) {
            return $return;
        }

        return array_merge(
            $this->parent->getOptionValues($contextId),
            $return
        );
    }

    /**
     * Overwrite this function to perform any validation on options before they
     * are saved
     *
     * If this is a child theme, you must call $this->parent->validateOptions() to
     * perform any validation defined on the parent theme.
     *
     * @param array $options Key/value list of options to validate
     * @param string $themePluginPath The theme these options are for
     * @param int $contextId The context these theme options are for, or
     *  CONTEXT_ID_NONE for the site-wide settings.
     * @param Request $request
     *
     * @return array List of errors with option name as the key and the value as
     *  an array of error messages. Example:
     *  [
     *    'color' => [
     *      'This color is too dark for this area and some people will not be able to read it.',
     *    ]
     *  ]
     */
    public function validateOptions($options, $themePluginPath, $contextId, $request)
    {
        return [];
    }

    /**
     * Sanitize and save a theme option
     *
     * @param string $name A unique id for the option to save
     * @param mixed $value The new value to save
     * @param int $contextId Optional context id. Defaults to the current
     *  context
     */
    public function saveOption($name, $value, $contextId = null)
    {
        $option = !empty($this->options[$name]) ? $this->options[$name] : null;

        if (is_null($option)) {
            return $this->parent ? $this->parent->saveOption($name, $value, $contextId) : false;
        }

        if (is_null($contextId)) {
            $context = Application::get()->getRequest()->getContext();
            $contextId = $context->getId();
        }

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */

        // Remove setting row for empty string values (but not all falsey values)
        if ($value === '') {
            $pluginSettingsDao->deleteSetting($contextId, $this->getName(), $name);
        } else {
            $type = $pluginSettingsDao->getType($value);
            $value = $pluginSettingsDao->convertToDb($value, $type);
            $this->updateSetting($contextId, $name, $value, $type);
        }
    }

    /**
     * Register a navigation menu area for this theme
     *
     * @param string|array $menuAreas One or more menu area names
     */
    public function addMenuArea($menuAreas)
    {
        if (!is_array($menuAreas)) {
            $menuAreas = [$menuAreas];
        }

        $this->menuAreas = array_merge($this->menuAreas, $menuAreas);
    }

    /**
     * Remove a registered navigation menu area
     *
     * @param string $menuArea The menu area to remove
     *
     * @return bool Whether or not the menuArea was found and removed.
     */
    public function removeMenuArea($menuArea)
    {
        $index = array_search($menuArea, $this->menuAreas);
        if ($index !== false) {
            array_splice($this->menuAreas, $index, 1);
            return true;
        }

        return $this->parent ? $this->parent->removeMenuArea($menuArea) : false;
    }

    /**
     * Get all menu areas registered by this theme and any parents
     *
     * @param array $existingAreas Any existing menu areas from child themes
     *
     * @return array All menua reas
     */
    public function getMenuAreas($existingAreas = [])
    {
        $existingAreas = array_unique(array_merge($this->menuAreas, $existingAreas));

        return $this->parent ? $this->parent->getMenuAreas($existingAreas) : $existingAreas;
    }

    /**
     * Set a parent theme for this theme
     *
     * @param string $parent Key in the plugin registry for the parent theme
     */
    public function setParent($parent)
    {
        $parent = PluginRegistry::getPlugin('themes', $parent);

        if (!($parent instanceof self)) {
            return;
        }

        $this->parent = $parent;
        $this->parent->init();
    }

    /**
     * Register directories to search for template files
     *
     */
    private function _registerTemplates()
    {

        // Register parent theme template directory
        if (isset($this->parent) && $this->parent instanceof self) {
            $this->parent->_registerTemplates();
        }

        // Register this theme's template directory
        $request = Application::get()->getRequest();
        $templateManager = TemplateManager::getManager($request);
        $templateManager->addTemplateDir($this->_getBaseDir('templates'));
    }

    /**
     * Register stylesheets and font assets
     *
     * Passes styles defined by the theme to the template manager for handling.
     *
     */
    private function _registerStyles()
    {
        if (isset($this->parent)) {
            $this->parent->_registerStyles();
        }

        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $templateManager = TemplateManager::getManager($request);

        foreach ($this->styles as $name => $data) {
            if (empty($data['style'])) {
                continue;
            }

            // Compile LESS files
            if ($dispatcher && substr($data['style'], (strlen(LESS_FILENAME_SUFFIX) * -1)) == LESS_FILENAME_SUFFIX) {
                $styles = $dispatcher->url(
                    $request,
                    PKPApplication::ROUTE_COMPONENT,
                    null,
                    'page.PageHandler',
                    'css',
                    null,
                    [
                        'name' => $name,
                    ]
                );
            } else {
                $styles = $data['style'];
            }

            unset($data['style']);

            $templateManager->addStylesheet($name, $styles, $data);
        }
    }

    /**
     * Register script assets
     *
     * Passes scripts defined by the theme to the template manager for handling.
     *
     */
    public function _registerScripts()
    {
        if (isset($this->parent)) {
            $this->parent->_registerScripts();
        }

        $request = Application::get()->getRequest();
        $templateManager = TemplateManager::getManager($request);

        foreach ($this->scripts as $name => $data) {
            $script = $data['script'];
            unset($data['script']);
            $templateManager->addJavaScript($name, $script, $data);
        }
    }

    /**
     * Get the base URL to be used for file paths
     *
     * A base URL for loading LESS/CSS/JS files in <link> elements. It will
     * also be set to the @baseUrl variable before LESS files are compiloed so
     * that images and fonts can be located.
     *
     * @param string $path An optional path to append to the base
     *
     * @return string
     */
    public function _getBaseUrl($path = '')
    {
        $request = Application::get()->getRequest();
        $path = empty($path) ? '' : "/${path}";
        return "{$request->getBaseUrl()}/{$this->getPluginPath()}$path";
    }

    /**
     * Get the base path to be used for file references
     *
     * @param string $path An optional path to append to the base
     *
     * @return string
     */
    public function _getBaseDir($path = '')
    {
        $path = empty($path) ? '' : "/${path}";
        return Core::getBaseDir() . "/{$this->getPluginPath()}$path";
    }

    /**
     * Check if the passed colour is dark
     *
     * This is a utility function to determine the darkness of a hex colour. This
     * is designed to be used in theme colour options, so that text can be
     * adjusted to ensure it's readable on light or dark backgrounds. You can
     * specify the brightness threshold by passing in a $limit value. Higher
     * values are brighter.
     *
     * Based on: http://stackoverflow.com/a/8468448/1723499
     *
     * @since 0.1
     */
    public function isColourDark($color, $limit = 130)
    {
        $color = str_replace('#', '', $color);
        $r = hexdec(substr($color, 0, 2));
        $g = hexdec(substr($color, 2, 2));
        $b = hexdec(substr($color, 4, 2));
        $contrast = sqrt(
            $r * $r * .241 +
            $g * $g * .691 +
            $b * $b * .068
        );
        return $contrast < $limit;
    }

    /**
     * Add usage statistics graph to submission view page
     */
    public function displayUsageStatsGraph(int $submissionId): void
    {
        $this->addUsageStatsJavascriptData($this->getAllDownloadsStats($submissionId), $submissionId);
        $this->loadChartJavascript();
    }

    /**
     * Add submission's monthly statistics data to the script data output for graph display
     */
    protected function addUsageStatsJavascriptData(array $statsByMonth, int $submissionId): void
    {
        // Initialize the name space
        $script_data = 'var pkpUsageStats = pkpUsageStats || {};';
        $script_data .= 'pkpUsageStats.data = pkpUsageStats.data || {};';
        $script_data .= 'pkpUsageStats.data.Submission = pkpUsageStats.data.Submission || {};';
        $namespace = 'Submission[' . $submissionId . ']';
        $script_data .= 'pkpUsageStats.data.' . $namespace . ' = ' . json_encode($statsByMonth) . ';';

        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->addJavaScript(
            'pkpUsageStatsData',
            $script_data,
            [
                'inline' => true,
                'contexts' => $this->getSubmissionViewContext(),
            ]
        );
    }

    /**
     * Load JavaScript assets for usage statistics display and pass data to the scripts
     */
    protected function loadChartJavascript(): void
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);

        // Register Chart.js on the frontend article view
        $min = Config::getVar('general', 'enable_minified') ? '.min' : '';
        $templateMgr->addJavaScript(
            'chartJS',
            $request->getBaseUrl() . '/lib/pkp/js/lib/Chart' . $min . '.js',
            [
                'contexts' => $this->getSubmissionViewContext(),
            ]
        );

        // Add locale and configuration data
        $chartType = $this->getOption('displayStats');
        $script_data = 'var pkpUsageStats = pkpUsageStats || {};';
        $script_data .= 'pkpUsageStats.locale = pkpUsageStats.locale || {};';
        $script_data .= 'pkpUsageStats.locale.months = ' . json_encode(explode(' ', __('plugins.themes.default.displayStats.monthInitials'))) . ';';
        $script_data .= 'pkpUsageStats.config = pkpUsageStats.config || {};';
        $script_data .= 'pkpUsageStats.config.chartType = ' . json_encode($chartType) . ';';

        $templateMgr->addJavaScript(
            'pkpUsageStatsConfig',
            $script_data,
            [
                'inline' => true,
                'contexts' => $this->getSubmissionViewContext(),
            ]
        );

        // Register the JS which initializes the chart
        $templateMgr->addJavaScript(
            'usageStatsFrontend',
            $request->getBaseUrl() . '/lib/pkp/js/usage-stats-chart.js',
            [
                'contexts' => $this->getSubmissionViewContext(),
            ]
        );
    }

    /**
     * Retrieve download metrics for the given submission
     */
    protected function getAllDownloadsStats(int $submissionId): array
    {
        $cache = CacheManager::getManager()->getCache('downloadStats', $submissionId, [$this, 'downloadStatsCacheMiss']);
        if (time() - $cache->getCacheTime() > 60 * 60 * 24) {
            // Cache is older than one day, erase it.
            $cache->flush();
        }
        $statsByMonth = [];
        $totalDownloads = 0;
        $data = $cache->get($submissionId);
        foreach ($data as $monthlyDownloadStats) {
            [$year, $month] = explode('-', $monthlyDownloadStats['date']);
            $month = ltrim($month, '0');
            $statsByMonth[$year][$month] = $monthlyDownloadStats['value'];
            $totalDownloads += $monthlyDownloadStats['value'];
        }
        return [
            'data' => $statsByMonth,
            'label' => __('common.allDownloads'),
            'color' => $this->getUsageStatsDisplayColor(REALLY_BIG_NUMBER),
            'total' => $totalDownloads
        ];
    }

    /**
     * Callback to fill cache with submission's download usage statistics data.
     */
    public function downloadStatsCacheMiss(FileCache $cache, int $submissionId): array
    {
        $request = Application::get()->getRequest();
        $submission = Repo::submission()->get($submissionId);
        $originalPublication = $submission->getOriginalPublication();
        $earliestDatePublished = $originalPublication->getData('datePublished');
        $params = [
            'contextIds' => [$request->getContext()->getId()],
            'submissionIds' => [$submissionId],
            'assocTypes' => [Application::ASSOC_TYPE_SUBMISSION_FILE],
            'timelineInterval' => StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            'dateStart' => $earliestDatePublished
        ];
        $statsService = Services::get('publicationStats');
        $data = $statsService->getTimeline($params['timelineInterval'], $params);
        $cache->setEntireCache([$submissionId => $data]);
        return $data;
    }

    /**
     * Return a color RGB code to be used in the usage statistics diplay graph.
     */
    protected function getUsageStatsDisplayColor(int $num): string
    {
        $hash = md5('color' . $num * 2);
        return hexdec(substr($hash, 0, 2)) . ',' . hexdec(substr($hash, 2, 2)) . ',' . hexdec(substr($hash, 4, 2));
    }

    /**
     * Get the context for inclusion of usage stats display related JavaScripts in the submission view page
     */
    protected function getSubmissionViewContext(): string
    {
        if (Application::get()->getName() == 'ojs2') {
            return 'frontend-article-view';
        } elseif (Application::get()->getName() == 'omp') {
            return 'frontend-catalog-book';
        } elseif (Application::get()->getName() == 'ops') {
            return 'frontend-preprint-view';
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\ThemePlugin', '\ThemePlugin');
}

<?php

/**
 * @file classes/template/PKPTemplateResource.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemplateResource
 *
 * @ingroup template
 *
 * @brief Representation for a PKP template resource (template directory).
 */

namespace PKP\template;

use APP\core\Application;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\View;
use PKP\plugins\Hook;
use Throwable;

class PKPTemplateResource extends \Smarty_Resource_Custom
{
    /** @var array Template path or list of paths */
    protected $_templateDir;

    /** @var array<string, string|false> Resolution cache */
    protected static array $cache = [];

    /**
     * Constructor
     *
     * @param string|array $templateDir Template directory
     */
    public function __construct($templateDir)
    {
        if (is_string($templateDir)) {
            $this->_templateDir = [$templateDir];
        } else {
            $this->_templateDir = $templateDir;
        }
    }

    /**
     * Resource function to get a template.
     *
     * @param string $name Template name
     * @param string $source Reference to variable receiving fetched Smarty source
     * @param int|bool $mtime Modification time
     *
     * @return bool
     */
    public function fetch($name, &$source, &$mtime)
    {
        $filePath = $this->_getFilename($name);

        if (!$filePath || !file_exists($filePath)) {
            return false;
        }

        $mtime = filemtime($filePath);
        if ($mtime === false) {
            return false;
        }

        // Blade template - render via View::file()
        if (str_ends_with($filePath, '.blade')) {
            try {
                $templateManager = TemplateManager::getManager(Application::get()->getRequest());
                $source = View::file($filePath, $templateManager->getTemplateVars())->render();
                return true;
            } catch (Throwable $e) {
                error_log("Error rendering Blade template '{$filePath}': " . $e->getMessage());
                throw $e;
            }
        }

        // Smarty template - return file contents
        $source = file_get_contents($filePath);
        return ($source !== false);
    }

    /**
     * Get the timestamp for the specified template.
     *
     * @param string $name Template name
     *
     * @return int|boolean
     */
    protected function fetchTimestamp($name)
    {
        $filePath = $this->_getFilename($name);

        if (!$filePath) {
            return false;
        }

        return filemtime($filePath);
    }

    /**
     * Get the complete template path and filename.
     *
     * @return string|null
     *
     * @hook TemplateResource::getFilename [[&$filePath, $template]]
     */
    protected function _getFilename($template)
    {
        if (array_key_exists($template, self::$cache)) {
            return self::$cache[$template] ?: null;
        }

        $baseName = self::normalizeTemplateName($template);
        $filePath = self::findInPaths($baseName, $this->_templateDir);

        Hook::call('TemplateResource::getFilename', [&$filePath, $template]);

        self::$cache[$template] = $filePath ?: false;
        return $filePath;
    }

    /**
     * Find a template in the given paths, checking .blade first then .tpl.
     *
     * @param string $baseName Normalized template name without extension
     * @param array $paths Array of directory paths to search
     *
     * @return string|null The file path if found, null otherwise
     */
    public static function findInPaths(string $baseName, array $paths): ?string
    {
        foreach ($paths as $path) {
            $bladePath = "{$path}/{$baseName}.blade";
            if (file_exists($bladePath)) {
                return $bladePath;
            }

            $smartyPath = "{$path}/{$baseName}.tpl";
            if (file_exists($smartyPath)) {
                return $smartyPath;
            }
        }

        return null;
    }

    /**
     * Normalize template name to base path without extension.
     *
     * @param string $name Template name in various formats
     *
     * @return string Normalized base name (e.g., 'frontend/pages/article')
     */
    public static function normalizeTemplateName(string $name): string
    {
        $name = preg_replace('/\.(tpl|blade)$/', '', $name);

        if (!str_contains($name, '/') && str_contains($name, '.')) {
            $name = str_replace('.', '/', $name);
        }

        $name = preg_replace('#^templates/#', '', $name);

        return $name;
    }

    /**
     * Static method to get a template file path (for use by other classes).
     *
     * @param string $template Template name
     *
     * @return string|null The file path, or null if not found
     */
    public static function getFilePath(string $template): ?string
    {
        if (array_key_exists($template, self::$cache)) {
            return self::$cache[$template] ?: null;
        }

        $templateManager = TemplateManager::getManager(Application::get()->getRequest());
        $resource = $templateManager->registered_resources['app'] ?? null;

        if ($resource instanceof self) {
            return $resource->_getFilename($template);
        }

        $instance = new self(['templates', 'lib/pkp/templates']);
        return $instance->_getFilename($template);
    }

    /**
     * Check if a file path is a Blade template.
     *
     * @param string $filePath The file path to check
     *
     * @return bool
     */
    public static function isBladeTemplate(string $filePath): bool
    {
        return str_ends_with($filePath, '.blade');
    }

    /**
     * Clear the resolution cache.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}

<?php

namespace PKP\core\blade;

use PKP\plugins\Hook;

class FileViewFinder extends \Illuminate\View\FileViewFinder
{
    /**
     * Find the given view in the list of paths.
     *
     * Fires a hook to allow plugins to override the view path
     * before falling back to standard Laravel resolution.
     *
     * @param string $name View name (e.g., 'frontend.pages.article')
     * @return string Resolved file path
     *
     * @throws \InvalidArgumentException
     */
    public function find($name)
    {
        // Initialize override path
        $overridePath = null;

        // Fire hook: allow plugins to provide override
        // Hook signature: BladeTemplate::getFilename(&$overridePath, $templateName)
        // Hook::call('BladeTemplate::getFilename', [&$overridePath, $name]);
        Hook::call('TemplateResource::getFilename', [&$overridePath, $name]);

        // If plugin provided an override, handle it
        if ($overridePath !== null) {
            // Check if this is a Smarty resource notation (e.g., "plugins-...:template.tpl")
            // Smarty resources don't exist as files, so skip file existence check
            $isSmartyResource = str_contains($overridePath, ':') && str_ends_with($overridePath, '.tpl');

            if ($isSmartyResource) {
                // NON-BREAKING BACKWARD COMPATIBILITY
                // Register .tpl extension to use Smarty engine for rendering
                // This allows plugins to override Blade templates with Smarty templates
                // Priority order: Plugin Blade > Plugin Smarty > Core Blade
                \Illuminate\Support\Facades\View::addExtension('tpl', 'smarty');

                // Return Smarty resource directly - it will be handled by SmartyTemplatingEngine
                $this->views[$name] = $overridePath;
                return $overridePath;
            }

            // For regular file paths (Blade), verify the file exists
            if ($this->files->exists($overridePath)) {
                $this->views[$name] = $overridePath;
                return $overridePath;
            }
        }

        // Fall back to standard Laravel resolution
        // This checks registered paths in order: theme → app → pkp
        return parent::find($name);
    }
}
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
 * @brief Smarty resource handler for core: and app: template prefixes.
 *
 * IMPORTANT: This class is required for Smarty COMPILATION phase.
 * When Smarty compiles {include file="core:template.tpl"}, it needs to read
 * the source file - this class provides that capability.
 *
 * Runtime template resolution (including plugin overrides) happens separately
 * in SmartyTemplate via the View::resolveName hook.
 *
 * Do not remove this class - without it, templates using core: or app: prefixes
 * will fail with "Unknown resource type" errors during compilation.
 */

namespace PKP\template;

class PKPTemplateResource extends \Smarty_Resource_Custom
{
    protected array $templateDirs;

    public function __construct(string|array $templateDir)
    {
        $this->templateDirs = (array) $templateDir;
    }

    public function fetch($name, &$source, &$mtime): bool
    {
        $filename = $this->findTemplate($name);
        if (!$filename) {
            return false;
        }
        $mtime = filemtime($filename);
        $source = file_get_contents($filename);
        return $source !== false;
    }

    protected function fetchTimestamp($name): int|false
    {
        $filename = $this->findTemplate($name);
        return $filename ? filemtime($filename) : false;
    }

    protected function findTemplate(string $template): ?string
    {
        foreach ($this->templateDirs as $dir) {
            $path = "{$dir}/{$template}";
            if (file_exists($path)) {
                return $path;
            }
        }
        return null;
    }
}

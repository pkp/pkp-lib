<?php

/**
 * @file classes/plugins/PKPViewableFilePlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPViewableFilePlugin
 *
 * @ingroup plugins
 *
 * @brief Abstract class for article galley plugins
 */

namespace PKP\plugins;

abstract class PKPViewableFilePlugin extends GenericPlugin
{
    /**
     * Get the filename of the template. (Default behavior may
     * be overridden through some combination of this function and the
     * displayArticleGalley function.)
     * Returning null from this function results in an empty display.
     *
     * @return string
     */
    public function getTemplateFilename()
    {
        return 'display.tpl';
    }
}

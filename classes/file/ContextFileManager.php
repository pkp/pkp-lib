<?php

/**
 * @file classes/file/ContextFileManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextFileManager
 *
 * @ingroup file
 *
 * @brief Class defining operations for private context file management.
 */

namespace PKP\file;

use APP\core\Application;

class ContextFileManager extends PrivateFileManager
{
    /**
     * Constructor.
     * Create a manager for handling context file uploads.
     */
    public function __construct(public int $contextId)
    {
        parent::__construct();
    }

    /**
     * Get the base path for file storage
     *
     * @return string
     */
    public function getBasePath()
    {
        $dirNames = Application::getFileDirectories();
        return parent::getBasePath() . $dirNames['context'] . $this->contextId . '/';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\ContextFileManager', '\ContextFileManager');
}

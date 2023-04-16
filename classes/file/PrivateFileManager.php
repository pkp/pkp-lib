<?php

/**
 * @file classes/file/PrivateFileManager.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PrivateFileManager
 *
 * @ingroup file
 *
 * @brief Class defining operations for private file management.
 */

namespace PKP\file;

use PKP\config\Config;

class PrivateFileManager extends FileManager
{
    /** @var string $filesDir */
    public $filesDir;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->filesDir = $this->getBasePath();
    }

    /**
     * Get the base path for file storage.
     *
     * @return string
     */
    public function getBasePath()
    {
        return Config::getVar('files', 'files_dir');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\PrivateFileManager', '\PrivateFileManager');
}

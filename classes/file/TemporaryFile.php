<?php

/**
 * @file classes/file/TemporaryFile.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemporaryFile
 *
 * @ingroup file
 *
 * @see TemporaryFileDAO
 *
 * @brief Temporary file class.
 */

namespace PKP\file;

class TemporaryFile extends PKPFile
{
    /**
     * Return absolute path to the file on the host filesystem.
     *
     * @return string
     */
    public function getFilePath()
    {
        $temporaryFileManager = new TemporaryFileManager();
        return $temporaryFileManager->getBasePath() . $this->getServerFileName();
    }

    //
    // Get/set methods
    //

    /**
     * Get ID of associated user.
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * Set ID of associated user.
     *
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->setData('userId', $userId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\file\TemporaryFile', '\TemporaryFile');
}

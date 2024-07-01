<?php

/**
 * @file classes/core/PKPEncryptionServiceProvider.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEncryptionServiceProvider
 *
 * @brief Register encryption service and validate app key.
 */

namespace PKP\core;

use APP\core\Application;
use Illuminate\Encryption\EncryptionServiceProvider as IlluminateEncryptionServiceProvider;

class PKPEncryptionServiceProvider extends IlluminateEncryptionServiceProvider
{
    /**
     * @copydoc Illuminate\Encryption\EncryptionServiceProvider::registerEncrypter()
     */
    protected function registerEncrypter()
    {
        // if application not installed, we do not check for app key as
        // may be it's need to be generated at application installation process
        if (!Application::isInstalled()) {
            return;
        }

        parent::registerEncrypter();
    }
}

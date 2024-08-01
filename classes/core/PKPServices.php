<?php

/**
 * @file classes/core/PKPServices.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPServices
 * 
 * @brief Pimple Dependency Injection Container.
 * 
 * @deprecated 3.5.0 Consider using {@see app()->get('SERVICE_NAME')}
 * @see app()->get('SERVICE_NAME')
 * 
 */

namespace PKP\core;
abstract class PKPServices
{
    /**
     * A static method to get a service
     *
     * @param string $service The name of the service
     */
    public static function get(string $service): mixed
    {
        return app()->get($service);
    }

}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\core\PKPServices', '\PKPServices');
}

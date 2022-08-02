<?php

/**
 * @file classes/db/DAORegistry.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAORegistry
 * @ingroup db
 *
 * @see DAO
 *
 * @brief Maintains a static list of DAO objects so each DAO is instantiated only once.
 */

namespace PKP\db;

use APP\core\Application;

use PKP\core\Registry;

class DAORegistry
{
    /**
     * Get the current list of registered DAOs.
     * This returns a reference to the static hash used to
     * store all DAOs currently instantiated by the system.
     *
     * @return array
     */
    public static function &getDAOs()
    {
        $daos = & Registry::get('daos', true, []);
        return $daos;
    }

    /**
     * Register a new DAO with the system.
     *
     * @param string $name The name of the DAO to register
     * @param object $dao A reference to the DAO to be registered
     *
     * @return object A reference to previously-registered DAO of the same
     *    name, if one was already registered; null otherwise
     */
    public static function registerDAO($name, $dao)
    {
        $daos = & DAORegistry::getDAOs();

        $returner = null;

        if (isset($daos[$name])) {
            $returner = $daos[$name];
        }

        $daos[$name] = $dao;
        return $returner;
    }

    /**
     * Retrieve a reference to the specified DAO.
     *
     * @param string $name the class name of the requested DAO
     *
     * @return DAO
     */
    public static function &getDAO($name)
    {
        $daos = & DAORegistry::getDAOs();
        if (!isset($daos[$name])) {
            // Import the required DAO class.
            $application = Application::get();
            $className = $application->getQualifiedDAOName($name);
            if (!$className) {
                throw new \Exception('Unrecognized DAO ' . $name . '!');
            }

            // Only instantiate each class of DAO a single time
            $daos[$name] = new $className();
        }

        return $daos[$name];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\db\DAORegistry', '\DAORegistry');
}

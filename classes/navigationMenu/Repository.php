<?php

/**
 * @file classes/navigationMenu/Repository.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and manage navigation menus.
 */

namespace PKP\navigationMenu;

class Repository
{
    /** @var NavigationMenuDAO $dao */
    public NavigationMenuDAO $dao;

    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    public function __construct(NavigationMenuDAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc DAO::newDataObject() */
    public function newDataObject(array $params = []): NavigationMenu
    {
        $object = $this->dao->newDataObject();
        if (!empty($params)) {
            $object->setAllData($params);
        }
        return $object;
    }

    /**
     * Get an instance of the map class for mapping
     * navigation menus to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /** @copydoc DAO::get() */
    public function get(int $id, ?int $contextId = null): ?NavigationMenu
    {
        return $this->dao->getById($id, $contextId);
    }
}

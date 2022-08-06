<?php

/**
 * @file tests/classes/core/TestEntityDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TestEntityDAO
 * @ingroup tests_classes_core
 *
 * @see EntityDAO
 *
 * @brief Tests for the EntityDAO class.
 */

namespace PKP\tests\classes\core;

use PKP\core\DataObject;
use PKP\core\EntityDAO;

class TestEntityDAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = 'test_schema';

    /** @copydoc EntityDAO::$table */
    public $table = 'test_entity';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'test_entity_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'test_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'test_id',
        'integerColumn' => 'integer_column',
        'nullableIntegerColumn' => 'nullable_integer_column',
    ];

    /**
     * @copydoc EntityDAO::_insert()
     */
    public function insert(DataObject $testEntity): int
    {
        return parent::_insert($testEntity);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(DataObject $testEntity)
    {
        parent::_update($testEntity);
    }

    /**
     * @copydoc EntityDAO::_delete()
     */
    public function delete(DataObject $testEntity)
    {
        parent::_delete($testEntity);
    }

    public function newDataObject()
    {
        return new DataObject();
    }
}

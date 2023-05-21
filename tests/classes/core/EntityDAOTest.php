<?php

/**
 * @file tests/classes/core/EntityDAOTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EntityDAOTest
 *
 * @ingroup tests_classes_core
 *
 * @see EntityDAO
 *
 * @brief Tests for the EntityDAO class.
 */

namespace PKP\tests\classes\core;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\core\DataObject;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class EntityDAOTest extends PKPTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create the database tables
        Schema::create('test_entity', function (Blueprint $table) {
            $table->bigInteger('test_id')->autoIncrement();
            $table->bigInteger('parent_id');
            $table->bigInteger('integer_column')->nullable(false);
            $table->bigInteger('nullable_integer_column')->nullable(true);
        });
        Schema::create('test_entity_settings', function (Blueprint $table) {
            $table->bigInteger('test_id');
            $table->foreign('test_id')->references('test_id')->on('test_entity')->onDelete('cascade');
            ;
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6)->nullable();
            $table->index(['test_id'], 'test_entity_settings_test_id');
            $table->unique(['test_id', 'locale', 'setting_name'], 'test_entity_settings_unique');
        });

        // Inject a test schema
        Hook::add('Schema::get::test_schema', function ($hookName, $args) {
            $schema = & $args[0];
            $schema = json_decode('{
                "title": "Test Schema",
                "description": "A schema for testing purposes",
                "properties": {
                    "id": {
                        "type": "integer",
                        "readOnly": true
                    },
                    "parentId": {
                        "type": "integer"
                    },
                    "integerColumn": {
                        "type": "integer"
                    },
                    "nullableIntegerColumn": {
                        "type": "integer",
                        "validation": ["nullable"]
                    },
                    "nonlocalizedSettingString": {
                        "type": "string"
                    }
                }
            }');
            return true;
        });
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        Schema::dropIfExists('test_entity_settings');
        Schema::dropIfExists('test_entity');
    }

    /**
     * @covers \PKP\core\EntityDAO::_insert
     */
    public function testCRUD()
    {
        $testEntityDao = app(TestEntityDAO::class);

        // Create a data object for storage
        $testEntity = new DataObject();
        $testEntity->setData('parentId', 2);
        $testEntity->setData('integerColumn', 3);
        $testEntity->setData('nullableIntegerColumn', 4);
        $testEntity->setData('nonlocalizedSettingString', 'test string');

        // Store the data object to the DB
        $testEntityDao->insert($testEntity);
        $insertedId = $testEntity->getId();
        self::assertNotNull($insertedId);

        // Retrieve the data object from the DB
        $fetchedEntity = $testEntityDao->get($insertedId);

        // Ensure that the stored data matches the retrieved data
        self::assertEquals($testEntity->_data, $fetchedEntity->_data);
        unset($fetchedEntity);

        // Update some values
        $testEntity->setData('integerColumn', 5);
        $testEntity->setData('nonlocalizedSettingString', 'another test string');
        $testEntity->setData('nullableIntegerColumn', null);
        $testEntityDao->update($testEntity);

        $fetchedEntity = $testEntityDao->get($insertedId);
        self::assertEquals([
            'id' => $insertedId,
            'parentId' => 2,
            'integerColumn' => 5,
            'nonlocalizedSettingString' => 'another test string',
            'nullableIntegerColumn' => null,
        ], $fetchedEntity->_data);

        // Delete the entity and make sure it's gone.
        $testEntityDao->delete($testEntity);
        $fetchedEntity = $testEntityDao->get($insertedId);
        self::assertNull($fetchedEntity);
    }

    public function testNullablePrimaryColumn()
    {
        $testEntityDao = app(TestEntityDAO::class);

        // Create a data object for storage
        $testEntity = new DataObject();
        $testEntity->setData('parentId', 2);
        $testEntity->setData('integerColumn', 3);
        $testEntity->setData('nullableIntegerColumn', null);

        // Store the data object to the DB
        $testEntityDao->insert($testEntity);
        $insertedId = $testEntity->getId();

        // Retrieve the data object from the DB
        $fetchedEntity = $testEntityDao->get($insertedId);

        // Ensure that the stored data matches the retrieved data
        self::assertTrue(!isset($fetchedEntity->_data['nullableIntegerColumn']), 'Nullable columns are stored properly');

        // Delete the entity and make sure it's gone.
        $testEntityDao->delete($testEntity);
    }

    public function testNotNullablePrimaryColumn()
    {
        $testEntityDao = app(TestEntityDAO::class);

        // Create a data object for storage
        $testEntity = new DataObject();
        $testEntity->setData('parentId', 2);
        $testEntity->setData('integerColumn', null); // Invalid

        $this->expectException(\Exception::class);

        // Store the data object to the DB
        $testEntityDao->insert($testEntity);
    }
}

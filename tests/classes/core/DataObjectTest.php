<?php

/**
 * @file tests/classes/core/DataObjectTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectTest
 * @ingroup tests_classes_core
 *
 * @see DataObject
 *
 * @brief Tests for the DataObject class.
 */

import('lib.pkp.tests.PKPTestCase');

use PKP\core\DataObject;

class DataObjectTest extends PKPTestCase
{
    /** @var DataObject */
    protected $dataObject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataObject = new DataObject();
    }

    /**
     * @covers DataObject::setData
     * @covers DataObject::getData
     * @covers DataObject::getAllData
     */
    public function testSetGetData()
    {
        // Set data with and without locale
        $this->dataObject->setData('testVar1', 'testVal1');
        $this->dataObject->setData('testVar2', 'testVal2_US', 'en_US');
        $this->dataObject->setData('testVar2', 'testVal2_DE', 'de_DE');
        $expectedResult = [
            'testVar1' => 'testVal1',
            'testVar2' => [
                'en_US' => 'testVal2_US',
                'de_DE' => 'testVal2_DE'
            ]
        ];
        self::assertEquals($expectedResult, $this->dataObject->getAllData());
        self::assertEquals('testVal1', $this->dataObject->getData('testVar1'));
        self::assertNull($this->dataObject->getData('testVar1', 'en_US'));
        self::assertEquals('testVal2_US', $this->dataObject->getData('testVar2', 'en_US'));

        // Unset a few values
        $this->dataObject->unsetData('testVar1');
        $this->dataObject->unsetData('testVar2', 'en_US');
        $expectedResult = [
            'testVar2' => [
                'de_DE' => 'testVal2_DE'
            ]
        ];
        self::assertEquals($expectedResult, $this->dataObject->getAllData());

        // Make sure that un-setting a non-existent value doesn't hurt
        $this->dataObject->unsetData('testVar1');
        $this->dataObject->unsetData('testVar2', 'en_US');
        self::assertEquals($expectedResult, $this->dataObject->getAllData());

        // Make sure that getting a non-existent value doesn't hurt
        self::assertNull($this->dataObject->getData('testVar1'));
        self::assertNull($this->dataObject->getData('testVar1', 'en_US'));
        self::assertNull($this->dataObject->getData('testVar2', 'en_US'));

        // Unsetting the whole translation set will kill the variable
        $this->dataObject->unsetData('testVar2');
        self::assertEquals([], $this->dataObject->getAllData());

        // Test by-ref behaviour
        $testVal1 = 'testVal1';
        $testVal2 = 'testVal2';
        $this->dataObject->setData('testVar1', $testVal1);
        $this->dataObject->setData('testVar2', $testVal2, 'en_US');
        $testVal1 = $testVal2 = 'something else';
        $expectedResult = [
            'testVar1' => 'testVal1',
            'testVar2' => [
                'en_US' => 'testVal2'
            ]
        ];
        $result = & $this->dataObject->getAllData();
        self::assertEquals($expectedResult, $result);

        // Should be returned by-ref:
        $testVal1 = & $this->dataObject->getData('testVar1');
        $testVal2 = & $this->dataObject->getData('testVar2', 'en_US');
        $testVal1 = $testVal2 = 'something else';
        $expectedResult = [
            'testVar1' => 'something else',
            'testVar2' => [
                'en_US' => 'something else'
            ]
        ];
        $result = & $this->dataObject->getAllData();
        self::assertEquals($expectedResult, $result);
    }

    /**
     * @covers DataObject::setAllData
     */
    public function testSetAllData()
    {
        $expectedResult = ['someKey' => 'someVal'];
        $this->dataObject->setAllData($expectedResult);
        $result = & $this->dataObject->getAllData();
        self::assertEquals($expectedResult, $result);

        // Test assignment is not done by reference
        $expectedResult = ['someOtherKey' => 'someOtherVal'];
        self::assertNotEquals($expectedResult, $result);
    }

    /**
     * @covers DataObject::hasData
     */
    public function testHasData()
    {
        $testData = [
            'testVar1' => 'testVal1',
            'testVar2' => [
                'en_US' => 'testVal2'
            ]
        ];
        $this->dataObject->setAllData($testData);
        self::assertTrue($this->dataObject->hasData('testVar1'));
        self::assertTrue($this->dataObject->hasData('testVar2'));
        self::assertTrue($this->dataObject->hasData('testVar2', 'en_US'));
        self::assertFalse($this->dataObject->hasData('testVar1', 'en_US'));
        self::assertFalse($this->dataObject->hasData('testVar2', 'de_DE'));
        self::assertFalse($this->dataObject->hasData('testVar3'));
        self::assertFalse($this->dataObject->hasData('testVar3', 'en_US'));
    }
}

<?php

/**
 * @file tests/classes/validation/ValidatorControlledVocabTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorControlledVocabTest
 * @ingroup tests_classes_validation
 *
 * @see ValidatorControlledVocab
 *
 * @brief Test class for ValidatorControlledVocab.
 */

import('lib.pkp.tests.PKPTestCase');

use PKP\controlledVocab\ControlledVocab;
use PKP\validation\ValidatorControlledVocab;

class ValidatorControlledVocabTest extends PKPTestCase
{
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs()
    {
        return ['ControlledVocabDAO'];
    }

    /**
     * @covers ValidatorControlledVocab
     */
    public function testValidatorControlledVocab()
    {
        // Mock a ControlledVocab object
        $mockControlledVocab = $this->getMockBuilder(ControlledVocab::class)
            ->setMethods(['enumerate'])
            ->getMock();
        $mockControlledVocab->setId(1);
        $mockControlledVocab->setAssocType(ASSOC_TYPE_CITATION);
        $mockControlledVocab->setAssocId(333);
        $mockControlledVocab->setSymbolic('testVocab');

        // Set up the mock enumerate() method
        $mockControlledVocab->expects($this->any())
            ->method('enumerate')
            ->will($this->returnValue([1 => 'vocab1', 2 => 'vocab2']));

        // Mock the ControlledVocabDAO
        $mockControlledVocabDao = $this->getMockBuilder(ControlledVocabDAO::class)
            ->setMethods(['getBySymbolic'])
            ->getMock();

        // Set up the mock getBySymbolic() method
        $mockControlledVocabDao->expects($this->any())
            ->method('getBySymbolic')
            ->with('testVocab', ASSOC_TYPE_CITATION, 333)
            ->will($this->returnValue($mockControlledVocab));

        DAORegistry::registerDAO('ControlledVocabDAO', $mockControlledVocabDao);

        $validator = new ValidatorControlledVocab('testVocab', ASSOC_TYPE_CITATION, 333);
        self::assertTrue($validator->isValid('1'));
        self::assertTrue($validator->isValid('2'));
        self::assertFalse($validator->isValid('3'));
    }
}

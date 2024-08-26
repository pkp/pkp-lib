<?php

/**
 * @file tests/classes/validation/ValidatorControlledVocabTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorControlledVocabTest
 *
 * @brief Test class for ValidatorControlledVocab.
 */

namespace PKP\tests\classes\validation;

use APP\core\Application;
use APP\facades\Repo;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\db\DAORegistry;
use PKP\tests\PKPTestCase;
use PKP\validation\ValidatorControlledVocab;

#[CoversClass(ValidatorControlledVocab::class)]
class ValidatorControlledVocabTest extends PKPTestCase
{
    public function testValidatorControlledVocab()
    {
        $testControlledVocab = Repo::controlledVocab()->build(
            'testVocab',
            Application::ASSOC_TYPE_CITATION,
            333
        );

        /** @var ControlledVocabEntryDAO */
        $controlledVocabEntryDao = DAORegistry::getDAO('ControlledVocabEntryDAO');

        $testControlledVocabEntry1 = $controlledVocabEntryDao->newDataObject();
        $testControlledVocabEntry1->setName('testEntry', 'en');
        $testControlledVocabEntry1->setControlledVocabId($testControlledVocab->id);
        $controlledVocabEntryId1 = $controlledVocabEntryDao->insertObject($testControlledVocabEntry1);

        $testControlledVocabEntry2 = $controlledVocabEntryDao->newDataObject();
        $testControlledVocabEntry2->setName('testEntry', 'en');
        $testControlledVocabEntry2->setControlledVocabId($testControlledVocab->id);
        $controlledVocabEntryId2 = $controlledVocabEntryDao->insertObject($testControlledVocabEntry2);

        $validator = new ValidatorControlledVocab('testVocab', Application::ASSOC_TYPE_CITATION, 333);
        self::assertTrue($validator->isValid($controlledVocabEntryId1));
        self::assertTrue($validator->isValid($controlledVocabEntryId2));
        self::assertFalse($validator->isValid(3));

        // Delete the test entried
        $controlledVocabEntryDao->deleteObjectById($controlledVocabEntryId1);
        $controlledVocabEntryDao->deleteObjectById($controlledVocabEntryId2);
        $testControlledVocab->delete();
    }
}

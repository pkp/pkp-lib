<?php

/**
 * @file tests/classes/form/validation/FormValidatorControlledVocabTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorControlledVocabTest
 *
 * @brief Test class for FormValidatorControlledVocab.
 */

namespace PKP\tests\classes\form\validation;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\controlledVocab\ControlledVocabEntryDAO;
use APP\facades\Repo;
use PKP\form\validation\FormValidatorControlledVocab;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormValidatorControlledVocab::class)]
class FormValidatorControlledVocabTest extends PKPTestCase
{
    public function testIsValid()
    {
        // Test form
        $form = new Form('some template');

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

        // Instantiate validator
        $validator = new FormValidatorControlledVocab(
            $form,
            'testData',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key',
            'testVocab',
            Application::ASSOC_TYPE_CITATION,
            333
        );

        $form->setData('testData', $controlledVocabEntryId1);
        self::assertTrue($validator->isValid());

        $form->setData('testData', $controlledVocabEntryId2);
        self::assertTrue($validator->isValid());

        $form->setData('testData', 3);
        self::assertFalse($validator->isValid());

        // Delete the test entried
        $controlledVocabEntryDao->deleteObjectById($controlledVocabEntryId1);
        $controlledVocabEntryDao->deleteObjectById($controlledVocabEntryId2);
        $testControlledVocab->delete();
    }
}

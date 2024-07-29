<?php

/**
 * @file tests/classes/form/validation/FormValidatorControlledVocabTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorControlledVocabTest
 *
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorControlledVocab
 *
 * @brief Test class for FormValidatorControlledVocab.
 */

namespace PKP\tests\classes\form\validation;

use APP\core\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PKP\form\validation\FormValidatorControlledVocab;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabDAO;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\tests\PKPTestCase;

#[CoversClass(FormValidatorControlledVocab::class)]
class FormValidatorControlledVocabTest extends PKPTestCase
{
    /**
     * @see PKPTestCase::getMockedDAOs()
     */
    protected function getMockedDAOs(): array
    {
        return [...parent::getMockedDAOs(), 'ControlledVocabDAO'];
    }

    public function testIsValid()
    {
        // Test form
        $form = new Form('some template');

        // Mock a ControlledVocab object
        /** @var ControlledVocab|MockObject */
        $mockControlledVocab = $this->getMockBuilder(ControlledVocab::class)
            ->onlyMethods(['enumerate'])
            ->getMock();
        $mockControlledVocab->setId(1);
        $mockControlledVocab->setAssocType(Application::ASSOC_TYPE_CITATION);
        $mockControlledVocab->setAssocId(333);
        $mockControlledVocab->setSymbolic('testVocab');

        // Set up the mock enumerate() method
        $mockControlledVocab->expects($this->any())
            ->method('enumerate')
            ->willReturn([1 => 'vocab1', 2 => 'vocab2']);

        // Mock the ControlledVocabDAO
        $mockControlledVocabDao = $this->getMockBuilder(ControlledVocabDAO::class)
            ->onlyMethods(['getBySymbolic'])
            ->getMock();

        // Set up the mock getBySymbolic() method
        $mockControlledVocabDao->expects($this->any())
            ->method('getBySymbolic')
            ->with('testVocab', Application::ASSOC_TYPE_CITATION, 333)
            ->willReturn($mockControlledVocab);

        DAORegistry::registerDAO('ControlledVocabDAO', $mockControlledVocabDao);

        // Instantiate validator
        $validator = new \PKP\form\validation\FormValidatorControlledVocab($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', 'testVocab', Application::ASSOC_TYPE_CITATION, 333);

        $form->setData('testData', '1');
        self::assertTrue($validator->isValid());

        $form->setData('testData', '2');
        self::assertTrue($validator->isValid());

        $form->setData('testData', '3');
        self::assertFalse($validator->isValid());
    }
}

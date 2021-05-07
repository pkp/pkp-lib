<?php

/**
 * @file tests/classes/form/validation/FormValidatorTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorTest
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidator
 *
 * @brief Test class for FormValidator.
 */


require_mock_env('env1');

import('lib.pkp.tests.PKPTestCase');

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\validation\ValidatorUrl;

class FormValidatorTest extends PKPTestCase
{
    private $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = new Form('some template');
    }

    /**
     * @covers FormValidator::__construct
     * @covers FormValidator::getField
     * @covers FormValidator::getForm
     * @covers FormValidator::getValidator
     * @covers FormValidator::getType
     */
    public function testConstructor()
    {
        // Instantiate a test validator
        $validator = new ValidatorUrl();

        // Test CSS validation flags
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertEquals(['testData' => []], $this->form->cssValidation);
        self::assertSame(FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, $formValidator->getType());

        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $validator);
        self::assertEquals(['testData' => ['required']], $this->form->cssValidation);
        self::assertSame(FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, $formValidator->getType());

        // Test getters
        self::assertSame('testData', $formValidator->getField());
        self::assertSame($this->form, $formValidator->getForm());
        self::assertSame($validator, $formValidator->getValidator());
    }

    /**
     * @covers FormValidator::getMessage
     */
    public function testGetMessage()
    {
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('##some.message.key##', $formValidator->getMessage());
    }

    /**
     * @covers FormValidator::getFieldValue
     */
    public function testGetFieldValue()
    {
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $this->form->setData('testData', null);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $this->form->setData('testData', 0);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('0', $formValidator->getFieldValue());

        $this->form->setData('testData', '0');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('0', $formValidator->getFieldValue());

        $this->form->setData('testData', ' some text ');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('some text', $formValidator->getFieldValue());

        $this->form->setData('testData', [' some text ']);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame([' some text '], $formValidator->getFieldValue());
    }

    /**
     * @covers FormValidator::isEmptyAndOptional
     */
    public function testIsEmptyAndOptional()
    {
        // When the validation type is "required" then the method should return
        // false even if the given data field is empty.
        $this->form->setData('testData', '');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isEmptyAndOptional());

        // If the validation type is "optional" but the given data field is not empty
        // then the method should also return false.
        $this->form->setData('testData', 'something');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isEmptyAndOptional());

        $this->form->setData('testData', ['something']);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isEmptyAndOptional());

        // When the validation type is "optional" and the value empty then return true
        $this->form->setData('testData', '');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertTrue($formValidator->isEmptyAndOptional());

        // Test border conditions
        $this->form->setData('testData', null);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertTrue($formValidator->isEmptyAndOptional());

        $this->form->setData('testData', 0);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isEmptyAndOptional());

        $this->form->setData('testData', '0');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isEmptyAndOptional());

        $this->form->setData('testData', []);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertTrue($formValidator->isEmptyAndOptional());
    }

    /**
     * @covers FormValidator::isValid
     */
    public function testIsValid()
    {
        // We don't need to test the case where a validator is set, this
        // is sufficiently tested by the other FormValidator* tests.

        // Test default validation (without internal validator set and optional values)
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key');
        self::assertTrue($formValidator->isValid());

        // Test default validation (without internal validator set and required values)
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isValid());

        $this->form->setData('testData', []);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertFalse($formValidator->isValid());

        $this->form->setData('testData', 'some value');
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertTrue($formValidator->isValid());

        $this->form->setData('testData', ['some value']);
        $formValidator = new \PKP\form\validation\FormValidator($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertTrue($formValidator->isValid());
    }
}

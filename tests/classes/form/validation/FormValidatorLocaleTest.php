<?php

/**
 * @file tests/classes/form/validation/FormValidatorLocaleTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLocaleTest
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorLocale
 *
 * @brief Test class for FormValidatorLocale.
 */


require_mock_env('env1');

import('lib.pkp.tests.PKPTestCase');

use PKP\form\Form;
use PKP\form\validation\FormValidator;

class FormValidatorLocaleTest extends PKPTestCase
{
    /**
     * @covers FormValidatorLocale::getMessage
     */
    public function testGetMessage()
    {
        $form = new Form('some template');
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('##some.message.key## (English (United States))', $formValidator->getMessage());
    }

    /**
     * @covers FormValidatorLocale::getFieldValue
     */
    public function testGetFieldValue()
    {
        $form = new Form('some template');
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $form->setData('testData', null);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $form->setData('testData', ['en_US' => null]);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $form->setData('testData', ['en_US' => 0]);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('0', $formValidator->getFieldValue());

        $form->setData('testData', ['en_US' => '0']);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('0', $formValidator->getFieldValue());

        $form->setData('testData', ' some text ');
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $form->setData('testData', ['de_DE' => ' some text ']);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('', $formValidator->getFieldValue());

        $form->setData('testData', ['en_US' => ' some text ']);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame('some text', $formValidator->getFieldValue());

        $form->setData('testData', ['en_US' => [' some text ']]);
        $formValidator = new \PKP\form\validation\FormValidatorLocale($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertSame([' some text '], $formValidator->getFieldValue());
    }
}

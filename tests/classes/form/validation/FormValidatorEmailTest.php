<?php

/**
 * @file tests/classes/form/validation/FormValidatorEmailTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorEmailTest
 *
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorEmail
 *
 * @brief Test class for FormValidatorEmail.
 */

namespace PKP\tests\classes\form\validation;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorEmail;
use PKP\tests\PKPTestCase;

class FormValidatorEmailTest extends PKPTestCase
{
    /**
     * @covers FormValidatorEmail
     * @covers FormValidator
     */
    public function testIsValid()
    {
        $form = new Form('some template');

        $form->setData('testData', 'some.address@gmail.com');
        $validator = new FormValidatorEmail($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertTrue($validator->isValid());
        self::assertEquals(['testData' => ['required', 'email']], $form->cssValidation);

        $form->setData('testData', 'anything else');
        $validator = new FormValidatorEmail($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertFalse($validator->isValid());
    }
}

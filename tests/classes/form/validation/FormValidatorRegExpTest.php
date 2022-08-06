<?php

/**
 * @file tests/classes/form/validation/FormValidatorRegExpTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorRegExpTest
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorRegExp
 *
 * @brief Test class for FormValidatorRegExp.
 */

namespace PKP\tests\classes\form\validation;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorRegExp;
use PKP\tests\PKPTestCase;

class FormValidatorRegExpTest extends PKPTestCase
{
    /**
     * @covers FormValidatorRegExp
     * @covers FormValidator
     */
    public function testIsValid()
    {
        $form = new Form('some template');
        $form->setData('testData', 'some data');

        $validator = new FormValidatorRegExp($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', '/some.*/');
        self::assertTrue($validator->isValid());

        $validator = new FormValidatorRegExp($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', '/some more.*/');
        self::assertFalse($validator->isValid());
    }
}

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

import('lib.pkp.tests.PKPTestCase');

use PKP\form\Form;
use PKP\form\validation\FormValidator;

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

        $validator = new \PKP\form\validation\FormValidatorRegExp($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', '/some.*/');
        self::assertTrue($validator->isValid());

        $validator = new \PKP\form\validation\FormValidatorRegExp($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', '/some more.*/');
        self::assertFalse($validator->isValid());
    }
}

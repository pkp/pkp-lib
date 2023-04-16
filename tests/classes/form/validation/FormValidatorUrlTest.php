<?php

/**
 * @file tests/classes/form/validation/FormValidatorUrlTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorUrlTest
 *
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorUrl
 *
 * @brief Test class for FormValidatorUrl.
 */

namespace PKP\tests\classes\form\validation;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorUrl;
use PKP\tests\PKPTestCase;

class FormValidatorUrlTest extends PKPTestCase
{
    /**
     * @covers FormValidatorUrl
     * @covers FormValidator
     */
    public function testIsValid()
    {
        $form = new Form('some template');

        // test valid urls
        $form->setData('testUrl', 'http://some.domain.org/some/path?some=query#fragment');
        $validator = new FormValidatorUrl($form, 'testUrl', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertTrue($validator->isValid());
        self::assertEquals(['testUrl' => ['required', 'url']], $form->cssValidation);

        $form->setData('testUrl', 'http://192.168.0.1/');
        $validator = new FormValidatorUrl($form, 'testUrl', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertTrue($validator->isValid());

        // test invalid urls
        $form->setData('testUrl', 'http//missing-colon.org');
        $validator = new FormValidatorUrl($form, 'testUrl', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertFalse($validator->isValid());

        $form->setData('testUrl', 'http:/missing-slash.org');
        $validator = new FormValidatorUrl($form, 'testUrl', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key');
        self::assertFalse($validator->isValid());
    }
}

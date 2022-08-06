<?php

/**
 * @file tests/classes/form/validation/FormValidatorLengthTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorLengthTest
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorLength
 *
 * @brief Test class for FormValidatorLength.
 */

namespace PKP\tests\classes\form\validation;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorLength;
use PKP\tests\PKPTestCase;

class FormValidatorLengthTest extends PKPTestCase
{
    /**
     * @covers FormValidatorLength
     * @covers FormValidator
     */
    public function testIsValid()
    {
        $form = new Form('some template');
        $form->setData('testData', 'test');

        // Encode the tests to be run against the validator
        $tests = [
            ['==', 4, true],
            ['==', 5, false],
            ['==', 3, false],
            ['!=', 4, false],
            ['!=', 5, true],
            ['!=', 3, true],
            ['<', 5, true],
            ['<', 4, false],
            ['>', 3, true],
            ['>', 4, false],
            ['<=', 4, true],
            ['<=', 5, true],
            ['<=', 3, false],
            ['>=', 4, true],
            ['>=', 3, true],
            ['>=', 5, false],
            ['...', 3, false]
        ];

        foreach ($tests as $test) {
            $validator = new FormValidatorLength($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $test[0], $test[1]);
            self::assertSame($test[2], $validator->isValid());
        }

        // Test optional validation type
        $form->setData('testData', '');
        $validator = new FormValidatorLength($form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key', '==', 4);
        self::assertTrue($validator->isValid());
    }
}

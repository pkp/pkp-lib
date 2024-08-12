<?php

/**
 * @file tests/classes/form/validation/FormValidatorInSetTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorInSetTest
 *
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorInSet
 *
 * @brief Test class for FormValidatorInSet.
 */

namespace PKP\tests\classes\form\validation;

use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorInSet;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormValidatorInSet::class)]
class FormValidatorInSetTest extends PKPTestCase
{
    public function testIsValid()
    {
        $form = new Form('some template');

        // Instantiate test validator
        $acceptedValues = ['val1', 'val2'];
        $validator = new FormValidatorInSet($form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $acceptedValues);

        $form->setData('testData', 'val1');
        self::assertTrue($validator->isValid());

        $form->setData('testData', 'anything else');
        self::assertFalse($validator->isValid());
    }
}

<?php

/**
 * @file tests/classes/form/validation/FormValidatorPostTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorPostTest
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorPost
 *
 * @brief Test class for FormValidatorPost.
 */

use PKP\form\Form;

require_mock_env('env1');

import('lib.pkp.tests.PKPTestCase');
import('classes.core.Request'); // This will import the mock request

class FormValidatorPostTest extends PKPTestCase
{
    /**
     * @covers FormValidatorPost
     * @covers FormValidator
     */
    public function testIsValid()
    {
        // Instantiate test validator
        $form = new Form('some template');
        $validator = new \PKP\form\validation\FormValidatorPost($form, 'some.message.key');

        $this->markTestSkipped('Disabled for static invocation of Request.');

        $request = Application::get()->getRequest();
        $request->setRequestMethod('POST');
        self::assertTrue($validator->isValid());

        $request->setRequestMethod('GET');
        self::assertFalse($validator->isValid());
    }
}

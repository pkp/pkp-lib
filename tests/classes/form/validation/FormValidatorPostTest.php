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

use APP\core\Request;
use PKP\core\Registry;
use PKP\form\Form;

import('lib.pkp.tests.PKPTestCase');

class FormValidatorPostTest extends PKPTestCase
{
    private Request $_request;
    private bool $_isPosted = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->_request = Application::get()->getRequest();
        $mock = Mockery::mock($this->_request)
            // Custom isPost()
            ->shouldReceive('isPost')->andReturn(fn () => $this->_isPosted)
            ->getMock();
        // Replace the request singleton by a mock
        Registry::set('request', $mock);
    }

    protected function tearDown(): void
    {
        // Restores the original request instance
        Registry::set('request', $this->_request);
        parent::tearDown();
    }

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

        $this->_isPosted = true;
        self::assertTrue($validator->isValid());

        $this->_isPosted = false;
        self::assertFalse($validator->isValid());
    }
}

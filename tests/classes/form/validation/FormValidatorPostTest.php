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

namespace PKP\tests\classes\form\validation;

use APP\core\Application;
use Mockery;
use PKP\core\Registry;
use PKP\form\Form;
use PKP\form\validation\FormValidatorPost;
use PKP\tests\PKPTestCase;

class FormValidatorPostTest extends PKPTestCase
{
    private bool $_isPosted = false;

    /**
     * @see PKPTestCase::getMockedRegistryKeys()
     */
    protected function getMockedRegistryKeys(): array
    {
        return [...parent::getMockedRegistryKeys(), 'request'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $request = Application::get()->getRequest();
        $mock = Mockery::mock($request)
            // Custom isPost()
            ->shouldReceive('isPost')->andReturnUsing(fn () => $this->_isPosted)
            ->getMock();
        // Replace the request singleton by a mock
        Registry::set('request', $mock);
    }

    /**
     * @covers FormValidatorPost
     * @covers FormValidator
     */
    public function testIsValid()
    {
        // Instantiate test validator
        $form = new Form('some template');
        $validator = new FormValidatorPost($form, 'some.message.key');

        $this->markTestSkipped('Disabled for static invocation of Request.');

        $this->_isPosted = true;
        self::assertTrue($validator->isValid());

        $this->_isPosted = false;
        self::assertFalse($validator->isValid());
    }
}

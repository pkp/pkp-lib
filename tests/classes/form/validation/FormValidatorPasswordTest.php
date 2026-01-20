<?php

/**
 * @file tests/classes/form/validation/FormValidatorPasswordTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorPasswordTest
 *
 * @brief Test class for FormValidatorPassword.
 */

namespace PKP\tests\classes\form\validation;

use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Mockery;
use PKP\core\Registry;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorPassword;
use PKP\site\Site;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormValidatorPassword::class)]
class FormValidatorPasswordTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock UncompromisedVerifier to bypass external API calls
        app()->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier {
            public function verify($data): bool
            {
                return true; // Always return safe (not compromised)
            }
        });

        // Mock Site with minPasswordLength = 8
        $siteMock = Mockery::mock(Site::class);
        $siteMock->shouldReceive('getMinPasswordLength')->andReturn(8);

        // Set site mock in Registry (getSite() retrieves from Registry)
        Registry::set('site', $siteMock);

        // Mock request
        $this->mockRequest();
    }

    protected function getMockedRegistryKeys(): array
    {
        return array_merge(parent::getMockedRegistryKeys(), ['site']);
    }

    protected function getMockedContainerKeys(): array
    {
        return [UncompromisedVerifier::class];
    }

    /**
     * Test that a strong password meeting all requirements passes validation.
     */
    public function testValidPasswordPasses()
    {
        $form = new Form('some template');

        // Password with: lowercase, uppercase, number, symbol, >= 8 chars
        $form->setData('password', 'Test@1234');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that a weak password missing requirements fails validation.
     */
    public function testWeakPasswordFails()
    {
        $form = new Form('some template');

        // Password missing: uppercase, symbols
        $form->setData('password', 'password123');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator->isValid());
    }

    /**
     * Test that mismatched password confirmation fails validation.
     */
    public function testPasswordConfirmationMismatch()
    {
        $form = new Form('some template');

        $form->setData('password', 'Test@1234');
        $form->setData('password2', 'Different@1234');

        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key',
            'password2'
        );

        self::assertFalse($validator->isValid());
    }

    /**
     * Test that optional password allows empty value.
     */
    public function testOptionalPasswordAllowsEmpty()
    {
        $form = new Form('some template');

        $form->setData('password', '');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }
}

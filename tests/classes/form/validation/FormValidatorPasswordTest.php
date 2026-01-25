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

        // Password with: letter, number, symbol, >= 8 chars
        $form->setData('password', 'test@1234');
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

        // Password missing: symbols
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

        $form->setData('password', 'test@1234');
        $form->setData('password2', 'different@1234');

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

    /**
     * Test that non-Latin passwords (Arabic) pass validation.
     * Arabic script has no case distinction, so mixed case requirement would fail.
     */
    public function testNonLatinArabicPasswordPasses()
    {
        $form = new Form('some template');

        // Arabic letters + Arabic-Indic numerals (١٢٣) + symbol
        $form->setData('password', 'كلمةسرية١٢٣!');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that non-Latin passwords (Chinese) pass validation.
     * Chinese script has no case distinction.
     */
    public function testNonLatinChinesePasswordPasses()
    {
        $form = new Form('some template');

        // Chinese characters + number + symbol
        $form->setData('password', '密码安全测试123!');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that non-Latin passwords (Cyrillic) pass validation.
     * Cyrillic has case but we no longer require mixed case.
     */
    public function testNonLatinCyrillicPasswordPasses()
    {
        $form = new Form('some template');

        // Cyrillic lowercase letters + number + symbol (no uppercase needed)
        $form->setData('password', 'пароль123!');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that non-Latin password without number fails validation.
     */
    public function testNonLatinPasswordWithoutNumberFails()
    {
        $form = new Form('some template');

        // Arabic letters + symbol but NO number
        $form->setData('password', 'كلمةسريةطويلة!');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator->isValid());
    }

    /**
     * Test that non-Latin password without symbol fails validation.
     */
    public function testNonLatinPasswordWithoutSymbolFails()
    {
        $form = new Form('some template');

        // Chinese characters + number but NO symbol
        $form->setData('password', '密码安全测试12345');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator->isValid());
    }
}

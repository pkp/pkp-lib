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
     * Test that a password shorter than minimum length fails validation.
     */
    public function testPasswordTooShortFails()
    {
        $form = new Form('some template');

        // 7 chars, min is 8
        $form->setData('password', '1234567');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator->isValid());
    }

    /**
     * Test that a password at exactly minimum length passes validation.
     */
    public function testPasswordExactMinimumLengthPasses()
    {
        $form = new Form('some template');

        // Exactly 8 chars
        $form->setData('password', '12345678');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that a password longer than minimum length passes validation.
     */
    public function testPasswordLongerThanMinimumPasses()
    {
        $form = new Form('some template');

        // 20 chars
        $form->setData('password', '12345678901234567890');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test password validation with different minimum length configuration.
     */
    public function testPasswordWithDifferentMinLength()
    {
        // Mock Site with minPasswordLength = 12
        $siteMock = Mockery::mock(Site::class);
        $siteMock->shouldReceive('getMinPasswordLength')->andReturn(12);
        Registry::set('site', $siteMock);

        $form = new Form('some template');

        // 10 chars should fail with min 12
        $form->setData('password', 'abcdefghij');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator->isValid());

        // 12 chars should pass
        $form->setData('password', 'abcdefghijkl');
        $validator2 = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator2->isValid());
    }

    /**
     * Test that letters-only password passes (no number/symbol requirement).
     */
    public function testLettersOnlyPasswordPasses()
    {
        $form = new Form('some template');

        // Only lowercase letters, 8 chars
        $form->setData('password', 'abcdefgh');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that numbers-only password passes (no letter requirement).
     */
    public function testNumbersOnlyPasswordPasses()
    {
        $form = new Form('some template');

        // Only numbers, 8 chars
        $form->setData('password', '12345678');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that lowercase-only password passes (no mixed case requirement).
     */
    public function testLowercaseOnlyPasswordPasses()
    {
        $form = new Form('some template');

        // Only lowercase, no uppercase required
        $form->setData('password', 'lowercase');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that a compromised password fails validation.
     */
    public function testCompromisedPasswordFails()
    {
        // Override the UncompromisedVerifier to return compromised
        app()->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier {
            public function verify($data): bool
            {
                return false; // Password IS compromised
            }
        });

        $form = new Form('some template');

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
     * Test that required password with empty value fails validation.
     */
    public function testRequiredPasswordEmptyFails()
    {
        $form = new Form('some template');

        $form->setData('password', '');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator->isValid());
    }

    /**
     * Test that matching password confirmation passes validation.
     */
    public function testPasswordConfirmationMatching()
    {
        $form = new Form('some template');

        $form->setData('password', 'test@1234');
        $form->setData('password2', 'test@1234');

        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key',
            'password2'
        );

        self::assertTrue($validator->isValid());
    }

    /**
     * Test that Unicode password length is counted correctly.
     * Multi-byte characters should each count as one character.
     */
    public function testUnicodePasswordLengthCounting()
    {
        $form = new Form('some template');

        // 8 Chinese characters = 8 chars (even though 24 bytes in UTF-8)
        $form->setData('password', '密码安全测试好长');
        $validator = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertTrue($validator->isValid());

        // 7 Chinese characters = too short
        $form->setData('password', '密码安全测试好');
        $validator2 = new FormValidatorPassword(
            $form,
            'password',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key'
        );

        self::assertFalse($validator2->isValid());
    }

    /**
     * Test that non-Latin passwords (Arabic) pass validation.
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
}

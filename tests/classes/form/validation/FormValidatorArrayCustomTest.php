<?php

/**
 * @file tests/classes/form/validation/FormValidatorArrayCustomTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorArrayCustomTest
 * @ingroup tests_classes_form_validation
 *
 * @see FormValidatorArrayCustom
 *
 * @brief Test class for FormValidatorArrayCustom.
 */

import('lib.pkp.tests.PKPTestCase');

use PKP\form\Form;
use PKP\form\validation\FormValidator;

class FormValidatorArrayCustomTest extends PKPTestCase
{
    private $checkedValues = [];
    private $form;
    private $subfieldValidation;
    private $localeFieldValidation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = new Form('some template');
        $this->subfieldValidation = [$this, 'userFunctionForSubfields'];
        $this->localeFieldValidation = [$this, 'userFunctionForLocaleFields'];
    }

    /**
     * @covers FormValidatorArrayCustom
     * @covers FormValidator
     */
    public function testIsValidOptionalAndEmpty()
    {
        // Tests are completely bypassed when the validation type is
        // "optional" and the test data are empty. We make sure this is the
        // case by always returning 'false' for the custom validation function.
        $this->form->setData('testData', '');
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key', $this->subfieldValidation, [false]);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);

        $this->form->setData('testData', []);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key', $this->subfieldValidation, [false]);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);

        // The data are valid when they contain only empty (sub-)sub-fields and the validation type is "optional".
        $this->form->setData('testData', ['subfield1' => [], 'subfield2' => '']);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key', $this->subfieldValidation, [false]);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);

        $this->form->setData('testData', ['subfield1' => ['subsubfield1' => [], 'subsubfield2' => '']]);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_OPTIONAL_VALUE, 'some.message.key', $this->subfieldValidation, [false], false, ['subsubfield1', 'subsubfield2']);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);
    }

    /**
     * @covers FormValidatorArrayCustom
     * @covers FormValidator
     */
    public function testIsValidNoArray()
    {
        // Field data must be an array, otherwise validation fails
        $this->form->setData('testData', '');
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [true]);
        self::assertFalse($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);
    }

    /**
     * Check all sub-fields (default behavior of isValid)
     *
     * @covers FormValidatorArrayCustom
     * @covers FormValidator
     */
    public function testIsValidCheckAllSubfields()
    {
        // Check non-locale data
        $this->form->setData('testData', ['subfield1' => 'abc', 'subfield2' => '0']);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [true]);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame(['abc', '0'], $this->checkedValues);
        $this->checkedValues = [];

        // Check complement return
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [false], true);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame(['abc', '0'], $this->checkedValues);
        $this->checkedValues = [];

        // Simulate invalid data (check function returns false)
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [false]);
        self::assertFalse($validator->isValid());
        self::assertEquals(['testData[subfield1]', 'testData[subfield2]'], $validator->getErrorFields());
        self::assertSame(['abc', '0'], $this->checkedValues);
        $this->checkedValues = [];

        // Check locale data
        $this->form->setData('testData', ['en_US' => 'abc', 'de_DE' => 'def']);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->localeFieldValidation, [true], false, [], true);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame(['en_US' => ['abc'], 'de_DE' => ['def']], $this->checkedValues);
        $this->checkedValues = [];

        // Simulate invalid locale data
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->localeFieldValidation, [false], false, [], true);
        self::assertFalse($validator->isValid());
        self::assertEquals(['en_US' => 'testData[en_US]', 'de_DE' => 'testData[de_DE]'], $validator->getErrorFields());
        self::assertSame(['en_US' => ['abc'], 'de_DE' => ['def']], $this->checkedValues);
        $this->checkedValues = [];
    }

    /**
     * Check explicitly given sub-sub-fields within all sub-fields
     *
     * @covers FormValidatorArrayCustom
     * @covers FormValidator
     */
    public function testIsValidCheckExplicitSubsubfields()
    {
        // Check non-locale data
        $testArray = [
            'subfield1' => ['subsubfield1' => 'abc', 'subsubfield2' => 'def'],
            'subfield2' => ['subsubfield1' => '0', 'subsubfield2' => 0] // also test allowed boarder conditions
        ];
        $this->form->setData('testData', $testArray);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [true], false, ['subsubfield1', 'subsubfield2']);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame(['abc', 'def', '0', 0], $this->checkedValues);
        $this->checkedValues = [];

        // Check complement return
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [false], true, ['subsubfield1', 'subsubfield2']);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame(['abc', 'def', '0', 0], $this->checkedValues);
        $this->checkedValues = [];

        // Simulate invalid data (check function returns false)
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [false], false, ['subsubfield1', 'subsubfield2']);
        self::assertFalse($validator->isValid());
        $expectedErrors = [
            'testData[subfield1][subsubfield1]', 'testData[subfield1][subsubfield2]',
            'testData[subfield2][subsubfield1]', 'testData[subfield2][subsubfield2]'
        ];
        self::assertEquals($expectedErrors, $validator->getErrorFields());
        self::assertSame(['abc', 'def', '0', 0], $this->checkedValues);
        $this->checkedValues = [];

        // Check locale data
        $testArray = [
            'en_US' => ['subsubfield1' => 'abc', 'subsubfield2' => 'def'],
            'de_DE' => ['subsubfield1' => 'uvw', 'subsubfield2' => 'xyz']
        ];
        $this->form->setData('testData', $testArray);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->localeFieldValidation, [true], false, ['subsubfield1', 'subsubfield2'], true);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame(['en_US' => ['abc', 'def'], 'de_DE' => ['uvw', 'xyz']], $this->checkedValues);
        $this->checkedValues = [];

        // Simulate invalid locale data
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->localeFieldValidation, [false], false, ['subsubfield1', 'subsubfield2'], true);
        self::assertFalse($validator->isValid());
        $expectedErrors = [
            'en_US' => [
                'testData[en_US][subsubfield1]', 'testData[en_US][subsubfield2]'
            ],
            'de_DE' => [
                'testData[de_DE][subsubfield1]', 'testData[de_DE][subsubfield2]'
            ]
        ];
        self::assertEquals($expectedErrors, $validator->getErrorFields());
        self::assertSame(['en_US' => ['abc', 'def'], 'de_DE' => ['uvw', 'xyz']], $this->checkedValues);
        $this->checkedValues = [];
    }

    /**
     * Check a few border conditions
     *
     * @covers FormValidatorArrayCustom
     * @covers FormValidator
     */
    public function testIsValidWithBorderConditions()
    {
        // Make sure that we get 'null' in the user function
        // whenever an expected field doesn't exist in the value array.
        $testArray = [
            'subfield1' => ['subsubfield1' => null, 'subsubfield2' => ''],
            'subfield2' => ['subsubfield2' => 0]
        ];
        $this->form->setData('testData', $testArray);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [true], false, ['subsubfield1', 'subsubfield2']);
        self::assertTrue($validator->isValid());
        self::assertEquals([], $validator->getErrorFields());
        self::assertSame([null, '', null, 0], $this->checkedValues);
        $this->checkedValues = [];

        // Pass in a one-dimensional array where a two-dimensional array is expected
        $testArray = ['subfield1' => 'abc', 'subfield2' => 'def'];
        $this->form->setData('testData', $testArray);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation, [true], false, ['subsubfield']);
        self::assertFalse($validator->isValid());
        self::assertEquals(['testData[subfield1]', 'testData[subfield2]'], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);
        $this->checkedValues = [];

        // Pass in a one-dimensional locale array where a two-dimensional array is expected
        $testArray = ['en_US' => 'abc', 'de_DE' => 'def'];
        $this->form->setData('testData', $testArray);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->localeFieldValidation, [true], false, ['subsubfield'], true);
        self::assertFalse($validator->isValid());
        self::assertEquals(['en_US' => 'testData[en_US]', 'de_DE' => 'testData[de_DE]'], $validator->getErrorFields());
        self::assertSame([], $this->checkedValues);
        $this->checkedValues = [];
    }

    /**
     * Check explicitly given sub-sub-fields within all sub-fields
     *
     * @covers FormValidatorArrayCustom::isArray
     */
    public function testIsArray()
    {
        $this->form->setData('testData', ['subfield' => 'abc']);
        $validator = new \PKP\form\validation\FormValidatorArrayCustom($this->form, 'testData', FormValidator::FORM_VALIDATOR_REQUIRED_VALUE, 'some.message.key', $this->subfieldValidation);
        self::assertTrue($validator->isArray());

        $this->form->setData('testData', 'field');
        self::assertFalse($validator->isArray());
    }

    /**
     * This function is used as a custom validation callback for
     * one-dimensional data fields.
     * It simply reflects the additional argument so that we can
     * easily manipulate its return value. The values passed in
     * to this method are saved internally for later inspection.
     *
     * @param string $value
     * @param bool $additionalArgument
     *
     * @return bool the value passed in to $additionalArgument
     */
    public function userFunctionForSubfields($value, $additionalArgument)
    {
        $this->checkedValues[] = $value;
        return $additionalArgument;
    }

    /**
     * This function is used as a custom validation callback for
     * two-dimensional data fields.
     * It simply reflects the additional argument so that we can
     * easily manipulate its return value. The keys and values
     * passed in to this method are saved internally for later
     * inspection.
     *
     * @param string $value
     * @param string $key
     * @param bool $additionalArgument
     *
     * @return bool the value passed in to $additionalArgument
     */
    public function userFunctionForLocaleFields($value, $key, $additionalArgument)
    {
        if (!isset($this->checkedValues[$key])) {
            $this->checkedValues[$key] = [];
        }
        $this->checkedValues[$key][] = $value;
        return $additionalArgument;
    }
}

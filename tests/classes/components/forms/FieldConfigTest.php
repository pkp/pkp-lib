<?php

/**
 * @file tests/classes/components/forms/FieldConfigTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FieldConfigTest
 *
 * @ingroup tests_classes_components_forms
 *
 * @brief Test the serialized field config value fallback chain:
 *  value ?? default ?? getEmptyValue()
 */

namespace PKP\tests\classes\components\forms;

use PHPUnit\Framework\Attributes\CoversClass;
use PKP\components\forms\Field;
use PKP\components\forms\FieldMetadataSetting;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldSlider;
use PKP\components\forms\FieldText;
use PKP\components\forms\FieldUpload;
use PKP\components\forms\FormComponent;
use PKP\context\Context;
use PKP\tests\PKPTestCase;

#[CoversClass(Field::class)]
#[CoversClass(FormComponent::class)]
class FieldConfigTest extends PKPTestCase
{
    public function testValueFallsBackToEmptyValue()
    {
        $config = (new FieldText('title'))->getConfig();
        self::assertSame('', $config['value']);
    }

    public function testValueTakesPrecedenceOverDefaultAndEmptyValue()
    {
        $config = (new FieldText('title', ['value' => 'saved', 'default' => 'preset']))->getConfig();
        self::assertSame('saved', $config['value']);
    }

    public function testDefaultTakesPrecedenceOverEmptyValue()
    {
        $config = (new FieldText('title', ['default' => 'preset']))->getConfig();
        self::assertSame('preset', $config['value']);
    }

    public function testFalsyValuesAreNotClobbered()
    {
        // The null coalescing chain must only fall through on null, never on
        // other falsy values: an explicit false/0/''/[] is real data.
        self::assertSame(false, (new FieldOptions('toggle', ['value' => false, 'default' => true]))->getConfig()['value']);
        self::assertSame(0, (new FieldText('num', ['value' => 0, 'default' => 5]))->getConfig()['value']);
        self::assertSame('', (new FieldText('str', ['value' => '', 'default' => 'x']))->getConfig()['value']);
        self::assertSame([], (new FieldOptions('list', ['value' => [], 'default' => ['a']]))->getConfig()['value']);
    }

    public function testFieldOptionsEmptyValueDependsOnDetectedMode()
    {
        $toggleOptions = [['value' => true, 'label' => 'Enable']];
        $groupOptions = [
            ['value' => 'a', 'label' => 'A'],
            ['value' => 'b', 'label' => 'B'],
        ];

        // A single checkbox bound to a boolean option value is a boolean
        // on/off toggle
        self::assertSame(false, (new FieldOptions('toggle', ['options' => $toggleOptions]))->getConfig()['value']);
        self::assertSame(false, (new FieldOptions('inverted', ['options' => [['value' => false, 'label' => 'Hide']]]))->getConfig()['value']);

        // Any other checkbox is a multi-select group: several options, or a
        // single option holding a data value (e.g. a dynamic options list
        // reduced to one entry)
        self::assertSame([], (new FieldOptions('checkboxes', ['options' => $groupOptions]))->getConfig()['value']);
        self::assertSame([], (new FieldOptions('single', ['options' => [['value' => 12, 'label' => 'Section']]]))->getConfig()['value']);
        self::assertSame([], (new FieldOptions('empty'))->getConfig()['value']);

        // A radio holds a scalar
        self::assertSame('', (new FieldOptions('radios', ['type' => 'radio', 'options' => $toggleOptions]))->getConfig()['value']);

        // Multilingual options are keyed by locale, so the boolean toggle
        // convention cannot be detected there
        $multilingualField = new FieldOptions('multilingual', [
            'isMultilingual' => true,
            'options' => ['en' => $toggleOptions],
        ]);
        self::assertSame([], $multilingualField->getEmptyValue());
    }

    public function testFieldSliderEmptyValueIsMin()
    {
        $config = (new FieldSlider('days', ['min' => 3, 'max' => 10]))->getConfig();
        self::assertSame(3, $config['value']);
    }

    public function testFieldUploadEmptyValueIsNull()
    {
        // null is meaningful for uploads: the save handlers delete the stored
        // file when the value is null.
        $config = (new FieldUpload('file', ['options' => ['url' => 'http://example.com']]))->getConfig();
        self::assertNull($config['value']);
    }

    public function testFieldMetadataSettingEmptyValueIsDisabledValue()
    {
        $config = (new FieldMetadataSetting('keywords'))->getConfig();
        self::assertSame(Context::METADATA_DISABLE, $config['value']);
    }

    public function testMultilingualFieldConfigFillsEveryLocale()
    {
        $form = $this->getForm();
        $field = new FieldText('title', ['isMultilingual' => true]);
        $config = $form->getFieldConfig($field);
        self::assertSame(['en' => '', 'fr_CA' => ''], $config['value']);
    }

    public function testMultilingualFieldConfigKeepsExistingLocaleValues()
    {
        $form = $this->getForm();
        $field = new FieldText('title', ['isMultilingual' => true, 'value' => ['en' => 'Saved']]);
        $config = $form->getFieldConfig($field);
        self::assertSame(['en' => 'Saved', 'fr_CA' => ''], $config['value']);
    }

    public function testFieldConfigPreservesIntentionalNull()
    {
        // A field whose getEmptyValue() returns null must keep null through
        // FormComponent::getFieldConfig().
        $form = $this->getForm();
        $field = new FieldUpload('file', ['options' => ['url' => 'http://example.com']]);
        $config = $form->getFieldConfig($field);
        self::assertNull($config['value']);
    }

    public function testFieldConfigFillsEmptyValueWhenSubclassEmitsNull()
    {
        // A subclass that assigns its own null value in getConfig() bypasses
        // the fallback in Field::getConfig(); getFieldConfig() must catch it.
        $form = $this->getForm();
        $field = new class ('custom') extends Field {
            public $component = 'field-custom';

            public function getConfig()
            {
                $config = parent::getConfig();
                $config['value'] = null;
                return $config;
            }

            public function getEmptyValue()
            {
                return ['shaped'];
            }
        };
        $config = $form->getFieldConfig($field);
        self::assertSame(['shaped'], $config['value']);
    }

    protected function getForm(): FormComponent
    {
        return new FormComponent('testForm', 'PUT', 'http://example.com', [
            ['key' => 'en', 'label' => 'English'],
            ['key' => 'fr_CA', 'label' => 'French'],
        ]);
    }
}

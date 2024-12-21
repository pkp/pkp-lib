<?php

/**
 * @file tests/classes/metadata/MetadataPropertyTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataPropertyTest
 *
 * @ingroup tests_classes_metadata
 *
 * @see MetadataProperty
 *
 * @brief Test class for MetadataProperty.
 */

namespace PKP\tests\classes\metadata;

use APP\facades\Repo;
use InvalidArgumentException;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\metadata\MetadataDescription;
use PKP\metadata\MetadataProperty;
use PKP\tests\PKPTestCase;
use stdClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\user\interest\UserInterest;

#[CoversClass(MetadataProperty::class)]
class MetadataPropertyTest extends PKPTestCase
{
    public function testMetadataPropertyConstructor()
    {
        // test instantiation with non-default values
        $metadataProperty = new MetadataProperty('testElement', [0x001], [MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE => 0x002], false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY, 'non.default.displayName', 'non.default.validationMessage', true);
        self::assertEquals('testElement', $metadataProperty->getName());
        self::assertEquals([0x001], $metadataProperty->getAssocTypes());
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE => [0x002]], $metadataProperty->getAllowedTypes());
        self::assertFalse($metadataProperty->getTranslated());
        self::assertEquals(MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY, $metadataProperty->getCardinality());
        self::assertEquals('non.default.displayName', $metadataProperty->getDisplayName());
        self::assertEquals('non.default.validationMessage', $metadataProperty->getValidationMessage());
        self::assertTrue($metadataProperty->getMandatory());
        self::assertEquals('TestElement', $metadataProperty->getId());

        // Test translation
        $metadataProperty = new MetadataProperty('testElement', [0x001], MetadataProperty::METADATA_PROPERTY_TYPE_STRING, true);
        self::assertTrue($metadataProperty->getTranslated());

        // test normal instantiation with defaults
        $metadataProperty = new MetadataProperty('testElement');
        self::assertEquals('testElement', $metadataProperty->getName());
        self::assertEquals([], $metadataProperty->getAssocTypes());
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_STRING => [null]], $metadataProperty->getAllowedTypes());
        self::assertFalse($metadataProperty->getTranslated());
        self::assertEquals(MetadataProperty::METADATA_PROPERTY_CARDINALITY_ONE, $metadataProperty->getCardinality());
        self::assertEquals('metadata.property.displayName.testElement', $metadataProperty->getDisplayName());
        self::assertEquals('metadata.property.validationMessage.testElement', $metadataProperty->getValidationMessage());
        self::assertFalse($metadataProperty->getMandatory());
        self::assertEquals('TestElement', $metadataProperty->getId());
    }

    /**
     * Tests special error conditions while setting composite types
     */
    public function testCompositeWithoutParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE, false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }

    /**
     * Tests special error conditions while setting composite types
     */
    public function testCompositeWithWrongParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], [MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE => 'string'], false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }

    /**
     * Tests special error conditions while setting controlled vocab types
     */
    public function testControlledVocabWithoutParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], MetadataProperty::METADATA_PROPERTY_TYPE_VOCABULARY);
    }

    /**
     * Tests special error conditions while setting controlled vocab types
     */
    public function testControlledVocabWithWrongParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], [MetadataProperty::METADATA_PROPERTY_TYPE_VOCABULARY => 0x002], false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }

    /**
     * Tests special error conditions while setting non-parameterized type
     */
    public function testNonParameterizedTypeWithParameter()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], [MetadataProperty::METADATA_PROPERTY_TYPE_STRING => 0x002], false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }

    /**
     * Tests special error conditions while setting an unsupported type
     */
    public function testSetUnsupportedType()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], 0x99999999, true, MetadataProperty::METADATA_PROPERTY_CARDINALITY_MANY);
    }

    /**
     * Tests special error conditions while setting an unsupported cardinality
     */
    public function testSetUnsupportedCardinality()
    {
        $this->expectException(InvalidArgumentException::class);
        $metadataProperty = new MetadataProperty('testElement', [0x001], MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE, true, 0x99999999);
    }

    public function testValidateString()
    {
        $metadataProperty = new MetadataProperty('testElement');
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_STRING => null], $metadataProperty->isValid('any string'));
        self::assertFalse($metadataProperty->isValid(null));
        self::assertFalse($metadataProperty->isValid(5));
        self::assertFalse($metadataProperty->isValid(['string1', 'string2']));
    }

    public function testValidateUri()
    {
        $metadataProperty = new MetadataProperty('testElement', [], MetadataProperty::METADATA_PROPERTY_TYPE_URI);
        self::assertFalse($metadataProperty->isValid('any string'));
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_URI => null], $metadataProperty->isValid('ftp://some.domain.org/path'));
        self::assertFalse($metadataProperty->isValid(null));
        self::assertFalse($metadataProperty->isValid(5));
        self::assertFalse($metadataProperty->isValid(['ftp://some.domain.org/path', 'http://some.domain.org/']));
    }

    public function testValidateControlledVocabulary()
    {
        // Build a test vocabulary. (Assoc type and id are 0 to simulate a site-wide vocabulary).
        $vocab = Repo::controlledVocab()->build(
            UserInterest::CONTROLLED_VOCAB_INTEREST, 0, 0
        );

        // TODO : Investigate if possible to insert dummy symbolic in `controlled_vocab_entry_settings` table
        $controlledVocabEntry = ControlledVocabEntry::create([
            'controlledVocabId' => $vocab->id,
            UserInterest::CONTROLLED_VOCAB_INTEREST => [
                'en' => 'testEntry',
            ],
        ]);

        $metadataProperty = new MetadataProperty(
            'testElement',
            [], 
            [MetadataProperty::METADATA_PROPERTY_TYPE_VOCABULARY => UserInterest::CONTROLLED_VOCAB_INTEREST]
        );

        // This validator checks numeric values
        self::assertEquals(
            [MetadataProperty::METADATA_PROPERTY_TYPE_VOCABULARY => UserInterest::CONTROLLED_VOCAB_INTEREST],
            $metadataProperty->isValid($controlledVocabEntry->id)
        );
        self::assertFalse($metadataProperty->isValid($controlledVocabEntry->id + 1));

        // Delete the test vocabulary entry
        $controlledVocabEntry->delete();
        
    }

    public function testValidateDate()
    {
        $metadataProperty = new MetadataProperty('testElement', [], MetadataProperty::METADATA_PROPERTY_TYPE_DATE);
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_DATE => null], $metadataProperty->isValid('2009-10-25'));
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_DATE => null], $metadataProperty->isValid('2020-11'));
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_DATE => null], $metadataProperty->isValid('1847'));
        self::assertFalse($metadataProperty->isValid('XXXX'));
        self::assertFalse($metadataProperty->isValid('2009-10-35'));
        self::assertFalse($metadataProperty->isValid('2009-13-01'));
        self::assertFalse($metadataProperty->isValid('2009-12-1'));
        self::assertFalse($metadataProperty->isValid('2009-13'));
        self::assertFalse($metadataProperty->isValid(5));
        self::assertFalse($metadataProperty->isValid(['2009-10-25', '2009-10-26']));
    }

    public function testValidateInteger()
    {
        $metadataProperty = new MetadataProperty('testElement', [], MetadataProperty::METADATA_PROPERTY_TYPE_INTEGER);
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_INTEGER => null], $metadataProperty->isValid(5));
        self::assertFalse($metadataProperty->isValid(null));
        self::assertFalse($metadataProperty->isValid('a string'));
        self::assertFalse($metadataProperty->isValid([4, 8]));
    }

    public function testValidateComposite()
    {
        $metadataProperty = new MetadataProperty('testElement', [], [MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE => 0x002], false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_ONE);

        $metadataDescription = new MetadataDescription('lib.pkp.classes.metadata.MetadataSchema', 0x002);
        $anotherMetadataDescription = clone($metadataDescription);
        $stdObject = new stdClass();

        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE => 0x002], $metadataProperty->isValid($metadataDescription));
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_COMPOSITE => 0x002], $metadataProperty->isValid('2:5')); // assocType:assocId
        self::assertFalse($metadataProperty->isValid('1:5'));
        self::assertFalse($metadataProperty->isValid('2:xxx'));
        self::assertFalse($metadataProperty->isValid('2'));
        self::assertFalse($metadataProperty->isValid(null));
        self::assertFalse($metadataProperty->isValid(5));
        self::assertFalse($metadataProperty->isValid($stdObject));
        self::assertFalse($metadataProperty->isValid([$metadataDescription, $anotherMetadataDescription]));
    }

    public function testValidateMultitype()
    {
        $metadataProperty = new MetadataProperty('testElement', [], [MetadataProperty::METADATA_PROPERTY_TYPE_DATE, MetadataProperty::METADATA_PROPERTY_TYPE_INTEGER], false, MetadataProperty::METADATA_PROPERTY_CARDINALITY_ONE);
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_DATE => null], $metadataProperty->isValid('2009-07-28'));
        self::assertEquals([MetadataProperty::METADATA_PROPERTY_TYPE_INTEGER => null], $metadataProperty->isValid(5));
        self::assertFalse($metadataProperty->isValid(null));
        self::assertFalse($metadataProperty->isValid('string'));
    }
}

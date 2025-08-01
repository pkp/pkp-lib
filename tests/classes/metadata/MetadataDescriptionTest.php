<?php

/**
 * @file tests/classes/metadata/MetadataDescriptionTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetadataDescriptionTest
 *
 * @ingroup tests_classes_metadata
 *
 * @see MetadataDescription
 *
 * @brief Test class for MetadataDescription.
 */

namespace PKP\tests\classes\metadata;

use APP\core\Application;
use PKP\tests\classes\metadata\TestSchema;
use PKP\metadata\MetadataDescription;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversMethod;

#[CoversMethod(MetadataDescription::class, 'addStatement')]
#[CoversMethod(MetadataDescription::class, 'setStatements')]
class MetadataDescriptionTest extends PKPTestCase
{
    private MetadataDescription $metadataDescription;
    private static array $testStatements = [
        ['not-translated-one', 'nto', null],

        ['not-translated-many', 'ntm1', null],
        ['not-translated-many', 'ntm2', null],

        ['translated-one', 'to_en', 'en'],
        ['translated-one', 'to_de', 'de'],

        ['translated-many', 'tm1_en', 'en'],
        ['translated-many', 'tm1_de', 'de'],
        ['translated-many', 'tm2_en', 'en'],
        ['translated-many', 'tm2_de', 'de']
    ];
    private static array $testStatementsData = [
        'not-translated-one' => 'nto',
        'not-translated-many' => [
            0 => 'ntm1',
            1 => 'ntm2'
        ],
        'translated-one' => [
            'en' => 'to_en',
            'de' => 'to_de'
        ],
        'translated-many' => [
            'en' => [
                0 => 'tm1_en',
                1 => 'tm2_en'
            ],
            'de' => [
                0 => 'tm1_de',
                1 => 'tm2_de'
            ]
        ]
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->metadataDescription = new MetadataDescription(TestSchema::class, Application::ASSOC_TYPE_CITATION);
    }

    public function testAddStatement()
    {
        foreach (self::$testStatements as $test) {
            $this->metadataDescription->addStatement($test[0], $test[1], $test[2]);
        }

        self::assertEquals(self::$testStatementsData, $this->metadataDescription->getAllData());
    }

    public function testSetStatements()
    {
        $testStatements = self::$testStatementsData;
        $this->metadataDescription->setStatements($testStatements);
        self::assertEquals($testStatements, $this->metadataDescription->getAllData());

        $testStatements = [
            'not-translated-one' => 'nto-new',
            'not-translated-many' => [
                0 => 'ntm1-new',
                1 => 'ntm2-new'
            ],
            'translated-one' => [
                'en' => 'to_en-new',
                'de' => 'to_de-new'
            ],
            'translated-many' => [
                'en' => [
                    0 => 'tm1_en-new',
                    1 => 'tm2_en-new'
                ],
                'de' => [
                    0 => 'tm1_de-new',
                    1 => 'tm2_de-new'
                ]
            ]
        ];

        // Trying to replace a property with METADATA_DESCRIPTION_REPLACE_NOTHING
        // should provoke an error.
        $previousData = self::$testStatementsData;
        self::assertFalse($this->metadataDescription->setStatements($testStatements, MetadataDescription::METADATA_DESCRIPTION_REPLACE_NOTHING));
        self::assertEquals($previousData, $this->metadataDescription->getAllData());

        // Unset the offending property and try again - this should work
        $previousData = self::$testStatementsData;
        unset($previousData['not-translated-one']);
        unset($previousData['translated-one']);
        $this->metadataDescription->setAllData($previousData);
        $expectedResult = self::$testStatementsData;
        $expectedResult['not-translated-many'][] = 'ntm1-new';
        $expectedResult['not-translated-many'][] = 'ntm2-new';
        unset($expectedResult['translated-one']);
        $expectedResult['translated-one']['en'] = 'to_en-new';
        $expectedResult['translated-one']['de'] = 'to_de-new';
        $expectedResult['translated-many']['en'][] = 'tm1_en-new';
        $expectedResult['translated-many']['en'][] = 'tm2_en-new';
        $expectedResult['translated-many']['de'][] = 'tm1_de-new';
        $expectedResult['translated-many']['de'][] = 'tm2_de-new';
        unset($expectedResult['not-translated-one']);
        $expectedResult['not-translated-one'] = 'nto-new';
        self::assertTrue($this->metadataDescription->setStatements($testStatements, MetadataDescription::METADATA_DESCRIPTION_REPLACE_NOTHING));
        self::assertEquals($expectedResult, $this->metadataDescription->getAllData());

        // Using the default replacement level (METADATA_DESCRIPTION_REPLACE_PROPERTY)
        $previousData = self::$testStatementsData;
        $this->metadataDescription->setAllData($previousData);
        //unset($expectedResult['not-translated-many']);
        //$expectedResult['not-translated-many'] = array('ntm1-new', 'ntm2-new');
        self::assertTrue($this->metadataDescription->setStatements($testStatements));
        self::assertEquals($testStatements, $this->metadataDescription->getAllData());

        // Now test METADATA_DESCRIPTION_REPLACE_ALL
        self::assertTrue($this->metadataDescription->setStatements($testStatements, MetadataDescription::METADATA_DESCRIPTION_REPLACE_ALL));
        self::assertEquals($testStatements, $this->metadataDescription->getAllData());

        // Test that an error in the test statements maintains the previous state
        // of the description.
        // 1) Set some initial state (and make a non-referenced copy for later comparison)
        $previousData = ['non-translated-one' => 'previous-value'];
        $previousDataCopy = $previousData;
        $this->metadataDescription->setAllData($previousData);
        // 2) Create invalid test statement
        $testStatements['non-existent-property'] = 'some-value';
        // 3) Make sure that the previous data will always be restored when
        //    an error occurs.
        self::assertFalse($this->metadataDescription->setStatements($testStatements));
        self::assertEquals($previousDataCopy, $this->metadataDescription->getAllData());
        self::assertFalse($this->metadataDescription->setStatements($testStatements, true));
        self::assertEquals($previousDataCopy, $this->metadataDescription->getAllData());
    }
}

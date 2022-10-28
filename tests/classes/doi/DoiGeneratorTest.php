<?php

/**
 * @file tests/classes/dois/DoiGeneratorTest.php
 *
 * Copyright (c) 2013-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiGeneratorTest
 * @ingroup tests_classes_doi
 *
 * @see Core
 *
 * @brief Tests for the \PKP\doi\DoiGenerator
 */

namespace PKP\tests\classes\doi;

use PKP\doi\DoiGenerator;
use PKP\tests\PKPTestCase;

class DoiGeneratorTest extends PKPTestCase
{
    public function testEncodeDoi()
    {
        // Provides public access to protected method for testing
        $doiUtils = new class () extends DoiGenerator {
            public static function base32EncodeSuffix(int $number): string
            {
                return parent::base32EncodeSuffix($number);
            }
        };
        $number = 123;

        self::assertEquals('00003v20', $doiUtils::base32EncodeSuffix($number));
        self::assertEquals('00003v20', $doiUtils::base32EncodeSuffix((string) $number));
        self::assertMatchesRegularExpression(
            '/^[0-9abcdefghjkmnpqrstvwxyz]{6}[0-9]{2}$/',
            DoiGenerator::encodeSuffix()
        );
    }

    public function testDecodeDoi()
    {
        $validSuffix = DoiGenerator::encodeSuffix();
        $decodedValidSuffix = DoiGenerator::decodeSuffix($validSuffix);
        self::assertIsNumeric($decodedValidSuffix);

        $invalidSuffix = '00003v25';
        $decodedInvalidSuffix = DoiGenerator::decodeSuffix($invalidSuffix);
        self::assertNull($decodedInvalidSuffix);
    }
}

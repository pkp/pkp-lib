<?php

/**
 * @file tests/classes/context/ContextTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextTest
 *
 * @brief Tests for the Context class.
 */

namespace PKP\tests\classes\context;

use PHPUnit\Framework\Attributes\CoversClass;
use PKP\context\Context;
use PKP\tests\PKPTestCase;

#[CoversClass(Context::class)]
class ContextTest extends PKPTestCase
{
    /**
     * A minimal context, so the test does not depend on an application's Journal/Press/Server.
     */
    private function createContext(): Context
    {
        return new class () extends Context {
            public function getAssocType()
            {
                return 0;
            }
        };
    }

    /**
     * The acronym stands in for a missing abbreviation, as in metadata output.
     */
    public function testGetAbbreviationOrAcronymFallsBackToAcronymPerLocale(): void
    {
        $context = $this->createContext();
        $context->setData('abbreviation', ['en' => 'J. X', 'de' => '']);
        $context->setData('acronym', ['en' => 'JX', 'de' => 'ZX']);
        $this->assertSame(['en' => 'J. X', 'de' => 'ZX'], $context->getAbbreviationOrAcronym());
    }

    public function testGetAbbreviationOrAcronymIsNullWhenNothingSet(): void
    {
        $context = $this->createContext();
        $context->setData('acronym', ['en' => '']);
        $this->assertNull($context->getAbbreviationOrAcronym());
    }
}

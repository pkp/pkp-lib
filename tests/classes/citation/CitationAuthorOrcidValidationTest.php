<?php

/**
 * @file tests/classes/citation/CitationAuthorOrcidValidationTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CitationAuthorOrcidValidationTest
 *
 * @ingroup tests_classes_citation
 *
 * @see \PKP\citation\Repository
 *
 * @brief Test class for the validation of citation authors' ORCID iDs.
 */

namespace PKP\tests\classes\citation;

use PHPUnit\Framework\Attributes\CoversClass;
use PKP\citation\Repository;
use PKP\tests\PKPTestCase;

#[CoversClass(Repository::class)]
class CitationAuthorOrcidValidationTest extends PKPTestCase
{
    private function getAuthorErrors(?string $orcid): array
    {
        $errors = app(Repository::class)->validate(null, ['authors' => [['givenName' => 'Ada', 'familyName' => 'Lovelace', 'orcid' => $orcid]]]);
        return $errors['authors'] ?? [];
    }

    private function assertValid(?string $orcid): void
    {
        self::assertEmpty($this->getAuthorErrors($orcid), "The following ORCID iD should be valid: {$orcid}");
    }

    private function assertInvalid(string $orcid): void
    {
        self::assertNotEmpty($this->getAuthorErrors($orcid), "The following ORCID iD should be invalid: {$orcid}");
    }

    public function testValidOrcid(): void
    {
        $this->assertValid('https://orcid.org/0000-0002-1825-0097');
        $this->assertValid('https://sandbox.orcid.org/0000-0002-1825-0097');
        $this->assertValid('');
        $this->assertValid(null);
    }

    public function testInvalidOrcid(): void
    {
        $this->assertInvalid('0000-0002-1825-0097');
        $this->assertInvalid('https://orcid.org/0000-0002-1825-0098');
        $this->assertInvalid('https://example.com/not-an-orcid');
        $this->assertInvalid('javascript:alert(document.domain)');
    }
}

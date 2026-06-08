<?php

/**
 * @file tests/classes/dataCitation/DataCitationIdentifierValidationTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataCitationIdentifierValidationTest
 *
 * @ingroup tests_classes_dataCitation
 *
 * @see \PKP\dataCitation\Repository
 *
 * @brief Test class for DataCitation identifier validation.
 */

namespace PKP\tests\classes\dataCitation;

use PKP\dataCitation\Repository;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Repository::class)]
class DataCitationIdentifierValidationTest extends PKPTestCase
{
    private function assertValid(string $type, string $identifier): void
    {
        $errors = app(Repository::class)->validate(null, ['identifierType' => $type, 'identifier' => $identifier]);
        self::assertArrayNotHasKey('identifier', $errors, "The following identifier should be valid. Type: {$type}, Identifier: {$identifier}");
    }

    private function assertInvalid(string $type, string $identifier): void
    {
        $errors = app(Repository::class)->validate(null, ['identifierType' => $type, 'identifier' => $identifier]);
        self::assertArrayHasKey('identifier', $errors, "The following identifier should be invalid. Type: {$type}, Identifier: {$identifier}");
    }

    public function testDoiValidation(): void
    {
        $this->assertValid('DOI', '10.1234/5a6b-7c8d');
        $this->assertValid('DOI', 'doi:10.1234/5a6b-7c8d');
        $this->assertValid('DOI', 'https://doi.org/10.1234/5a6b-7c8d');
        $this->assertValid('DOI', 'https://dx.doi.org/10.1234/5a6b-7c8d');
        $this->assertInvalid('DOI', '10.123/too-short-prefix');
        $this->assertInvalid('DOI', 'just-random-text');
    }

    public function testArxivValidation(): void
    {
        $this->assertValid('ARXIV', '2301.00001');
        $this->assertValid('ARXIV', 'cs.AI/0601001');
        $this->assertValid('ARXIV', 'arxiv:2301.00001');
        $this->assertValid('ARXIV', 'https://arxiv.org/abs/2301.00001');
        $this->assertValid('ARXIV', 'https://arxiv.org/pdf/2301.00001');
        $this->assertInvalid('ARXIV', 'random-text');
    }

    public function testHandleValidation(): void
    {
        $this->assertValid('Handle', '11366/1249');
        $this->assertValid('Handle', '20.1000/xyz123');
        $this->assertValid('Handle', 'handle:11366/1249');
        $this->assertValid('Handle', 'hdl:11366/1249');
        $this->assertValid('Handle', 'https://hdl.handle.net/11366/1249');
        $this->assertInvalid('Handle', '11366');
        $this->assertInvalid('Handle', 'random-text');
    }

    public function testIssnValidation(): void
    {
        $this->assertValid('ISSN', '2167-8359');
        $this->assertValid('ISSN', '1234-567X');
        $this->assertValid('ISSN', 'issn:2167-8359');
        $this->assertValid('ISSN', 'https://portal.issn.org/resource/ISSN/2167-8359');
        $this->assertInvalid('ISSN', '21678359');
        $this->assertInvalid('ISSN', '1234-56789');
    }

    public function testIsbnValidation(): void
    {
        $this->assertValid('ISBN', '9780306406157');
        $this->assertValid('ISBN', '978-0-306-40615-7');
        $this->assertValid('ISBN', '030640615X');
        $this->assertValid('ISBN', '0-306-40615-X');
        $this->assertValid('ISBN', 'isbn:9780306406157');
        $this->assertValid('ISBN', 'isbn:978-0-306-40615-7');
        $this->assertInvalid('ISBN', '978030640615');
        $this->assertInvalid('ISBN', 'random-text');
    }

    public function testPmidValidation(): void
    {
        $this->assertValid('PMID', '12345678');
        $this->assertValid('PMID', 'pmid:12345678');
        $this->assertValid('PMID', 'PMID12345678');
        $this->assertValid('PMID', 'https://pubmed.ncbi.nlm.nih.gov/12345678');
        $this->assertInvalid('PMID', '1234abcd');
    }

    public function testPmcidValidation(): void
    {
        $this->assertValid('PMCID', 'PMC1234567');
        $this->assertValid('PMCID', 'pmcid:PMC1234567');
        $this->assertValid('PMCID', 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC1234567');
        $this->assertInvalid('PMCID', '1234567');
        $this->assertInvalid('PMCID', 'pmc1234567');
    }

    public function testUuidValidation(): void
    {
        $this->assertValid('UUID', '550e8400-e29b-41d4-a716-446655440000');
        $this->assertInvalid('UUID', '550e8400-e29b-41d4-a716');
        $this->assertInvalid('UUID', '550e8400e29b41d4a716446655440000');
    }

    public function testArkValidation(): void
    {
        $this->assertValid('ARK', 'ark:/12345/abc123');
        $this->assertValid('ARK', 'https://n2t.net/ark:/12345/abc123');
        $this->assertInvalid('ARK', 'ark:/abc/xyz');
        $this->assertInvalid('ARK', '12345/abc123');
    }

    public function testEcliValidation(): void
    {
        $this->assertValid('ECLI', 'ECLI:NL:HR:2020:1234');
        $this->assertValid('ECLI', 'ecli:NL:HR:2020:1234');
        $this->assertInvalid('ECLI', 'ECLI:NL:HR:20201:1234');
    }

    public function testUriValidation(): void
    {
        $this->assertValid('URI', 'https://example.com');
        $this->assertValid('URI', 'http://example.com/path?query=1');
        $this->assertInvalid('URI', 'ftp://example.com');
        $this->assertInvalid('URI', 'example.com');
    }

    public function testPurlValidation(): void
    {
        $this->assertValid('PURL', 'https://purl.org/dc/elements/1.1/');
        $this->assertInvalid('PURL', 'purl.org/dc/elements/1.1/');
    }

    public function testAccessionValidation(): void
    {
        $this->assertValid('Accession', 'NM_000546');
        $this->assertValid('Accession', 'anything-goes');
    }
}

<?php

/**
 * @file tests/classes/author/AuthorTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorTest
 *
 * @ingroup tests_classes_author
 *
 * @see Author
 *
 * @brief Test class for the Author class
 */

namespace PKP\tests\classes\author;

use APP\author\Author;
use APP\author\DAO;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\affiliation\Affiliation;
use PKP\services\PKPSchemaService;
use PKP\tests\PKPTestCase;

#[CoversClass(Author::class)]
class AuthorTest extends PKPTestCase
{
    private function newAuthor(): Author
    {
        return (new DAO(new PKPSchemaService()))->newDataObject();
    }

    /**
     * A fresh author keeps affiliations added one by one (the getter must not
     * hand back a throwaway collection).
     */
    public function testAddAffiliationToFreshAuthor()
    {
        $author = $this->newAuthor();
        $author->addAffiliation(new Affiliation());
        $author->addAffiliation(new Affiliation());

        self::assertCount(2, $author->getAffiliations());
    }

    /**
     * addAffiliation() works on a DAO-loaded author, whose affiliations are a
     * LazyCollection with no push().
     */
    public function testAddAffiliationWithLazyLoadedAffiliations()
    {
        $author = $this->newAuthor();
        $author->setAffiliations(LazyCollection::make(fn () => yield new Affiliation()));

        $author->addAffiliation(new Affiliation());

        self::assertCount(2, $author->getAffiliations());
    }
}

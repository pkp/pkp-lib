<?php

/**
 * @file tests/classes/filter/FilterDAOTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FilterDAOTest
 *
 * @ingroup tests_classes_filter
 *
 * @see FilterDAO
 *
 * @brief Test class for FilterDAO.
 */

namespace PKP\tests\classes\filter;

use PKP\db\DAORegistry;
use PKP\filter\PersistableFilter;
use PKP\filter\FilterDAO;
use PKP\filter\FilterGroup;
use PKP\filter\FilterGroupDAO;
use PKP\filter\GenericMultiplexerFilter;
use PKP\filter\GenericSequencerFilter;
use PKP\tests\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FilterDAO::class)]
class FilterDAOTest extends DatabaseTestCase
{
    /**
     * @see DatabaseTestCase::getAffectedTables()
     */
    protected function getAffectedTables()
    {
        return \PKP\tests\PKPTestHelper::PKP_TEST_ENTIRE_DB;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test filter group.
        $someGroup = new FilterGroup();
        $someGroup->setSymbolic('test-filter-group');
        $someGroup->setDisplayName('some.test.filter.group.display');
        $someGroup->setDescription('some.test.filter.group.description');
        $someGroup->setInputType('primitive::string');
        $someGroup->setOutputType('primitive::string');
        $filterGroupDao = DAORegistry::getDAO('FilterGroupDAO'); /** @var FilterGroupDAO $filterGroupDao */
        self::assertTrue(is_integer($filterGroupId = $filterGroupDao->insertObject($someGroup)));
    }

    public function testFilterCrud()
    {
        $filterDao = DAORegistry::getDAO('FilterDAO'); /** @var FilterDAO $filterDao */

        // Install a test filter object.
        $settings = ['seq' => '1', 'some-key' => 'some-value'];
        $testFilter = $filterDao->configureObject(PersistableTestFilterWithSetting::class, 'test-filter-group', $settings, false, 1);
        self::assertInstanceOf(PersistableFilter::class, $testFilter);
        $filterId = $testFilter->getId();
        self::assertTrue(is_integer($filterId));

        // Insert filter instance.
        self::assertTrue(is_numeric($filterId));
        self::assertTrue($filterId > 0);

        // Retrieve filter instance by id.
        $filterById = $filterDao->getObjectById($filterId);
        self::assertEquals($testFilter, $filterById);

        // Retrieve filter by group.
        $filtersByGroup = $filterDao->getObjectsByGroup('test-filter-group', 1);
        self::assertTrue(count($filtersByGroup) == 1);
        $filterByGroup = array_pop($filtersByGroup);
        self::assertEquals($testFilter, $filterByGroup);

        // Retrieve filter by class.
        $filtersByClassFactory = $filterDao->getObjectsByClass(PersistableTestFilterWithSetting::class, 1);
        $filterByClass = $filtersByClassFactory->next();
        $nonexistentSecondFilter = $filtersByClassFactory->next();
        assert($filterByClass !== null && $nonexistentSecondFilter === null);
        self::assertEquals($testFilter, $filterByClass);

        // Retrieve filter by group and class.
        $filtersByGroupAndClassFactory = $filterDao->getObjectsByGroupAndClass('test-filter-group', PersistableTestFilterWithSetting::class, 1);
        $filterByGroupAndClass = $filtersByGroupAndClassFactory->next();
        $nonexistentSecondFilter = $filtersByGroupAndClassFactory->next();
        assert($filterByClass !== null && $nonexistentSecondFilter === null);
        self::assertEquals($testFilter, $filterByGroupAndClass);

        // Update filter instance.
        $testFilter->setData('some-key', 'another value');
        $testFilter->setIsTemplate(true);

        $filterDao->updateObject($testFilter);
        $filterAfterUpdate = $filterDao->getObject($testFilter);
        self::assertEquals($testFilter, $filterAfterUpdate);

        // Delete filter instance.
        $filterDao->deleteObject($testFilter);
        self::assertNull($filterDao->getObjectById($filterId));
    }

    public function testCompositeFilterCrud()
    {
        $this->markTestSkipped('Broken test skipped');
        /** @var FilterDAO */
        $filterDao = DAORegistry::getDAO('FilterDAO');

        // sub-filter 1
        $subFilter1Settings = ['seq' => 1, 'displayName' => '1st sub-filter'];
        $subFilter1 = $filterDao->configureObject(PersistableTestFilterWithSettting::class, 'test-filter-group', $subFilter1Settings, false, 1, [], false);

        // sub-sub-filters for sub-filter 2
        $subSubFilter1Settings = ['seq' => 1, 'displayName' => '1st sub-sub-filter'];
        $subSubFilter1 = $filterDao->configureObject(PersistableTestFilterWithSetting::class, 'test-filter-group', $subSubFilter1Settings, false, 1, [], false);
        $subSubFilter2Settings = ['seq' => 2, 'displayName' => '2nd sub-sub-filter'];
        $subSubFilter2 = $filterDao->configureObject(PersistableTestFilterWithSetting::class, 'test-filter-group', $subSubFilter2Settings, false, 1, [], false);
        $subSubFilters = [$subSubFilter1, $subSubFilter2];

        // sub-filter 2
        $subFilter2Settings = ['seq' => 2, 'displayName' => '2nd sub-filter'];
        $subFilter2 = $filterDao->configureObject(GenericMultiplexerFilter::class, 'test-filter-group', $subFilter2Settings, false, 1, $subSubFilters, false);

        // Instantiate a composite test filter object
        $subFilters = [$subFilter1, $subFilter2];
        $testFilter = $filterDao->configureObject(GenericSequencerFilter::class, 'test-filter-group', ['seq' => 1], false, 1, $subFilters);
        self::assertInstanceOf('GenericSequencerFilter', $testFilter);
        $filterId = $testFilter->getId();
        self::assertTrue(is_numeric($filterId));
        self::assertTrue($filterId > 0);

        // Check that sub-filters were correctly
        // linked to the composite filter.
        $subFilters = & $testFilter->getFilters();
        self::assertEquals(2, count($subFilters));
        foreach ($subFilters as $subFilter) {
            self::assertTrue($subFilter->getId() > 0);
            self::assertEquals($filterId, $subFilter->getParentFilterId());
        }
        $subSubFilters = & $subFilters[2]->getFilters();
        self::assertEquals(2, count($subSubFilters));
        foreach ($subSubFilters as $subSubFilter) {
            self::assertTrue($subSubFilter->getId() > 0);
            self::assertEquals($subFilters[2]->getId(), $subSubFilter->getParentFilterId());
        }

        // Retrieve filter instance by id
        $filterById = $filterDao->getObjectById($filterId);
        self::assertEquals($testFilter, $filterById);

        // Update filter instance
        $filter = $testFilter->getFilterGroup();
        $testFilter = new GenericSequencerFilter($filter);
        $testFilter->setDisplayName('composite filter');
        $testFilter->setSequence(9999);
        $testFilter->setId($filterId);
        $testFilter->setIsTemplate(true);

        // leave out (sub-)sub-filter 2 but add a new (sub-)sub-filter 3
        // to test recursive update.
        $testFilter->addFilter($subFilter1);
        $filter = $testFilter->getFilterGroup();
        $subFilter3 = new GenericMultiplexerFilter($filter);
        $subFilter3->setDisplayName('3rd sub-filter');
        $subFilter3->addFilter($subSubFilter1);
        $subSubFilter3 = new PersistableTestFilterWithSetting($testFilter->getFilterGroup());
        $subSubFilter3->setDisplayName('3rd sub-sub-filter');
        $subFilter3->addFilter($subSubFilter3);
        $testFilter->addFilter($subFilter3);

        $filterDao->updateObject($testFilter);
        $filterAfterUpdate = $filterDao->getObject($testFilter);
        self::assertEquals($testFilter, $filterAfterUpdate);

        // Delete filter instance
        $filterDao->deleteObject($testFilter);
        self::assertNull($filterDao->getObjectById($filterId));
    }
}

<?php

/**
 * @file tests/classes/queue/PKPCliContextTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for CLI context management in job processing.
 */

namespace PKP\tests\classes\queue;

use Mockery;
use PKP\tests\PKPTestCase;
use APP\core\Application;
use PKP\context\Context;

class PKPCliContextTest extends PKPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Application::get()->clearCliContext();
    }

    protected function tearDown(): void
    {
        Application::get()->clearCliContext();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test setCliContext with Context object works directly
     */
    public function testSetCliContextWithContextObject(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(1);

        Application::get()->setCliContext($mockContext);

        $cliContext = Application::get()->getCliContext();
        $this->assertNotNull($cliContext);
        $this->assertEquals(1, $cliContext->getId());
    }

    /**
     * Test setCliContext throws for invalid context ID
     */
    public function testSetCliContextThrowsForInvalidId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid context ID');

        // Use a context ID that definitely doesn't exist
        Application::get()->setCliContext(999999);
    }

    /**
     * Test getCliContext returns set context
     */
    public function testGetCliContextReturnsSetContext(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(42);

        Application::get()->setCliContext($mockContext);

        $result = Application::get()->getCliContext();

        $this->assertNotNull($result);
        $this->assertEquals(42, $result->getId());
    }

    /**
     * Test clearCliContext resets to null
     */
    public function testClearCliContextResetsToNull(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(1);

        Application::get()->setCliContext($mockContext);
        $this->assertNotNull(Application::get()->getCliContext());

        Application::get()->clearCliContext();

        $this->assertNull(Application::get()->getCliContext());
    }

    /**
     * Test getCliContext returns null when not set
     */
    public function testGetCliContextReturnsNullWhenNotSet(): void
    {
        Application::get()->clearCliContext();

        $this->assertNull(Application::get()->getCliContext());
    }

    /**
     * Test CLI context can be updated
     */
    public function testCliContextCanBeUpdated(): void
    {
        $mockContext1 = Mockery::mock(Context::class);
        $mockContext1->shouldReceive('getId')->andReturn(1);

        $mockContext2 = Mockery::mock(Context::class);
        $mockContext2->shouldReceive('getId')->andReturn(2);

        Application::get()->setCliContext($mockContext1);
        $this->assertEquals(1, Application::get()->getCliContext()->getId());

        Application::get()->setCliContext($mockContext2);
        $this->assertEquals(2, Application::get()->getCliContext()->getId());
    }

    /**
     * Test setCliContext with null does update existing context
     */
    public function testSetCliContextWithNullUpdateExisting(): void
    {
        $mockContext = Mockery::mock(Context::class);
        $mockContext->shouldReceive('getId')->andReturn(5);

        Application::get()->setCliContext($mockContext);
        $this->assertNotNull(Application::get()->getCliContext());
        
        Application::get()->setCliContext(null);
        $this->assertNull(Application::get()->getCliContext());
        $this->assertNotEquals(5, Application::get()->getCliContext()?->getId());
    }
}

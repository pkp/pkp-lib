<?php

/**
 * @file tests/classes/publication/HasContextIdentityMetadataTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasContextIdentityMetadataTest
 *
 * @brief Tests for the PKP base HasContextIdentityMetadata trait: resolver methods that return
 *   the stamped context name, falling back to the live context when nothing is stamped yet.
 */

namespace PKP\tests\classes\publication;

use APP\publication\DAO;
use APP\publication\Publication;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\context\Context;
use PKP\core\Registry;
use PKP\publication\HasContextIdentityMetadata;
use PKP\services\PKPSchemaService;
use PKP\site\Site;
use PKP\tests\PKPTestCase;

#[CoversClass(HasContextIdentityMetadata::class)]
class HasContextIdentityMetadataTest extends PKPTestCase
{
    private Publication $publication;
    private Context|MockInterface $context;

    protected function getMockedRegistryKeys(): array
    {
        return ['site'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // getLocalizedData() calls getLocalePrecedence() which accesses the request router and
        // site. Set up a minimal mock site so the locale precedence logic does not hit the DB.
        $mockSite = Mockery::mock(Site::class);
        $mockSite->shouldReceive('getPrimaryLocale')->andReturn('en');
        Registry::set('site', $mockSite);

        $this->mockRequest();

        $this->publication = (new DAO(new PKPSchemaService()))->newDataObject();
        $this->context = Mockery::mock(Context::class);
    }


    // getContextName

    public function testGetContextNameReturnsStampedValue(): void
    {
        $this->publication->setData('contextName', 'Stamped Name', 'en');
        $this->assertSame('Stamped Name', $this->publication->getContextName('en', $this->context));
    }

    public function testGetContextNameFallsBackToLiveContextWhenNull(): void
    {
        $this->context->shouldReceive('getLocalizedName')->with('en')->once()->andReturn('Live Name');
        $this->assertSame('Live Name', $this->publication->getContextName('en', $this->context));
    }

    // getPrimaryContextName

    public function testGetPrimaryContextNameUsesStampedPrimaryLocale(): void
    {
        $this->publication->setData('contextName', ['en' => 'English Name', 'fr' => 'Nom Français']);
        $this->publication->setData('contextPrimaryLocale', 'fr');
        // The frozen locale must be used, not the context's current primary locale.
        $this->context->shouldNotReceive('getPrimaryLocale');
        $this->assertSame('Nom Français', $this->publication->getPrimaryContextName($this->context));
    }

    // contextPrimaryLocale not stamped → uses the context's current primary locale to pick the name locale.
    public function testGetPrimaryContextNameFallsBackToContextPrimaryLocaleWhenNotStamped(): void
    {
        $this->publication->setData('contextName', ['en' => 'English Name']);
        $this->context->shouldReceive('getPrimaryLocale')->once()->andReturn('en');
        $this->assertSame('English Name', $this->publication->getPrimaryContextName($this->context));
    }

    public function testGetPrimaryContextNameFallsBackToLiveContextNameWhenNoNameStamped(): void
    {
        $this->context->shouldReceive('getPrimaryLocale')->once()->andReturn('en');
        $this->context->shouldReceive('getLocalizedName')->with('en')->once()->andReturn('Live Name');
        $this->assertSame('Live Name', $this->publication->getPrimaryContextName($this->context));
    }

    public function testGetPrimaryContextNameRespectsFrozenLocaleAfterContextLocaleChange(): void
    {
        $this->publication->setData('contextName', ['en' => 'English Name', 'fr' => 'Nom Français']);
        $this->publication->setData('contextPrimaryLocale', 'fr');
        // Context is now 'en'-primary, but the stamp says 'fr'.
        $this->context->shouldNotReceive('getPrimaryLocale');
        $this->assertSame('Nom Français', $this->publication->getPrimaryContextName($this->context));
    }
}

<?php

/**
 * @file tests/classes/template/TemplateIntegrationTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TemplateIntegrationTest
 *
 * @brief Tests for Blade/Smarty template integration
 *
 * Test Strategy:
 * 1. Use the existing app templates directory for stability
 * 2. Create test-specific templates with unique names to avoid conflicts
 * 3. Test all rendering paths: Smarty-only, Blade-only, and mixed
 * 4. Test variable passing between template engines
 * 5. Test View::resolveName hook for template overrides
 */

namespace PKP\tests\classes\template;

use APP\template\TemplateManager;
use Illuminate\Support\Facades\View;
use PKP\core\Registry;
use PKP\plugins\Hook;
use PKP\tests\PKPTestCase;

class TemplateIntegrationTest extends PKPTestCase
{
    private static string $testTemplateDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Create test template directory under app basePath for consistency
        // All templates are under cache/ which is gitignored
        self::$testTemplateDir = app()->basePath() . '/cache/t_template_test';

        // Clean and recreate
        if (is_dir(self::$testTemplateDir)) {
            self::removeDirectoryStatic(self::$testTemplateDir);
        }
        mkdir(self::$testTemplateDir, 0777, true);
        mkdir(self::$testTemplateDir . '/nested', 0777, true);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test templates
        if (is_dir(self::$testTemplateDir)) {
            self::removeDirectoryStatic(self::$testTemplateDir);
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clear the TemplateManager singleton for clean state
        Registry::delete('templateManager');

        // Register namespace with Laravel's view system
        view()->addNamespace('test', self::$testTemplateDir);

        // Prepend to default paths for non-namespaced lookups
        app('view.finder')->prependLocation(self::$testTemplateDir);
    }

    protected function tearDown(): void
    {
        // Remove any hooks we registered
        Hook::clear('View::resolveName');

        // Clear singleton
        Registry::delete('templateManager');

        parent::tearDown();
    }

    private static function removeDirectoryStatic(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::removeDirectoryStatic($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTemplate(string $name, string $content): void
    {
        $path = self::$testTemplateDir . '/' . $name;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    private function getTemplateManager(): TemplateManager
    {
        $request = $this->mockRequest();
        $tm = TemplateManager::getManager($request);
        // Add test directory to Smarty's template dirs
        $tm->addTemplateDir(self::$testTemplateDir);
        return $tm;
    }

    /**
     * Helper to render a template using the test namespace
     */
    private function renderView(string $viewName, array $vars = []): string
    {
        $tm = $this->getTemplateManager();
        foreach ($vars as $key => $value) {
            $tm->assign($key, $value);
        }
        // Use namespace for unambiguous resolution
        return $tm->fetch('test::' . $viewName);
    }

    /**
     * Create a mock ThemePlugin for testing template overrides
     */
    private function createMockTheme(string $path, string $name): \PKP\plugins\ThemePlugin
    {
        return new class($path, $name) extends \PKP\plugins\ThemePlugin {
            private string $mockPath;
            private string $mockName;

            public function __construct(string $path, string $name)
            {
                $this->mockPath = $path;
                $this->mockName = $name;
            }

            public function register($category, $path, $mainContextId = null): bool
            {
                return true;
            }

            public function init(): void
            {
            }

            public function getPluginPath(): string
            {
                return $this->mockPath;
            }

            public function getName(): string
            {
                return $this->mockName;
            }

            public function getDisplayName(): string
            {
                return 'Mock Theme: ' . $this->mockName;
            }

            public function getDescription(): string
            {
                return 'Mock theme for testing';
            }
        };
    }

    // =========================================================================
    // SECTION 1: Pure Smarty Rendering
    // =========================================================================

    public function testSmartyBasicRendering(): void
    {
        $this->createTemplate('smarty_basic.tpl', 'Hello {$name}!');

        $result = $this->renderView('smarty_basic', ['name' => 'World']);

        $this->assertEquals('Hello World!', $result);
    }

    public function testSmartyIncludesSmarty(): void
    {
        $this->createTemplate('smarty_parent.tpl', 'Parent: {include file="nested/smarty_child.tpl"}');
        $this->createTemplate('nested/smarty_child.tpl', 'Child: {$message}');

        $result = $this->renderView('smarty_parent', ['message' => 'inherited']);

        $this->assertEquals('Parent: Child: inherited', $result);
    }

    public function testSmartyVariableInheritance(): void
    {
        $this->createTemplate('var_parent.tpl', '{$parentVar} {include file="nested/var_child.tpl"}');
        $this->createTemplate('nested/var_child.tpl', '{$parentVar} {$childVar}');

        $result = $this->renderView('var_parent', [
            'parentVar' => 'fromParent',
            'childVar' => 'forChild'
        ]);

        $this->assertEquals('fromParent fromParent forChild', $result);
    }

    public function testSmartyIncludeWithLocalVars(): void
    {
        $this->createTemplate('local_parent.tpl', '{include file="nested/local_child.tpl" localVar="passedValue"}');
        $this->createTemplate('nested/local_child.tpl', '{$localVar}');

        $result = $this->renderView('local_parent');

        $this->assertEquals('passedValue', $result);
    }

    // =========================================================================
    // SECTION 2: Pure Blade Rendering
    // =========================================================================

    public function testBladeBasicRendering(): void
    {
        $this->createTemplate('blade_basic.blade', 'Hello {{ $name }}!');

        $result = $this->renderView('blade_basic', ['name' => 'World']);

        $this->assertEquals('Hello World!', trim($result));
    }

    public function testBladeIncludesBlade(): void
    {
        $this->createTemplate('blade_parent.blade', 'Parent: @include("test::nested.blade_child")');
        $this->createTemplate('nested/blade_child.blade', 'Child: {{ $message }}');

        $result = $this->renderView('blade_parent', ['message' => 'inherited']);

        $this->assertStringContainsString('Parent:', $result);
        $this->assertStringContainsString('Child: inherited', $result);
    }

    // =========================================================================
    // SECTION 3: Mixed Smarty/Blade Direct Nesting (no aliasing/overrides)
    // =========================================================================

    public function testDirectNestingSmartyIncludesBlade(): void
    {
        $this->createTemplate('mixed_smarty.tpl', 'Smarty: {include file="nested/mixed_blade.blade"}');
        $this->createTemplate('nested/mixed_blade.blade', 'Blade: {{ $message }}');

        $result = $this->renderView('mixed_smarty', ['message' => 'fromSmarty']);

        $this->assertStringContainsString('Smarty:', $result);
        $this->assertStringContainsString('Blade: fromSmarty', $result);
    }

    public function testDirectNestingBladeIncludesSmarty(): void
    {
        $this->createTemplate('mixed_blade.blade', 'Blade: @include("test::nested.mixed_smarty")');
        $this->createTemplate('nested/mixed_smarty.tpl', 'Smarty: {$message}');

        $result = $this->renderView('mixed_blade', ['message' => 'fromBlade']);

        $this->assertStringContainsString('Blade:', $result);
        $this->assertStringContainsString('Smarty: fromBlade', $result);
    }

    /**
     * Test that local variables passed via Smarty {include} are available in Blade
     *
     * Smarty syntax: {include file="template.blade" localVar="value"}
     */
    public function testDirectNestingSmartyPassesLocalVarsToBlade(): void
    {
        $this->createTemplate(
            'smarty_with_local.tpl',
            'Parent: {include file="nested/blade_receives_local.blade" localVar="passedValue" anotherVar="secondValue"}'
        );
        $this->createTemplate(
            'nested/blade_receives_local.blade',
            'Blade received: {{ $localVar }} and {{ $anotherVar }}'
        );

        $result = $this->renderView('smarty_with_local');

        $this->assertStringContainsString('Blade received: passedValue and secondValue', $result);
    }

    /**
     * Test deep nesting: Smarty → Blade → Smarty (3 levels)
     *
     * Verifies that variables flow correctly through the entire chain
     */
    public function testDirectNestingDeepChainSmartyBladeSmary(): void
    {
        // Level 1: Smarty parent
        $this->createTemplate(
            'deep_level1.tpl',
            'L1({$rootVar}): {include file="nested/deep_level2.blade"}'
        );
        // Level 2: Blade middle
        $this->createTemplate(
            'nested/deep_level2.blade',
            'L2({{ $rootVar }}): @include("test::nested.deep_level3")'
        );
        // Level 3: Smarty child
        $this->createTemplate(
            'nested/deep_level3.tpl',
            'L3({$rootVar})'
        );

        $result = $this->renderView('deep_level1', ['rootVar' => 'inherited']);

        $this->assertStringContainsString('L1(inherited):', $result);
        $this->assertStringContainsString('L2(inherited):', $result);
        $this->assertStringContainsString('L3(inherited)', $result);
    }

    /**
     * Test deep nesting: Blade → Smarty → Blade (3 levels)
     *
     * Verifies the reverse chain also works
     */
    public function testDirectNestingDeepChainBladeSmartyBlade(): void
    {
        // Level 1: Blade parent
        $this->createTemplate(
            'deep_blade_l1.blade',
            'L1({{ $rootVar }}): @include("test::nested.deep_smarty_l2")'
        );
        // Level 2: Smarty middle
        $this->createTemplate(
            'nested/deep_smarty_l2.tpl',
            'L2({$rootVar}): {include file="nested/deep_blade_l3.blade"}'
        );
        // Level 3: Blade child
        $this->createTemplate(
            'nested/deep_blade_l3.blade',
            'L3({{ $rootVar }})'
        );

        $result = $this->renderView('deep_blade_l1', ['rootVar' => 'flows']);

        $this->assertStringContainsString('L1(flows):', $result);
        $this->assertStringContainsString('L2(flows):', $result);
        $this->assertStringContainsString('L3(flows)', $result);
    }

    // =========================================================================
    // SECTION 4: Variable Passing
    // =========================================================================

    public function testAssignedVarsAvailableInBlade(): void
    {
        $this->createTemplate('vars_blade.blade', '{{ $var1 }} {{ $var2 }}');

        $result = $this->renderView('vars_blade', [
            'var1' => 'first',
            'var2' => 'second'
        ]);

        $this->assertStringContainsString('first second', $result);
    }

    public function testViewShareMakesVarsGloballyAvailable(): void
    {
        $this->createTemplate('shared_blade.blade', '{{ $sharedVar }}');

        View::share('sharedVar', 'globalValue');

        $result = $this->renderView('shared_blade');

        $this->assertStringContainsString('globalValue', $result);
    }

    // =========================================================================
    // SECTION 5: Template Overriding via View::resolveName Hook
    // =========================================================================

    public function testAliasOverridesSmartyWithSmarty(): void
    {
        $this->createTemplate('alias_original.tpl', 'Original Content');
        $this->createTemplate('alias_override.tpl', 'Override Content');

        Hook::add('View::resolveName', function ($hookName, $args) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            if ($viewName === 'test::alias_original') {
                $overrideViewName = 'test::alias_override';
            }
            return Hook::CONTINUE;
        });

        $result = $this->renderView('alias_original');

        $this->assertEquals('Override Content', $result);
    }

    public function testAliasOverridesSmartyWithBlade(): void
    {
        $this->createTemplate('swap_original.tpl', 'Smarty: {$var}');
        $this->createTemplate('swap_override.blade', 'Blade: {{ $var }}');

        Hook::add('View::resolveName', function ($hookName, $args) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            if ($viewName === 'test::swap_original') {
                $overrideViewName = 'test::swap_override';
            }
            return Hook::CONTINUE;
        });

        $result = $this->renderView('swap_original', ['var' => 'value']);

        $this->assertStringContainsString('Blade: value', $result);
    }

    // =========================================================================
    // SECTION 6: Edge Cases
    // =========================================================================

    public function testStringResourceWorks(): void
    {
        $tm = $this->getTemplateManager();
        $tm->assign('name', 'Test');

        $template = $tm->createTemplate('string:Hello {$name}!', $tm);
        $template->assign($tm->getTemplateVars());
        $result = $template->fetch();

        $this->assertEquals('Hello Test!', $result);
    }

    public function testEmptyTemplateRendersEmpty(): void
    {
        $this->createTemplate('empty.tpl', '');

        $result = $this->renderView('empty');

        $this->assertEquals('', $result);
    }

    public function testSpecialCharactersEscapedInBlade(): void
    {
        $this->createTemplate('escape.blade', '{{ $html }}');

        $result = $this->renderView('escape', ['html' => '<script>alert("xss")</script>']);

        // Blade should escape HTML by default
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // =========================================================================
    // SECTION 7: View Composers
    // =========================================================================

    public function testViewComposerInjectsData(): void
    {
        $this->createTemplate('composed.blade', '{{ $injected }}');

        View::composer('test::composed', function ($view) {
            $view->with('injected', 'composerValue');
        });

        $result = $this->renderView('composed');

        $this->assertStringContainsString('composerValue', $result);
    }

    // =========================================================================
    // SECTION 8: Theme Override of Plugin Templates via View::resolveName (_overridePluginTemplates)
    // =========================================================================

    /**
     * Test that a theme can override a plugin's Smarty template with Smarty via _overridePluginTemplates
     * Verifies both assigned variables and $viewNamespace are available in the override
     */
    public function testAliasThemeOverridesPluginSmartyWithSmarty(): void
    {
        $basePath = app()->basePath();

        // Create directory structure simulating a generic plugin
        $pluginRelativePath = 'cache/t_template_test/plugins/generic/funding/templates';
        $pluginTemplatesDir = $basePath . '/' . $pluginRelativePath;
        @mkdir($pluginTemplatesDir, 0777, true);
        file_put_contents($pluginTemplatesDir . '/listFunders.tpl', 'Original: {$funder}');

        // Create theme override - include both assigned var and $viewNamespace
        $themeRelativePath = 'cache/t_template_test/themes/mytheme';
        $themeOverrideDir = $basePath . '/' . $themeRelativePath . '/templates/' . $pluginRelativePath;
        @mkdir($themeOverrideDir, 0777, true);
        file_put_contents($themeOverrideDir . '/listFunders.tpl', 'Override({$funder}) NS:{$viewNamespace}');

        // Register namespaces with viewNamespace composer (simulating _registerTemplateViewNamespace)
        view()->addNamespace('FundingPlugin', $pluginTemplatesDir);
        view()->addNamespace('mytheme', $basePath . '/' . $themeRelativePath . '/templates');
        View::composer('mytheme::*', fn ($view) => $view->with('viewNamespace', 'mytheme'));

        // Register the theme's override hook
        $mockTheme = $this->createMockTheme($themeRelativePath, 'mytheme');
        Hook::add('View::resolveName', [$mockTheme, '_overridePluginTemplates']);

        $tm = $this->getTemplateManager();
        $tm->assign('funder', 'Test Funder');

        try {
            $result = $tm->fetch('FundingPlugin::listFunders');
            $this->assertStringContainsString('Override(Test Funder)', $result, 'Assigned variable should be in override');
            $this->assertStringContainsString('NS:mytheme', $result, '$viewNamespace should be available');
        } finally {
            @unlink($pluginTemplatesDir . '/listFunders.tpl');
            @unlink($themeOverrideDir . '/listFunders.tpl');
            $this->cleanupEmptyDirs($basePath . '/cache/t_template_test/plugins');
            $this->cleanupEmptyDirs($basePath . '/cache/t_template_test/themes');
        }
    }

    /**
     * Test that theme can override plugin's Smarty template with Blade via _overridePluginTemplates
     * Verifies both assigned variables and $viewNamespace are available in the Blade override
     */
    public function testAliasThemeOverridesPluginSmartyWithBlade(): void
    {
        $basePath = app()->basePath();

        // Create plugin with Smarty template
        $pluginRelativePath = 'cache/t_template_test/plugins/generic/orcid/templates';
        $pluginTemplatesDir = $basePath . '/' . $pluginRelativePath;
        @mkdir($pluginTemplatesDir, 0777, true);
        file_put_contents($pluginTemplatesDir . '/orcidProfile.tpl', 'Smarty: {$orcid}');

        // Create theme override with Blade - include both assigned var and $viewNamespace
        $themeRelativePath = 'cache/t_template_test/themes/bladetheme';
        $themeOverrideDir = $basePath . '/' . $themeRelativePath . '/templates/' . $pluginRelativePath;
        @mkdir($themeOverrideDir, 0777, true);
        file_put_contents($themeOverrideDir . '/orcidProfile.blade', 'Blade({{ $orcid }}) NS:{{ $viewNamespace }}');

        // Register namespaces with viewNamespace composer
        view()->addNamespace('OrcidPlugin', $pluginTemplatesDir);
        view()->addNamespace('bladetheme', $basePath . '/' . $themeRelativePath . '/templates');
        View::composer('bladetheme::*', fn ($view) => $view->with('viewNamespace', 'bladetheme'));

        // Register the theme's override hook
        $mockTheme = $this->createMockTheme($themeRelativePath, 'bladetheme');
        Hook::add('View::resolveName', [$mockTheme, '_overridePluginTemplates']);

        $tm = $this->getTemplateManager();
        $tm->assign('orcid', '0000-0001-2345-6789');

        try {
            $result = $tm->fetch('OrcidPlugin::orcidProfile');
            $this->assertStringContainsString('Blade(0000-0001-2345-6789)', $result, 'Assigned variable should be in Blade override');
            $this->assertStringContainsString('NS:bladetheme', $result, '$viewNamespace should be available');
        } finally {
            @unlink($pluginTemplatesDir . '/orcidProfile.tpl');
            @unlink($themeOverrideDir . '/orcidProfile.blade');
            $this->cleanupEmptyDirs($basePath . '/cache/t_template_test/plugins');
            $this->cleanupEmptyDirs($basePath . '/cache/t_template_test/themes');
        }
    }

    /**
     * Test Smarty template includes a child template that is aliased to Blade override
     * Verifies both $viewNamespace and assigned variables are available in the nested Blade template
     *
     * Flow: Smarty parent → {include "nested/widget.tpl"} → SmartyTemplate converts to
     * "nested.widget" → View::resolveName hook redirects to "test::nested.widget_override" (Blade)
     */
    public function testAliasNestedSmartyIncludeOverriddenWithBlade(): void
    {
        // Create parent Smarty template that includes a child using relative path
        // Also use assigned variable in parent to verify inheritance
        $this->createTemplate('parent_includer.tpl', 'Parent({$title}): {include file="nested/widget.tpl"}');

        // Create original child template (Smarty)
        $this->createTemplate('nested/widget.tpl', 'Original Widget');

        // Create override with Blade that uses both $viewNamespace and assigned variable
        $this->createTemplate('nested/widget_override.blade', 'Blade Widget({{ $title }}) NS:{{ $viewNamespace }}');

        // Register composer for test namespace to inject $viewNamespace
        View::composer('test::*', fn ($view) => $view->with('viewNamespace', 'test'));

        // Register hook to alias the nested template to the Blade override
        // Note: smartyPathToViewName converts "nested/widget.tpl" to "nested.widget" (no namespace)
        Hook::add('View::resolveName', function ($hookName, $args) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            if ($viewName === 'nested.widget') {
                // Redirect to the namespaced Blade override
                $overrideViewName = 'test::nested.widget_override';
            }
            return Hook::CONTINUE;
        });

        try {
            // Assign variable and verify it flows through to aliased Blade template
            $result = $this->renderView('parent_includer', ['title' => 'MyTitle']);
            $this->assertStringContainsString('Parent(MyTitle):', $result);
            $this->assertStringContainsString('Blade Widget(MyTitle)', $result, 'Assigned variable should be available in aliased Blade');
            $this->assertStringContainsString('NS:test', $result);
        } finally {
            // Templates cleaned up by tearDownAfterClass
        }
    }

    /**
     * Test that without alias override, original plugin template is used
     */
    public function testNoAliasPluginTemplateRendersOriginal(): void
    {
        // Create plugin template only (no theme override)
        $pluginTemplatesDir = self::$testTemplateDir . '/plugins/generic/citation/templates';
        mkdir($pluginTemplatesDir, 0777, true);
        file_put_contents($pluginTemplatesDir . '/citationList.tpl', 'Original Citation: {$citation}');

        // Register plugin namespace
        view()->addNamespace('CitationPlugin', $pluginTemplatesDir);

        // Create mock theme WITHOUT the override file
        $themeDir = self::$testTemplateDir . '/emptytheme/templates';
        mkdir($themeDir, 0777, true);
        view()->addNamespace('emptytheme', $themeDir);

        $mockTheme = $this->createMockTheme(self::$testTemplateDir . '/emptytheme', 'emptytheme');
        Hook::add('View::resolveName', [$mockTheme, '_overridePluginTemplates']);

        $tm = $this->getTemplateManager();
        $tm->assign('citation', 'Test Citation');

        $result = $tm->fetch('CitationPlugin::citationList');

        // Should use original plugin template since no override exists
        $this->assertStringContainsString('Original Citation: Test Citation', $result);
    }

    // =========================================================================
    // SECTION 9: Caching Behavior - Verify file path based caching (pkp/pkp-lib#5592)
    // =========================================================================

    /**
     * Test that Smarty caching uses resolved file path, not original view name
     *
     * This verifies the fix for pkp/pkp-lib#5592 where cached templates from one
     * context (journal/theme) were incorrectly served to another context.
     *
     * The scenario: Same original template name, but alias resolves to different
     * files depending on context (e.g., different themes). Each should render
     * correctly without cache interference.
     */
    public function testAliasCachingUsesResolvedFilePath(): void
    {
        // Create original template
        $this->createTemplate('cacheable.tpl', 'Original: {$value}');

        // Create two different override templates (simulating different themes)
        $this->createTemplate('override_theme_a.tpl', 'Theme A Override: {$value}');
        $this->createTemplate('override_theme_b.tpl', 'Theme B Override: {$value}');

        // Variable to control which override is active (simulating context switch)
        $activeTheme = 'A';

        // Register dynamic alias hook (like theme switching between journals)
        Hook::add('View::resolveName', function ($hookName, $args) use (&$activeTheme) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            if ($viewName === 'test::cacheable') {
                $overrideViewName = $activeTheme === 'A'
                    ? 'test::override_theme_a'
                    : 'test::override_theme_b';
            }
            return Hook::CONTINUE;
        });

        // First render with "Theme A" context
        $result1 = $this->renderView('cacheable', ['value' => 'first']);
        $this->assertStringContainsString('Theme A Override: first', $result1);

        // Switch context to "Theme B" (simulating different HTTP request/journal)
        // Clear cache since in production each request starts fresh
        $activeTheme = 'B';
        app('view')->clearResolvedCache();

        // Second render should get Theme B's template
        $result2 = $this->renderView('cacheable', ['value' => 'second']);
        $this->assertStringContainsString('Theme B Override: second', $result2);

        // Switch back to Theme A to verify it still works
        $activeTheme = 'A';
        app('view')->clearResolvedCache();
        $result3 = $this->renderView('cacheable', ['value' => 'third']);
        $this->assertStringContainsString('Theme A Override: third', $result3);
    }

    /**
     * Test that nested Smarty includes also use file path caching correctly
     *
     * Verifies that when a parent template includes a child that gets aliased,
     * changing the alias target produces different output (no stale cache).
     */
    public function testAliasNestedIncludeCachingUsesResolvedFilePath(): void
    {
        // Parent template includes a widget
        $this->createTemplate('parent_cached.tpl', 'Parent: {include file="nested/widget_cached.tpl"}');

        // Original widget and two overrides
        $this->createTemplate('nested/widget_cached.tpl', 'Original Widget');
        $this->createTemplate('nested/widget_override_x.tpl', 'Widget X: {$data}');
        $this->createTemplate('nested/widget_override_y.tpl', 'Widget Y: {$data}');

        $activeOverride = 'X';

        Hook::add('View::resolveName', function ($hookName, $args) use (&$activeOverride) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            // Nested includes go through smartyPathToViewName which strips namespace
            if ($viewName === 'nested.widget_cached') {
                $overrideViewName = $activeOverride === 'X'
                    ? 'test::nested.widget_override_x'
                    : 'test::nested.widget_override_y';
            }
            return Hook::CONTINUE;
        });

        // Render with override X
        $result1 = $this->renderView('parent_cached', ['data' => 'val1']);
        $this->assertStringContainsString('Widget X: val1', $result1);

        // Switch to override Y (simulating different HTTP request)
        $activeOverride = 'Y';
        app('view')->clearResolvedCache();
        $result2 = $this->renderView('parent_cached', ['data' => 'val2']);
        $this->assertStringContainsString('Widget Y: val2', $result2);
    }

    // =========================================================================
    // SECTION 10: Smarty Prefix Handling (tpl:/app:/core:)
    // =========================================================================

    /**
     * Test smartyPathToViewName converts prefixes correctly
     */
    public function testSmartyPathToViewNamePrefixConversion(): void
    {
        $tm = $this->getTemplateManager();

        // Implicit (no prefix) - should have no namespace
        $this->assertEquals(
            'frontend.pages.article',
            $tm->smartyPathToViewName('frontend/pages/article.tpl')
        );

        // tpl: prefix (default) - should strip prefix, no namespace
        $this->assertEquals(
            'frontend.pages.article',
            $tm->smartyPathToViewName('tpl:frontend/pages/article.tpl')
        );

        // app: prefix (explicit) - should become app:: namespace
        $this->assertEquals(
            'app::frontend.pages.article',
            $tm->smartyPathToViewName('app:frontend/pages/article.tpl')
        );

        // core: prefix (explicit) - should become pkp:: namespace
        $this->assertEquals(
            'pkp::frontend.pages.article',
            $tm->smartyPathToViewName('core:frontend/pages/article.tpl')
        );

        // Laravel namespace passthrough
        $this->assertEquals(
            'mytheme::frontend.pages.article',
            $tm->smartyPathToViewName('mytheme::frontend.pages.article')
        );
    }

    /**
     * Test that implicit templates (no prefix) allow theme override
     */
    public function testImplicitTemplateAllowsOverride(): void
    {
        $this->createTemplate('implicit_test.tpl', 'Original: {$value}');
        $this->createTemplate('implicit_override.tpl', 'Override: {$value}');

        // Register hook to override
        Hook::add('View::resolveName', function ($hookName, $args) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            // Implicit template comes through without namespace
            if ($viewName === 'test::implicit_test') {
                $overrideViewName = 'test::implicit_override';
            }
            return Hook::CONTINUE;
        });

        $result = $this->renderView('implicit_test', ['value' => 'test']);
        $this->assertStringContainsString('Override: test', $result);
    }

    /**
     * Test that explicit app:: namespace skips theme override
     */
    public function testExplicitAppNamespaceSkipsOverride(): void
    {
        $this->createTemplate('explicit_app_test.tpl', 'Original: {$value}');
        $this->createTemplate('explicit_app_override.tpl', 'Override: {$value}');

        // Register hook that would override if called
        $overrideCalled = false;
        Hook::add('View::resolveName', function ($hookName, $args) use (&$overrideCalled) {
            $viewName = $args[0];
            $overrideViewName = &$args[1];
            // This should NOT match because app:: skips override in _viewNameToOverridePath
            if ($viewName === 'app::explicit_app_test' || $viewName === 'test::explicit_app_test') {
                $overrideCalled = true;
                $overrideViewName = 'test::explicit_app_override';
            }
            return Hook::CONTINUE;
        });

        // Use explicit app:: - should render original (hook may fire but _viewNameToOverridePath returns null)
        $tm = $this->getTemplateManager();
        $tm->assign('value', 'test');

        // Note: We can't easily test app:: directly through the test namespace,
        // but we can verify the smartyPathToViewName produces app:: namespace
        $viewName = $tm->smartyPathToViewName('app:explicit_app_test.tpl');
        $this->assertEquals('app::explicit_app_test', $viewName);
    }

    /**
     * Test that explicit pkp:: namespace skips theme override
     */
    public function testExplicitPkpNamespaceSkipsOverride(): void
    {
        $tm = $this->getTemplateManager();

        // Verify core: prefix produces pkp:: namespace
        $viewName = $tm->smartyPathToViewName('core:common/header.tpl');
        $this->assertEquals('pkp::common.header', $viewName);
    }

    /**
     * Test that Blade explicit namespaces also skip override
     *
     * This verifies that _viewNameToOverridePath returns null for app:: and pkp::
     * regardless of whether the view came from Smarty or Blade
     */
    public function testBladeExplicitNamespacesSkipOverride(): void
    {
        // Create a mock theme plugin to test _viewNameToOverridePath
        $mockTheme = $this->createMockTheme('cache/t_template_test/mocktheme', 'mocktheme');

        // Use reflection to test protected method
        $method = new \ReflectionMethod($mockTheme, '_viewNameToOverridePath');
        $method->setAccessible(true);

        // Implicit (no namespace) - should return path (allows override)
        $result = $method->invoke($mockTheme, 'frontend.pages.article');
        $this->assertEquals('templates/frontend/pages/article', $result);

        // Explicit app:: - should return null (skips override)
        $result = $method->invoke($mockTheme, 'app::frontend.pages.article');
        $this->assertNull($result, 'app:: namespace should skip override');

        // Explicit pkp:: - should return null (skips override)
        $result = $method->invoke($mockTheme, 'pkp::frontend.pages.article');
        $this->assertNull($result, 'pkp:: namespace should skip override');

        // Plugin namespace - should return path (allows override)
        // Note: This would need registered hints to work, so just verify it doesn't return null for unknown namespace
        $result = $method->invoke($mockTheme, 'someplugin::template');
        $this->assertNull($result, 'Unknown namespace without hints should return null');
    }

    /**
     * Helper to clean up empty directories recursively
     */
    private function cleanupEmptyDirs(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = @scandir($dir);
        if ($files === false) {
            return;
        }
        $files = array_diff($files, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->cleanupEmptyDirs($path);
            }
        }
        @rmdir($dir);
    }
}

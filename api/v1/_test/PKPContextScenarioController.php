<?php

/**
 * @file api/v1/_test/PKPContextScenarioController.php
 *
 * Copyright (c) 2023-2026 Simon Fraser University
 * Copyright (c) 2023-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextScenarioController
 *
 * @ingroup api_v1__test
 *
 * @brief POST /api/v1/_test/scenarios/context — seed a scratch context.
 *
 * A scratch context is what a test uses when it needs to mutate context-level
 * state: the shared base context seeded by bootstrap is read-only. The spec is
 * declarative — a tag, a path, throwaway users with roles, a small set of
 * settings — and the builder walks the application to that state through the
 * real context service, so the scratch context has the genres, user groups,
 * email templates and navigation menus a real one has.
 */

namespace PKP\API\v1\_test;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PKP\context\Context;
use PKP\services\PKPSchemaService;
use PKP\testing\scenario\ScenarioException;

class PKPContextScenarioController extends PKPTestApiController
{
    /**
     * Context spec keys that are structural rather than context settings.
     */
    protected const STRUCTURAL_KEYS = ['tag', 'urlPath', 'name', 'acronym', 'primaryLocale', 'supportedLocales', 'users', 'categories'];

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::post('scenarios/context', $this->addContext(...))->name('_test.scenarios.context');
    }

    public function addContext(Request $illuminateRequest): JsonResponse
    {
        return $this->build(function () use ($illuminateRequest) {
            $spec = $this->readSpec($illuminateRequest, $this->schema('context'));

            return $this->actingAs($this->siteAdmin(), function () use ($spec) {
                $context = $this->createContext($spec, '');
                $users = $this->seedUsers($context, $spec['users'] ?? [], 'users');

                return [
                    'tag' => $spec['tag'],
                    'contextId' => $context->getId(),
                    'urlPath' => $context->getPath(),
                    'users' => $users,
                ] + $this->contextEcho($context, $spec);
            });
        });
    }

    /**
     * Additional response fields an app wants echoed back (created section ids,
     * issue ids, ...). Ids are echoed so tests never have to look them up.
     */
    protected function contextEcho(Context $context, array $spec): array
    {
        return [];
    }

    /**
     * Build a context to the state the spec describes.
     *
     * @throws ScenarioException
     */
    protected function createContext(array $spec, string $specKeyPrefix): Context
    {
        $request = Application::get()->getRequest();
        $tag = $spec['tag'] ?? null;
        $urlPath = $spec['urlPath'] ?? ($tag ? 'scratch-' . $this->slug($tag) : null);
        $key = fn (string $name) => $specKeyPrefix === '' ? $name : "{$specKeyPrefix}.{$name}";

        if (!$urlPath) {
            throw new ScenarioException('A context spec needs a urlPath (or a tag to derive one from).', $key('urlPath'));
        }

        if (Application::getContextDAO()->getByPath($urlPath)) {
            throw new ScenarioException("A context already exists at urlPath '{$urlPath}'.", $key('urlPath'));
        }

        $site = $request->getSite();
        $primaryLocale = $spec['primaryLocale'] ?? $site->getPrimaryLocale();
        $supportedLocales = $spec['supportedLocales'] ?? [$primaryLocale];

        foreach ($supportedLocales as $index => $locale) {
            if (!in_array($locale, $site->getInstalledLocales())) {
                // Locales are chosen at INSTALL time, so this is not fixable from
                // the spec: name the remedy rather than leaving the reader to
                // discover that `installed_locales` exists.
                throw new ScenarioException(
                    "Locale '{$locale}' is not installed on this site (installed: "
                        . implode(', ', $site->getInstalledLocales()) . '). Add it to [i18n] '
                        . 'installed_locales in the configuration file and re-run '
                        . 'tools/installTest.php --recreate-db.',
                    $key("supportedLocales.{$index}")
                );
            }
        }

        $context = Application::getContextDAO()->newDataObject();
        $context->setData('primaryLocale', $primaryLocale);
        $context->setData('supportedLocales', array_values(array_unique(array_merge([$primaryLocale], $supportedLocales))));
        $context->setData('urlPath', $urlPath);
        $context->setData('name', array_fill_keys($context->getData('supportedLocales'), $spec['name'] ?? $urlPath));
        $context->setData('acronym', array_fill_keys($context->getData('supportedLocales'), $spec['acronym'] ?? strtoupper(substr($urlPath, 0, 6))));
        $context->setData('enabled', true);

        $context = app()->get('context')->add($context, $request);
        $this->journal->recordContext($context->getId());

        return $this->inContext($context, function () use ($context, $spec, $specKeyPrefix, $request, $key) {
            $settings = $this->settingsFromSpec($context, $spec);

            if ($settings) {
                $context = app()->get('context')->edit($context, $settings, $request);
            }

            $this->afterContextCreated($context, $spec, $specKeyPrefix);

            $this->seedCategories($context, $spec['categories'] ?? [], $key('categories'));

            return app()->get('context')->get($context->getId());
        });
    }

    /**
     * Turn declared setting passthroughs into an edit() payload.
     *
     * Only keys the schema declares reach this point, and each is written through
     * the real context service, so the same hooks and validation run as when an
     * editor saves the settings form.
     */
    protected function settingsFromSpec(Context $context, array $spec): array
    {
        $schema = app()->get('schema')->get(PKPSchemaService::SCHEMA_CONTEXT);
        $settings = [];

        foreach ($spec as $name => $value) {
            if (in_array($name, static::STRUCTURAL_KEYS) || in_array($name, $this->nonSettingOverlayKeys())) {
                continue;
            }

            if (!isset($schema->properties->{$name})) {
                continue;
            }

            $settings[$name] = ($schema->properties->{$name}->multilingual ?? false)
                ? $this->localized($context, (string) $value)
                : $value;
        }

        return $settings;
    }

    /**
     * Overlay keys that describe structure rather than a context setting
     * (OJS sections and issues, for instance).
     */
    protected function nonSettingOverlayKeys(): array
    {
        return [];
    }

    /**
     * Hook for the app subclass to seed its own context-level structure.
     */
    protected function afterContextCreated(Context $context, array $spec, string $specKeyPrefix): void
    {
    }

    /**
     * @throws ScenarioException
     */
    protected function seedCategories(Context $context, array $categorySpecs, string $specKeyPrefix): array
    {
        $byPath = [];

        foreach (array_values($categorySpecs) as $index => $categorySpec) {
            $parentId = null;

            if (isset($categorySpec['parentPath'])) {
                if (!isset($byPath[$categorySpec['parentPath']])) {
                    throw new ScenarioException(
                        "Parent category '{$categorySpec['parentPath']}' must be listed before its child.",
                        "{$specKeyPrefix}.{$index}.parentPath"
                    );
                }
                $parentId = $byPath[$categorySpec['parentPath']];
            }

            $category = Repo::category()->newDataObject();
            $category->setContextId($context->getId());
            $category->setPath($categorySpec['path']);
            $category->setTitle($categorySpec['title'], $context->getPrimaryLocale());

            if (isset($categorySpec['description'])) {
                $category->setDescription($categorySpec['description'], $context->getPrimaryLocale());
            }

            $category->setParentId($parentId);

            $byPath[$categorySpec['path']] = Repo::category()->add($category);
        }

        return $byPath;
    }

    protected function slug(string $value): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($value))), '-');
    }
}

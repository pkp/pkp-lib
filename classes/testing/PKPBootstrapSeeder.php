<?php

/**
 * @file classes/testing/PKPBootstrapSeeder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBootstrapSeeder
 *
 * @brief Declarative base seed: one context, its sections/series, categories,
 * issues (OJS overlay), and the roster users with roles and sub-editor
 * assignments. Idempotent: when the context already exists the seed is a warm
 * no-op. The whole spec is parsed (unknown keys → 400) before the first
 * write; every mutation goes through real application services (design
 * record 3); app specifics live in each app's subclass.
 */

namespace PKP\testing;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;

abstract class PKPBootstrapSeeder
{
    protected UserSeeder $userSeeder;
    protected ContextFactory $contextFactory;

    public function __construct()
    {
        $this->userSeeder = new UserSeeder();
        $this->contextFactory = new ContextFactory();
    }

    /** The spec key holding the content structure list: 'sections' or 'series'. */
    abstract protected function structureKey(): string;

    /** Read one section/series spec into a plain plan (no writes). */
    abstract protected function parseStructure(Spec $spec): array;

    /** Create one section/series from its parsed plan; returns its id. */
    abstract protected function addStructure(Context $context, array $plan, int $sequence): int;

    /** Resolve a structure identifier (abbrev/path) to its id. */
    abstract protected function resolveStructureId(Context $context, string $identifier): ?int;

    /** Parse app-overlay root keys (OJS: issues). Returns an opaque plan. */
    protected function parseOverlay(Spec $root): array
    {
        return [];
    }

    /** Execute the app overlay after context/structures/categories/users exist. */
    protected function executeOverlay(Context $context, array $overlayPlan): void
    {
    }

    public function probe(string $contextPath): array
    {
        $context = Application::getContextDAO()->getByPath($contextPath);
        return [
            'installed' => true,
            'seeded' => (bool) $context,
            'context' => $contextPath,
        ];
    }

    public function seed(array $data): array
    {
        $root = new Spec($data);
        $contextSpec = $root->child('context');
        if (!$contextSpec) {
            throw new SpecException('context', 'Missing required spec key "context"');
        }
        $path = (string) $contextSpec->require('path');

        $existing = Application::getContextDAO()->getByPath($path);
        if ($existing) {
            return ['seeded' => true, 'warm' => true, 'contextId' => $existing->getId()];
        }

        // Parse phase — reads every key; a stray key 400s before any write.
        $contextParams = $this->contextFactory->parseParams($contextSpec);
        $structurePlans = array_map(
            fn (Spec $spec) => $this->parseStructure($spec),
            $root->childList($this->structureKey())
        );
        $categoryPlans = array_map(
            fn (Spec $spec) => $this->parseCategory($spec),
            $root->childList('categories')
        );
        $userPlans = array_map(
            fn (Spec $spec) => $this->userSeeder->parse($spec, $this->structureKey()),
            $root->childList('users')
        );
        $overlayPlan = $this->parseOverlay($root);
        $root->assertConsumed();

        // Execute phase.
        $context = $this->contextFactory->create($contextParams);

        $sequence = 1;
        foreach ($structurePlans as $plan) {
            $this->addStructure($context, $plan, $sequence++);
        }
        foreach ($categoryPlans as $plan) {
            $this->addCategory($context, $plan, null);
        }
        $users = [];
        foreach ($userPlans as $plan) {
            $user = $this->userSeeder->seed(
                $context,
                $plan,
                fn (string $identifier) => $this->resolveStructureId($context, $identifier)
            );
            $users[] = ['id' => $user->getId(), 'username' => $user->getUsername()];
        }
        $this->executeOverlay($context, $overlayPlan);

        return [
            'seeded' => true,
            'warm' => false,
            'contextId' => $context->getId(),
            'users' => $users,
        ];
    }

    protected function parseCategory(Spec $spec): array
    {
        return [
            'path' => (string) $spec->require('path'),
            'title' => $spec->get('title'),
            'children' => array_map(
                fn (Spec $child) => $this->parseCategory($child),
                $spec->childList('children')
            ),
        ];
    }

    protected function addCategory(Context $context, array $plan, ?int $parentId): int
    {
        $locale = $context->getPrimaryLocale();
        $category = Repo::category()->newDataObject();
        $category->setData('contextId', $context->getId());
        $category->setData('path', $plan['path']);
        $title = is_array($plan['title']) ? $plan['title'] : [$locale => $plan['title'] ?? $plan['path']];
        foreach ($title as $titleLocale => $value) {
            $category->setData('title', $value, $titleLocale);
        }
        $category->setData('parentId', $parentId);
        // The category form always submits a sort option — its select
        // defaults to the app's default (CategoryForm ~124-131:
        // Repo::submission()->getDefaultSortOption()); a UI-created category
        // never stores NULL here (parity fix 2026-08-23).
        $category->setData('sortOption', Repo::submission()->getDefaultSortOption());
        $categoryId = Repo::category()->add($category);

        foreach ($plan['children'] as $childPlan) {
            $this->addCategory($context, $childPlan, $categoryId);
        }
        return $categoryId;
    }
}

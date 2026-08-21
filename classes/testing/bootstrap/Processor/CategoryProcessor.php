<?php

/**
 * @file classes/testing/bootstrap/Processor/CategoryProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CategoryProcessor
 *
 * @brief Creates a category tree for one journal.
 *
 * Nested inside JournalProcessor: called once per journal with that
 * journal's `categories` section of the spec. Recursively walks
 * children so the spec can express arbitrary-depth hierarchies.
 */

namespace PKP\testing\bootstrap\Processor;

use APP\facades\Repo;

class CategoryProcessor
{
    /**
     * @param int $contextId
     * @param array $categorySpecs [{path, title, children?}]
     * @return array Map of path => ['id' => int, 'children' => [...]]
     */
    public function run(int $contextId, array $categorySpecs): array
    {
        return $this->createTree($contextId, $categorySpecs, null);
    }

    private function createTree(int $contextId, array $categorySpecs, ?int $parentId): array
    {
        $map = [];
        foreach ($categorySpecs as $spec) {
            $category = Repo::category()->newDataObject([
                'contextId' => $contextId,
                'parentId' => $parentId,
                'path' => $spec['path'],
                'title' => $spec['title'],
            ]);
            $id = Repo::category()->add($category);

            $children = [];
            if (!empty($spec['children'])) {
                $children = $this->createTree($contextId, $spec['children'], $id);
            }
            $map[$spec['path']] = ['id' => $id, 'children' => $children];
        }
        return $map;
    }
}

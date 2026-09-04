<?php

/**
 * @file classes/testing/bootstrap/Processor/SectionProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SectionProcessor
 *
 * @brief Creates sections for one journal and, if the spec lists
 *        section editor usernames, assigns them.
 *
 * Invoked from PKPContextScenarioController. Section editor assignment
 * is split into `run()` (creates sections, captures editor usernames)
 * and `assignSectionEditors()` (resolves usernames once UserAssignment
 * has run).
 */

namespace PKP\testing\bootstrap\Processor;

use APP\core\Application;
use APP\facades\Repo;
use PKP\db\DAORegistry;
use PKP\testing\scenario\ScenarioContext;

class SectionProcessor
{
    /**
     * @param int $contextId
     * @param array $sectionSpecs [{abbrev, title, wordCount?, identifyType?, abstractsNotRequired?, sectionEditors?}]
     * @param string $journalPath  path of the owning journal — used to defer editor assignments
     * @return array [ 'editorsToAssign' => [ sectionId => [usernames] ], 'sections' => [abbrev => id] ]
     */
    public function run(int $contextId, array $sectionSpecs, string $journalPath): array
    {
        $sections = [];
        $editorsToAssign = [];

        // ContextService::add installs a localised default "Articles"
        // section on every new journal. Bootstrap specs are declarative —
        // they should own the full section list — so clear the default
        // before adding the spec's entries to avoid duplicate ARTs.
        Repo::section()->deleteByContextId($contextId);

        foreach ($sectionSpecs as $spec) {
            $section = Repo::section()->newDataObject([
                'contextId' => $contextId,
                'title' => $spec['title'],
                'abbrev' => $spec['abbrev'],
                'wordCount' => $spec['wordCount'] ?? 0,
                'identifyType' => $spec['identifyType'] ?? [],
                'abstractsNotRequired' => !empty($spec['abstractsNotRequired']),
                'editorRestricted' => !empty($spec['editorRestricted']),
            ]);
            $sectionId = Repo::section()->add($section);

            $abbrevKey = is_array($spec['abbrev'])
                ? ($spec['abbrev']['en'] ?? reset($spec['abbrev']))
                : (string)$spec['abbrev'];
            $sections[$abbrevKey] = $sectionId;

            if (!empty($spec['sectionEditors'])) {
                $editorsToAssign[$sectionId] = $spec['sectionEditors'];
            }
        }

        return ['sections' => $sections, 'editorsToAssign' => $editorsToAssign];
    }

    /**
     * Assign sub-editors to sections once their User rows exist. Called
     * by PKPContextScenarioController after UserAssignmentProcessor runs.
     */
    public function assignSectionEditors(int $contextId, array $editorsToAssign, ScenarioContext $ctx): void
    {
        /** @var \PKP\context\SubEditorsDAO $subEditorsDao */
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO');

        $sectionEditorGroupId = $this->findSectionEditorUserGroupId($contextId);

        foreach ($editorsToAssign as $sectionId => $usernames) {
            foreach ($usernames as $username) {
                $user = $ctx->userByUsername($username);
                $subEditorsDao->insertEditor(
                    $contextId,
                    $sectionId,
                    $user->getId(),
                    Application::ASSOC_TYPE_SECTION,
                    $sectionEditorGroupId
                );
            }
        }
    }

    private function findSectionEditorUserGroupId(int $contextId): int
    {
        $userGroup = \PKP\userGroup\UserGroup::withContextIds($contextId)
            ->where('nameLocaleKey', 'default.groups.name.sectionEditor')
            ->first();
        if (!$userGroup) {
            throw new \RuntimeException("Section editor user group not found for context {$contextId}");
        }
        return (int)$userGroup->id;
    }
}

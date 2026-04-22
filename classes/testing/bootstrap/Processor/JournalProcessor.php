<?php

/**
 * @file classes/testing/bootstrap/Processor/JournalProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JournalProcessor
 *
 * @brief Creates each journal (context) in the spec and cascades through
 *        its nested sections / categories / issues.
 *
 * Uses PKPContextService, which installs default user groups from
 * registry/userGroups.xml as part of add(). That's why UserProcessor
 * can safely assign users to role groups after this runs.
 *
 * Section editor assignments (usernames) are captured here but deferred
 * until after UserProcessor has run — BootstrapController calls
 * applyDeferredAssignments() at the end.
 */

namespace PKP\testing\bootstrap\Processor;

use APP\core\Application;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;

class JournalProcessor implements ScenarioProcessor
{
    /** Per-journal deferred section-editor assignments collected during run(). */
    private array $deferredSectionEditors = [];

    public function __construct(
        private SectionProcessor $sectionProcessor,
        private CategoryProcessor $categoryProcessor,
        private IssueProcessor $issueProcessor,
    ) {
    }

    public function appliesTo(array $spec): bool
    {
        return !empty($spec['journals']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        foreach ($spec['journals'] as $journalSpec) {
            $this->createJournal($journalSpec, $ctx);
        }
        return [];
    }

    private function createJournal(array $spec, ScenarioContext $ctx): void
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->newDataObject();

        $context->setAllData([
            'urlPath' => $spec['path'],
            'name' => $spec['name'],
            'description' => $spec['description'] ?? [],
            'acronym' => $spec['acronym'] ?? [],
            'abbreviation' => $spec['abbreviation'] ?? [],
            'publisherInstitution' => $spec['publisherInstitution'] ?? '',
            'primaryLocale' => $spec['primaryLocale'] ?? 'en',
            'supportedLocales' => $spec['supportedLocales'] ?? ['en'],
            'supportedFormLocales' => $spec['supportedLocales'] ?? ['en'],
            'supportedSubmissionLocales' => $spec['supportedLocales'] ?? ['en'],
            'country' => $spec['country'] ?? 'US',
            'contactName' => $spec['contact']['name'] ?? '',
            'contactEmail' => $spec['contact']['email'] ?? '',
            'supportName' => $spec['contact']['name'] ?? '',
            'supportEmail' => $spec['contact']['email'] ?? '',
            'onlineIssn' => $spec['onlineIssn'] ?? '',
            'printIssn' => $spec['printIssn'] ?? '',
            'enabled' => 1,
        ]);

        $contextService = app()->get('context');
        $request = Application::get()->getRequest();
        $context = $contextService->add($context, $request);
        $contextId = $context->getId();

        $sectionResult = !empty($spec['sections'])
            ? $this->sectionProcessor->run($contextId, $spec['sections'], $spec['path'])
            : ['sections' => [], 'editorsToAssign' => []];

        if (!empty($sectionResult['editorsToAssign'])) {
            $this->deferredSectionEditors[$contextId] = $sectionResult['editorsToAssign'];
        }

        $categoryMap = !empty($spec['categories'])
            ? $this->categoryProcessor->run($contextId, $spec['categories'])
            : [];

        $issueList = !empty($spec['issues'])
            ? $this->issueProcessor->run($contextId, $spec['issues'])
            : [];

        $ctx->recordJournal($spec['path'], [
            'id' => $contextId,
            'sections' => $sectionResult['sections'],
            'categories' => $categoryMap,
            'issues' => $issueList,
        ]);
    }

    /**
     * After UserProcessor has run, resolve each deferred section-editor
     * username and wire it into SubEditorsDAO.
     */
    public function applyDeferredAssignments(ScenarioContext $ctx): void
    {
        foreach ($this->deferredSectionEditors as $contextId => $assignments) {
            $this->sectionProcessor->assignSectionEditors($contextId, $assignments, $ctx);
        }
        $this->deferredSectionEditors = [];
    }
}

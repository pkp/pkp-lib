<?php

/**
 * @file classes/testing/PKPContextScenarioBuilder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextScenarioBuilder
 *
 * @brief POST /api/v1/_test/scenarios/context — seed a scratch context for
 * tests that need journal/press/server-level mutations (the shared base
 * context is read-only, PRINCIPLES A1).
 *
 * Step-2 core schema: tag* (parallel-isolation key, also the default urlPath —
 * ≤32 chars, single alphanumeric token), context {path, name, acronym,
 * description, primaryLocale, supportedLocales, contactName, contactEmail,
 * enabled}, users[] (throwaway accounts, same shape as the bootstrap roster
 * entries). Setting passthroughs return per feature, each with a parity entry.
 *
 * Feature passthroughs so far (each with a parity-ledger entry):
 * - orcid {enabled?, apiType?, clientId?, clientSecret?, city?,
 *   sendMailToAuthorsOnPublication?} — the "ORCID" settings-tab state (U4);
 *   written through PKPContextService::edit(), the same service the tab's
 *   form save runs through, with the tab's defaults-for-tests (enabled,
 *   Public Sandbox, dummy credentials). The OAuth exchange itself can never
 *   complete in the test env (outbound HTTP fails fast at the dead-port
 *   `[proxy]` in config.test.inc.php, and no real ORCID account backs the
 *   dummy sandbox credentials), so dummy credentials are exactly as good as
 *   real ones for every screen this state gates.
 */

namespace PKP\testing;

use APP\core\Application;
use PKP\context\Context;
use PKP\orcid\OrcidManager;
use PKP\testing\ContextFactory;
use PKP\testing\Spec;
use PKP\testing\SpecException;
use PKP\testing\UserSeeder;

abstract class PKPContextScenarioBuilder
{
    protected ContextFactory $contextFactory;
    protected UserSeeder $userSeeder;

    public function __construct()
    {
        $this->contextFactory = new ContextFactory();
        $this->userSeeder = new UserSeeder();
    }

    /** The user-spec structure key ('sections' or 'series'). */
    abstract protected function structureKey(): string;

    /** Resolve a structure identifier within the scratch context. */
    abstract protected function resolveStructureId(Context $context, string $identifier): ?int;

    public function build(array $data): array
    {
        $root = new Spec($data);

        $tag = (string) $root->require('tag');
        if (!preg_match('/^[a-zA-Z0-9]{1,32}$/', $tag)) {
            throw new SpecException('tag', 'tag must be a single alphanumeric token of at most 32 characters (it defaults to the context urlPath, varchar(32), and backs search scoping)');
        }

        // The context sub-spec defaults its path to the tag.
        $contextData = (array) ($root->get('context') ?? []);
        $contextData['path'] ??= $tag;
        $contextData['name'] ??= ['en' => "Scratch context {$tag}"];
        $contextSpec = new Spec($contextData, 'context');

        // Parse phase — unknown keys 400 before any write.
        $contextParams = $this->contextFactory->parseParams($contextSpec);
        $contextSpec->assertConsumed();
        $userPlans = array_map(
            fn (Spec $spec) => $this->userSeeder->parse($spec, $this->structureKey()),
            $root->childList('users')
        );
        $orcidSettings = $this->parseOrcidSettings($root);
        $root->assertConsumed();

        if (Application::getContextDAO()->getByPath((string) $contextData['path'])) {
            throw new SpecException('context.path', "A context with path \"{$contextData['path']}\" already exists — tags must be unique per run");
        }

        // Execute phase.
        $context = $this->contextFactory->create($contextParams);

        if ($orcidSettings !== null) {
            // The same service call the ORCID settings tab's form save runs
            // (PUT contexts/{id} → PKPContextService::edit — schema handles
            // the client-secret encryption exactly as the UI path does).
            $contextService = app()->get('context'); /** @var \PKP\services\PKPContextService $contextService */
            $context = $contextService->edit($context, $orcidSettings, Application::get()->getRequest());
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

        return [
            'tag' => $tag,
            'contextId' => $context->getId(),
            'path' => $context->getPath(),
            'users' => $users,
        ];
    }

    /**
     * The optional `orcid` sub-spec → the context-settings rows the "ORCID"
     * settings tab saves. Defaults produce the standard test configuration:
     * enabled, Public Sandbox, dummy credentials.
     */
    protected function parseOrcidSettings(Spec $root): ?array
    {
        $spec = $root->child('orcid');
        if ($spec === null) {
            return null;
        }
        $apiTypes = [
            OrcidManager::API_PUBLIC_PRODUCTION,
            OrcidManager::API_PUBLIC_SANDBOX,
            OrcidManager::API_MEMBER_PRODUCTION,
            OrcidManager::API_MEMBER_SANDBOX,
        ];
        $apiType = (string) $spec->get('apiType', OrcidManager::API_PUBLIC_SANDBOX);
        if (!in_array($apiType, $apiTypes)) {
            throw new SpecException('orcid.apiType', 'orcid.apiType must be one of: ' . implode(', ', $apiTypes));
        }
        $settings = [
            OrcidManager::ENABLED => (bool) $spec->get('enabled', true),
            OrcidManager::API_TYPE => $apiType,
            OrcidManager::CLIENT_ID => (string) $spec->get('clientId', 'APP-TESTCLIENTID'),
            OrcidManager::CLIENT_SECRET => (string) $spec->get('clientSecret', 'test-orcid-client-secret'),
        ];
        if ($spec->has('city')) {
            $settings[OrcidManager::CITY] = (string) $spec->get('city');
        }
        if ($spec->has('sendMailToAuthorsOnPublication')) {
            $settings[OrcidManager::SEND_MAIL_TO_AUTHORS_ON_PUBLICATION] = (bool) $spec->get('sendMailToAuthorsOnPublication');
        }
        return $settings;
    }
}

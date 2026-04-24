<?php

/**
 * @file classes/testing/scenario/Processor/ContextBuilderProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextBuilderProcessor
 *
 * @brief Creates a scratch context (journal/press/server) for per-test
 *        scenarios that need to mutate journal-level configuration without
 *        polluting the bootstrapped publicknowledge journal.
 *
 * Delegates to PKPContextService::add() so the new context receives the
 * standard default user groups, email templates, genres, navigation menus,
 * and (in OJS) default section + reviewer recommendations. Mirrors what
 * the bootstrap JournalProcessor does for the baseline journal — just
 * scoped to a single scratch context per request.
 */

namespace PKP\testing\scenario\Processor;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;

class ContextBuilderProcessor implements ScenarioProcessor
{
    public function appliesTo(array $spec): bool
    {
        return true;
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $contextDao = Application::getContextDAO();
        $context = $contextDao->newDataObject();

        $path = $spec['path'];
        $primaryLocale = $spec['primaryLocale'] ?? 'en';
        $supportedLocales = $spec['supportedLocales'] ?? [$primaryLocale];
        $name = $spec['name'] ?? [$primaryLocale => 'Scratch context ' . $spec['tag']];
        $contactName = $spec['contact']['name'] ?? 'Test Contact';
        $contactEmail = $spec['contact']['email'] ?? 'test@example.com';

        $data = [
            'urlPath' => $path,
            'name' => $name,
            'description' => $spec['description'] ?? [],
            'acronym' => $spec['acronym'] ?? [],
            'abbreviation' => $spec['abbreviation'] ?? [],
            'publisherInstitution' => $spec['publisherInstitution'] ?? '',
            'primaryLocale' => $primaryLocale,
            'supportedLocales' => $supportedLocales,
            'supportedFormLocales' => $supportedLocales,
            'supportedSubmissionLocales' => $supportedLocales,
            'country' => $spec['country'] ?? 'US',
            'contactName' => $contactName,
            'contactEmail' => $contactEmail,
            'supportName' => $contactName,
            'supportEmail' => $contactEmail,
            'enabled' => 1,
        ];

        // Optional multilingual journal-level settings that specs may
        // need to seed directly (e.g. the copyrightNotice required for
        // the submission wizard's copyright gate). Everything in this
        // block is a plain <locale> => <string> map accepted by the
        // context schema — tests pass only the locales they care
        // about, everything else falls through to the Journal's
        // schema default.
        foreach (['copyrightNotice'] as $optional) {
            if (isset($spec[$optional])) {
                $data[$optional] = $spec[$optional];
            }
        }

        // Scalar journal-level settings that specs may need to seed at
        // creation time (e.g. submitWithCategories to enable the wizard
        // Categories field without flipping it through the settings UI
        // mid-test; DOI prefix + toggles to skip the multi-tab DOI
        // Distribution settings UI).
        foreach (['submitWithCategories', 'enableDois', 'doiPrefix', 'doiVersioning', 'registrationAgency', 'onlineIssn', 'printIssn'] as $optionalScalar) {
            if (array_key_exists($optionalScalar, $spec)) {
                $data[$optionalScalar] = $spec[$optionalScalar];
            }
        }

        // Array journal-level settings (e.g. enabledDoiTypes — which
        // pubObject types auto-mint DOIs on publish). Default from the
        // OJS context schema is ['publication'], which is already what
        // our DOI tests want; pass through only when the test overrides
        // explicitly.
        foreach (['enabledDoiTypes'] as $optionalArray) {
            if (array_key_exists($optionalArray, $spec) && is_array($spec[$optionalArray])) {
                $data[$optionalArray] = $spec[$optionalArray];
            }
        }

        $context->setAllData($data);

        // PKPContextService::add() pulls $currentUser from $request->getUser()
        // and assigns them as the context's first manager. We can't call
        // Validation::registerUserSession here (it would rotate the browser
        // session cookie and log the test user out mid-test), so populate the
        // per-request Registry slot directly. $request->getUser() reads that
        // slot before falling back to the session cookie.
        $admin = Repo::user()->getByUsername('admin', true);
        if (!$admin) {
            throw new \RuntimeException('ContextBuilderProcessor: admin user missing — bootstrap must run first.');
        }
        $previousRegistryUser = Registry::get('user', true, null);
        Registry::set('user', $admin);

        try {
            $contextService = app()->get('context');
            $request = Application::get()->getRequest();
            $context = $contextService->add($context, $request);
        } finally {
            Registry::set('user', $previousRegistryUser);
        }

        $contextId = $context->getId();

        // Optional plugin enablement + per-plugin settings. Some features
        // (Crossref DOI registration, for one) require enabling a lazy-load
        // plugin and dropping credentials into its settings before the UI
        // will let you select it as the configured registration agency.
        // Driving those through the web UI would be multi-step + flaky;
        // seeding directly is the simpler path.
        if (!empty($spec['plugins']) && is_array($spec['plugins'])) {
            $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO');
            foreach ($spec['plugins'] as $pluginName => $pluginCfg) {
                if (!is_array($pluginCfg)) {
                    continue;
                }
                // `enabled` flips the lazy-load plugin's per-context enabled
                // flag (settings row keyed by 'enabled').
                if (array_key_exists('enabled', $pluginCfg)) {
                    $pluginSettingsDao->updateSetting(
                        $contextId,
                        $pluginName,
                        'enabled',
                        (bool)$pluginCfg['enabled'],
                        'bool'
                    );
                }
                // `settings` carries a plain name => value map, written to
                // the same plugin_settings row. Used for Crossref's
                // depositorName / depositorEmail / username / password
                // without exposing test credentials in the UI.
                foreach ($pluginCfg['settings'] ?? [] as $settingName => $settingValue) {
                    $pluginSettingsDao->updateSetting(
                        $contextId,
                        $pluginName,
                        $settingName,
                        $settingValue,
                        is_bool($settingValue) ? 'bool' : null
                    );
                }
            }
        }

        $ctx->recordJournal($path, [
            'id' => $contextId,
            'path' => $path,
            'name' => $name,
            'primaryLocale' => $primaryLocale,
        ]);

        return [];
    }
}

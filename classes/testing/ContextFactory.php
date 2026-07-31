<?php

/**
 * @file classes/testing/ContextFactory.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextFactory
 *
 * @brief Creates a journal/press/server through the SAME service the admin
 * "hosted contexts" flow uses — schema defaults, default user groups, genres,
 * navigation menus, email templates, Context::add hooks, and the acting
 * user's auto-enrolment as Manager all included (that acting user is the
 * seeding request's admin, which is why every scratch context counts admin
 * among its managers — recorded parity fact).
 *
 * parseParams() is read-only (spec + service validation); create() mutates.
 */

namespace PKP\testing;

use APP\core\Application;
use PKP\context\Context;
use PKP\services\interfaces\EntityWriteInterface;

class ContextFactory
{
    /**
     * @param Spec $spec context keys: path*, name, acronym, description,
     *   primaryLocale, supportedLocales, contactName, contactEmail, enabled
     */
    public function parseParams(Spec $spec): array
    {
        $request = Application::get()->getRequest();
        $site = $request->getSite();
        $primaryLocale = (string) $spec->get('primaryLocale', $site->getPrimaryLocale());
        $supportedLocales = (array) $spec->get('supportedLocales', [$primaryLocale]);
        $path = (string) $spec->require('path');

        $params = array_filter([
            'urlPath' => $path,
            'name' => $spec->localized('name', $primaryLocale, ['en' => "Context {$path}"]),
            'acronym' => $spec->localized('acronym', $primaryLocale),
            'description' => $spec->localized('description', $primaryLocale),
            'primaryLocale' => $primaryLocale,
            'supportedLocales' => $supportedLocales,
            'contactName' => (string) $spec->get('contactName', 'Site Admin'),
            'contactEmail' => (string) $spec->get('contactEmail', 'admin@mail.test'),
        ], fn ($value) => $value !== null);
        $params['enabled'] = (bool) $spec->get('enabled', true);

        $contextService = app()->get('context'); /** @var \PKP\services\PKPContextService $contextService */
        $errors = $contextService->validate(
            EntityWriteInterface::VALIDATE_ACTION_ADD,
            $params,
            $site->getSupportedLocales(),
            $primaryLocale
        );
        if (!empty($errors)) {
            throw new SpecException(
                $spec->path === '' ? 'context' : $spec->path,
                'Invalid context spec: ' . json_encode($errors)
            );
        }
        return $params;
    }

    public function create(array $params): Context
    {
        $request = Application::get()->getRequest();
        $contextService = app()->get('context'); /** @var \PKP\services\PKPContextService $contextService */
        $context = Application::getContextDAO()->newDataObject();
        $context->setAllData($params);
        return $contextService->add($context, $request);
    }
}

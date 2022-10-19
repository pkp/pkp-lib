<?php

/**
 * @file pages/management/SettingsHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsHandler
 * @ingroup pages_management
 *
 * @brief Handle requests for settings pages.
 */

namespace APP\pages\management;

use APP\components\forms\context\EmailSetupForm;
use APP\template\TemplateManager;
use PKP\components\forms\context\PKPInformationForm;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\mail\Mailable;
use PKP\pages\management\ManagementHandler;
use PKP\plugins\Hook;
use PKP\security\Role;

class SettingsHandler extends ManagementHandler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN],
            [
                'access',
            ]
        );
        $this->addRoleAssignment(
            Role::ROLE_ID_MANAGER,
            [
                'settings',
            ]
        );
    }

    public function workflow($args, $request)
    {
        parent::workflow($args, $request);

        // Add the author screening rules to the workflow settings submission tab
        Hook::add('Template::Settings::workflow::submission', function (string $hookName, array &$params) use ($request) {
            $output = & $params[2]; /** @var string $output */

            $rules = [];

            Hook::call('Settings::Workflow::listScreeningPlugins', [&$rules]);

            if (empty($rules)) {
                return false;
            }

            $rows = [];
            foreach ((array) $rules as $rule) {
                $rows[] = '<tr><td>' . $rule . '</td></tr>';
            }

            $output .= '
                <tab id="authorScreening" label="' . __('manager.setup.authorScreening') . '">
                    <table class="pkpTable">
                        ' . join('', $rows) . '
                    </table>
                </tab>
            ';

            return false;
        });

        TemplateManager::getManager($request)->display('management/workflow.tpl');
    }

    /**
     * Add OPS distribution settings
     *
     * @param array $args
     * @param \APP\core\Request $request
     */
    public function distribution($args, $request)
    {
        parent::distribution($args, $request);
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $accessForm = new \APP\components\forms\context\AccessForm($apiUrl, $locales, $context);

        // Add forms to the existing settings data
        $components = $templateMgr->getState('components');
        $components[$accessForm->id] = $accessForm->getConfig();
        $templateMgr->setState(['components' => $components]);

        $templateMgr->display('management/distribution.tpl');
    }

    protected function getEmailGroupFilters(): array
    {
        return [
            Mailable::GROUP_SUBMISSION => __('submission.submission'),
            Mailable::GROUP_PRODUCTION => __('submission.production'),
            Mailable::GROUP_OTHER => __('common.other'),
        ];
    }

    protected function getEmailFromFilters(): array
    {
        return [
            Role::ROLE_ID_SUB_EDITOR => __('default.groups.name.sectionEditor'),
            Role::ROLE_ID_READER => __('user.role.reader'),
            Mailable::FROM_SYSTEM => __('mailable.system'),
        ];
    }

    protected function getEmailToFilters(): array
    {
        return [
            Role::ROLE_ID_SUB_EDITOR => __('default.groups.name.sectionEditor'),
            Role::ROLE_ID_AUTHOR => __('user.role.author'),
            Role::ROLE_ID_READER => __('user.role.reader'),
        ];
    }

    protected function getEmailSetupForm(string $contextApiUrl, array $locales, Context $context): EmailSetupForm
    {
        return new EmailSetupForm($contextApiUrl, $locales, $context);
    }

    protected function getInformationForm(string $contextApiUrl, array $locales, Context $context, string $publicFileApiUrl): ?PKPInformationForm
    {
        return null;
    }
}

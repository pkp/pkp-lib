<?php

/**
 * @file plugins/generic/webFeed/WebFeedSettingsForm.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WebFeedSettingsForm
 * @brief Form for managers to modify web feeds plugin settings
 */

namespace APP\plugins\generic\webFeed;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;

class WebFeedSettingsForm extends Form
{
    private int $contextId;

    private WebFeedPlugin $plugin;

    /**
     * Constructor
     */
    public function __construct(WebFeedPlugin $plugin, int $contextId)
    {
        $this->contextId = $contextId;
        $this->plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData(): void
    {
        $contextId = $this->contextId;
        $plugin = $this->plugin;

        $this->setData('displayPage', $plugin->getSetting($contextId, 'displayPage'));
        $this->setData('recentItems', $plugin->getSetting($contextId, 'recentItems'));
        $this->setData('includeIdentifiers', $plugin->getSetting($contextId, 'includeIdentifiers'));
        parent::initData();
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData(): void
    {
        $this->readUserVars(['displayPage', 'recentItems', 'includeIdentifiers']);

        // check that recent items value is a positive integer
        if ((int) $this->getData('recentItems') <= 0) {
            $this->setData('recentItems', '');
        }

        $this->addCheck(new FormValidator($this, 'recentItems', 'required', 'plugins.generic.webfeed.settings.recentItemsRequired'));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false): string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $plugin = $this->plugin;
        $contextId = $this->contextId;

        $plugin->updateSetting($contextId, 'displayPage', $this->getData('displayPage'), 'string');
        $plugin->updateSetting($contextId, 'recentItems', $this->getData('recentItems'), 'int');
        $plugin->updateSetting($contextId, 'includeIdentifiers', $this->getData('includeIdentifiers'), 'bool');

        parent::execute(...$functionArgs);
    }
}

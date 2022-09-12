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

class WebFeedSettingsForm extends Form
{
    /** Associated context ID */
    private int $_contextId;

    /** Web feed plugin */
    private WebFeedPlugin $_plugin;

    public function __construct(WebFeedPlugin $plugin, int $contextId)
    {
        $this->_contextId = $contextId;
        $this->_plugin = $plugin;

        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Initialize form data.
     */
    public function initData(): void
    {
        $contextId = $this->_contextId;
        $plugin = $this->_plugin;

        $this->setData('displayPage', $plugin->getSetting($contextId, 'displayPage'));
        $this->setData('recentItems', $plugin->getSetting($contextId, 'recentItems'));
    }

    /**
     * Assign form data to user-submitted data.
     */
    public function readInputData(): void
    {
        $this->readUserVars(['displayPage', 'recentItems']);

        // check that recent items value is a positive integer
        if ((int) $this->getData('recentItems') <= 0) {
            $this->setData('recentItems', '');
        }

        $this->addCheck(new FormValidator($this, 'recentItems', 'required', 'plugins.generic.webfeed.settings.recentItemsRequired'));
    }

    /**
     * Fetch the form.
     *
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false): string
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->_plugin->getName());
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $plugin = $this->_plugin;
        $contextId = $this->_contextId;

        $plugin->updateSetting($contextId, 'displayPage', $this->getData('displayPage'));
        $plugin->updateSetting($contextId, 'recentItems', $this->getData('recentItems'));

        parent::execute(...$functionArgs);
    }
}

<?php

/**
 * @file controllers/tab/pubIds/form/PublicIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicIdentifiersForm
 * @ingroup controllers_tab_pubIds_form
 *
 * @brief Displays a pub ids form.
 */

import('lib.pkp.controllers.tab.pubIds.form.PKPPublicIdentifiersForm');

use APP\template\TemplateManager;

class PublicIdentifiersForm extends PKPPublicIdentifiersForm
{
    /**
     * Constructor.
     *
     * @param object $pubObject
     * @param int $stageId
     * @param array $formParams
     */
    public function __construct($pubObject, $stageId = null, $formParams = null)
    {
        parent::__construct($pubObject, $stageId, $formParams);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $enablePublisherId = $request->getContext()->getData('enablePublisherId');
        $templateMgr->assign([
            'enablePublisherId' => (is_a($this->getPubObject(), 'PreprintGalley') && in_array('galley', $enablePublisherId))
        ]);

        return parent::fetch($request, $template, $display);
    }
}

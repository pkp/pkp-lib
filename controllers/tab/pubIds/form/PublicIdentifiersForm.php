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

namespace APP\controllers\tab\pubIds\form;

use PKP\controllers\tab\pubIds\form\PKPPublicIdentifiersForm;
use APP\template\TemplateManager;
use PKP\galley\Galley;

class PublicIdentifiersForm extends PKPPublicIdentifiersForm
{
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
            'enablePublisherId' => ($this->getPubObject() instanceof Galley && in_array('galley', $enablePublisherId))
        ]);

        return parent::fetch($request, $template, $display);
    }
}

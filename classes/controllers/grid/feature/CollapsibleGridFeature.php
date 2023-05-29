<?php

/**
 * @file classes/controllers/grid/feature/CollapsibleGridFeature.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CollapsibleGridFeature
 *
 * @ingroup controllers_grid_feature
 *
 * @brief Add collapse and expand functionality to grids.
 *
 */

namespace PKP\controllers\grid\feature;

use APP\template\TemplateManager;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\NullAction;

class CollapsibleGridFeature extends GridFeature
{
    /**
     * @copydoc GridFeature::GridFeature()
     * Constructor.
     */
    public function __construct($id = 'collapsible')
    {
        parent::__construct($id);
    }

    /**
     * @copyDoc GridFeature::getJSClass()
     */
    public function getJSClass()
    {
        return '$.pkp.classes.features.CollapsibleGridFeature';
    }

    /**
     * @copyDoc GridFeature::fetchUIElement()
     */
    public function fetchUIElements($request, $grid)
    {
        $controlLink = new LinkAction(
            'expandGridControlLink',
            new NullAction(),
            null,
            'expand_all'
        );

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('controlLink', $controlLink);
        $markup = $templateMgr->fetch('controllers/grid/feature/collapsibleGridFeature.tpl');

        return ['collapsibleLink' => $markup];
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\controllers\grid\feature\CollapsibleGridFeature', '\CollapsibleGridFeature');
}

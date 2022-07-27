<?php

/**
 * @file classes/plugins/ReportPlugin.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReportPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for report plugins
 */

namespace PKP\plugins;

use PKP\core\PKPApplication;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;

abstract class ReportPlugin extends Plugin
{
    //
    // Public methods.
    //
    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $dispatcher = $request->getDispatcher();
        return array_merge(
            $this->getEnabled() ? [
                new LinkAction(
                    'settings',
                    new RedirectAction($dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'stats',
                        'reports',
                        'report',
                        ['pluginName' => $this->getName()]
                    )),
                    __('manager.statistics.reports'),
                    null
                )
            ] : [],
            parent::getActions($request, $actionArgs)
        );
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\plugins\ReportPlugin', '\ReportPlugin');
}

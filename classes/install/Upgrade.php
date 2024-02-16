<?php

/**
 * @file classes/install/Upgrade.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Upgrade
 *
 * @ingroup install
 *
 * @brief Perform system upgrade.
 */

namespace APP\install;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\install\Installer;

class Upgrade extends Installer
{
    protected $appEmailTemplateVariableNames = [
        'contextName' => 'serverName',
        'contextUrl' => 'serverUrl',
        'contextSignature' => 'serverSignature',
    ];

    /**
     * Constructor.
     *
     * @param array $params upgrade parameters
     */
    public function __construct($params, $installFile = 'upgrade.xml', $isPlugin = false)
    {
        parent::__construct($installFile, $params, $isPlugin);
    }


    /**
     * Returns true iff this is an upgrade process.
     *
     * @return bool
     */
    public function isUpgrade()
    {
        return true;
    }

    //
    // Upgrade actions
    //

    /**
     * Rebuild the search index.
     *
     * @return bool
     */
    public function rebuildSearchIndex()
    {
        $submissionSearchIndex = Application::getSubmissionSearchIndex();
        $submissionSearchIndex->rebuildIndex();
        return true;
    }

    /**
     * Clear the CSS cache files (needed when changing LESS files)
     *
     * @return bool
     */
    public function clearCssCache()
    {
        $request = Application::get()->getRequest();
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->clearCssCache();
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\install\Upgrade', '\Upgrade');
}

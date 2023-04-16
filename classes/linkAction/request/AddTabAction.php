<?php
/**
 * @file classes/linkAction/request/AddTabAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddTabAction
 *
 * @ingroup linkAction_request
 *
 * @brief This action triggers a containing tabset to add a new tab.
 */

namespace PKP\linkAction\request;

class AddTabAction extends EventAction
{
    /**
     * Constructor
     *
     * @param string $targetSelector Selector for target to receive event.
     */
    public function __construct($targetSelector, $url, $title)
    {
        parent::__construct($targetSelector, 'addTab', [
            'url' => $url,
            'title' => $title,
        ]);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\AddTabAction', '\AddTabAction');
}

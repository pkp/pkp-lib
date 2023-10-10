<?php
/**
 * @file classes/linkAction/request/NullAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class NullAction
 *
 * @ingroup linkAction_request
 *
 * @brief This action does nothing.
 */

namespace PKP\linkAction\request;

class NullAction extends LinkActionRequest
{
    //
    // Overridden protected methods from LinkActionRequest
    //
    /**
     * @see LinkActionRequest::getJSLinkActionRequest()
     */
    public function getJSLinkActionRequest()
    {
        return '$.pkp.classes.linkAction.NullAction';
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\linkAction\request\NullAction', '\NullAction');
}

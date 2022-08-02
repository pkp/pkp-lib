<?php

/**
 * @file controllers/modals/publish/OPSPublishHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OPSPublishHandler
 * @ingroup controllers_modals_publish
 *
 * @brief A handler to load final publishing confirmation checks
 */

namespace APP\controllers\modals\publish;

use PKP\controllers\modals\publish\PublishHandler;
use PKP\security\Role;

class OPSPublishHandler extends PublishHandler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR],
            ['publish']
        );
        parent::__construct();
    }
}

<?php

/**
 * @file classes/user/UserAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAction
 * @ingroup user
 *
 * @see User
 *
 * @brief UserAction class.
 */

namespace APP\user;

use PKP\user\PKPUserAction;

class UserAction extends PKPUserAction
{
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\user\UserAction', '\UserAction');
}

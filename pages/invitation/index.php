<?php

/**
 * @file pages/invitation/index.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_invitation
 *
 * @brief Handle requests for the invitation accept and decline URLs
 *
 */

switch ($op) {
    case 'decline':
    case 'accept':
        return new PKP\pages\invitation\InvitationHandler();
}

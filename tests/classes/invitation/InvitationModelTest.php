<?php

/**
 * @file tests/classes/invitation/InvitationModelTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationModelTest
 *
 * @see \PKP\invitation\models\InvitationModel
 *
 * @brief Tests for the InvitationModel class.
 */

namespace PKP\tests\classes\invitation;

use PKP\invitation\models\InvitationModel;
use PKP\tests\PKPTestCase;

class InvitationModelTest extends PKPTestCase
{
    public function testIdIsNullBeforeTheInvitationIsSaved(): void
    {
        $invitation = new InvitationModel();

        $this->assertNull($invitation->id);
    }
}

<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7265_EditorialDecisions.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7265_EditorialDecisions
 *
 * @brief Database migrations for editorial decision refactor.
 */

namespace APP\migration\upgrade\v3_4_0;

class I7265_EditorialDecisions extends \PKP\migration\upgrade\v3_4_0\I7265_EditorialDecisions
{
    protected function getContextTable(): string
    {
        return 'servers';
    }

    protected function getContextSettingsTable(): string
    {
        return 'server_settings';
    }

    protected function getContextIdColumn(): string
    {
        return 'server_id';
    }
}

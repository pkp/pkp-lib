<?php


/**
 * @file classes/migration/upgrade/v3_4_0/I8933_EventLogLocalized.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8933_EventLogLocalized.php
 *
 * @brief Extends the event log migration with the correct table names for OPS.
 */

namespace APP\migration\upgrade\v3_4_0;

class I8933_EventLogLocalized extends \PKP\migration\upgrade\v3_4_0\I8933_EventLogLocalized
{
    protected function getContextTable(): string
    {
        return 'servers';
    }

    protected function getContextIdColumn(): string
    {
        return 'server_id';
    }
}

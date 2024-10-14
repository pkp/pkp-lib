<?php
/**
 * @file classes/ror/Ror.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Ror
 *
 * @ingroup ror
 *
 * @see DAO
 *
 * @brief Basic class describing a ror.
 */

namespace PKP\ror;

use PKP\core\DataObject;

class Ror extends DataObject
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;
}

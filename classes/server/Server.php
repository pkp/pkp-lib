<?php

/**
 * @defgroup server Server
 * Extensions to the pkp-lib "context" concept to specialize it for use in OPS
 * in representing Server objects and server-specific concerns.
 */

/**
 * @file classes/server/Server.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Server
 *
 * @ingroup server
 *
 * @see ServerDAO
 *
 * @brief Describes basic server properties.
 */

namespace APP\server;

use APP\core\Application;
use PKP\context\Context;
use PKP\db\DAORegistry;

class Server extends Context
{
    public const PUBLISHING_MODE_OPEN = 0;
    public const PUBLISHING_MODE_NONE = 2;

    //
    // Get/set methods
    //

    /**
     * Get the association type for this context.
     *
     * @return int
     */
    public function getAssocType()
    {
        return Application::ASSOC_TYPE_SERVER;
    }

    /**
     * @copydoc DataObject::getDAO()
     */
    public function getDAO()
    {
        return DAORegistry::getDAO('ServerDAO');
    }
}

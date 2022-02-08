<?php

declare(strict_types=1);

/**
 * @file classes/core/Proxies/Entities/TcpProxy.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TcpProxy
 * @ingroup classes_core_proxies_entities
 *
 * @brief Implementation for TCP Proxy entity
 */

namespace PKP\core\Proxies\Entities;

use PKP\core\Proxies\Abstracts\ProxyEntity;

final class TcpProxy extends ProxyEntity
{
    protected $auth = null;
    protected $proxy = null;
}

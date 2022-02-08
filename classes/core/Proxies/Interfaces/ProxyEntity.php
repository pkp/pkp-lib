<?php

declare(strict_types=1);

/**
 * @file classes/core/Proxies/Interfaces/ProxyEntity.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProxyEntity
 * @ingroup Interfaces
 *
 * @brief Interface for Proxy entities classes
 */

namespace PKP\core\Proxies\Interfaces;

interface ProxyEntity
{
    public function setAuth(?string $auth): void;
    public function getAuth(): ?string;
    public function setProxy(?string $proxy): void;
    public function getProxy(): ?string;
    public function isEmpty(): bool;
}

<?php

declare(strict_types=1);

/**
 * @file classes/core/Proxies/Abstracts/ProxyEntity.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Entity
 * @ingroup classes_core_proxies_abstracts
 *
 * @brief Abstract Class for Proxy entities
 */

namespace PKP\core\Proxies\Abstracts;

use PKP\core\Proxies\Interfaces\ProxyEntity as ProxyEntityInterface;
use PKP\Support\Entities\Hydratable;

abstract class ProxyEntity extends Hydratable implements ProxyEntityInterface
{
    public function setAuth(?string $auth): void
    {
        $this->auth = $auth;
    }

    public function getAuth(): ?string
    {
        return $this->auth;
    }

    public function setProxy(?string $proxy): void
    {
        $this->proxy = $proxy;
    }

    public function getProxy(): ?string
    {
        return $this->proxy;
    }

    public function isEmpty(): bool
    {
        return $this->getProxy() === null;
    }

    public function toArray(): array
    {
        return [
            'proxy' => $this->getProxy(),
            'auth' => $this->getAuth(),
        ];
    }
}

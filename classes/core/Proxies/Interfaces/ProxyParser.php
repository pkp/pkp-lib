<?php

declare(strict_types=1);

/**
 * @file classes/core/Proxies/Interfaces/ProxyParser.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProxyParser
 * @ingroup Interfaces
 *
 * @brief Interface for Proxy parsers classes
 */

namespace PKP\core\Proxies\Interfaces;

interface ProxyParser
{
    public function getAuth(): ?string;
    public function getProxy(): ?string;
    public function getProxyEntity(): ?ProxyEntity;
    public function isEmpty(): bool;
    public function parseAuth(array $parsed): ?string;
    public function parseFQDN(string $fqdn): void;
    public function parseHost(array $parsed): ?string;
    public function setProxyEntity(array $properties = []): void;
}

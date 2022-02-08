<?php

declare(strict_types=1);

/**
 * @file classes/core/Proxies/Abstracts/ProxyParser.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ProxyParser
 * @ingroup Abstracts
 *
 * @brief Abstract classe for Proxies Parsers
 */

namespace PKP\core\Proxies\Abstracts;

use PKP\core\Proxies\Interfaces\ProxyParser as ProxyInterface;

abstract class ProxyParser implements ProxyInterface
{
    public function isEmpty(): bool
    {
        return !$this->getProxyEntity();
    }

    public function parseFQDN(string $fqdn): void
    {
        $parsed = array_filter(parse_url($fqdn));

        /**
         * Was not possible to parse the proxy's FQDN (@see https://en.wikipedia.org/wiki/Fully_qualified_domain_name) or it is not a HTTP scheme, so let's return it as-is.
         */
        if (!$parsed ||
            (isset($parsed['scheme']) &&
            $parsed['scheme'] !== 'http' &&
            $parsed['scheme'] !== 'https')
        ) {
            $this->setProxyEntity([
                'proxy' => $fqdn,
            ]);

            return;
        }

        $this->setProxyEntity([
            'auth' => $this->parseAuth($parsed),
            'proxy' => $this->parseHost($parsed),
        ]);

        return;
    }

    public function parseAuth(array $parsed = []): ?string
    {
        if (!$parsed['user'] || !$parsed['pass']) {
            return null;
        }

        return base64_encode("{$parsed['user']}:{$parsed['pass']}");
    }

    public function parseHost(array $parsed = []): ?string
    {
        if (!$parsed['host'] || !$parsed['port']) {
            return null;
        }

        return "tcp://{$parsed['host']}:{$parsed['port']}";
    }

    public function getAuth(): ?string
    {
        return $this->getProxyEntity()->getAuth();
    }

    public function getProxy(): ?string
    {
        return $this->getProxyEntity()->getProxy();
    }
}

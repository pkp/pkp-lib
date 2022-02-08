<?php

declare(strict_types=1);

namespace PKP\core\Proxies;

use PKP\core\Proxies\Abstracts\ProxyParser;
use PKP\core\Proxies\Entities\TcpProxy;
use PKP\core\Proxies\Interfaces\ProxyEntity as InterfacesProxyEntity;

final class Proxy extends ProxyParser
{
    protected $auth = null;
    protected $proxyEntity = null;

    public function setProxyEntity(array $properties = []): void
    {
        $this->proxyEntity = new TcpProxy($properties);
    }

    public function getProxyEntity(): ?InterfacesProxyEntity
    {
        return $this->proxyEntity;
    }
}

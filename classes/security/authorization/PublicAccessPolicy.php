<?php

namespace PKP\security\authorization;

class PublicAccessPolicy extends AuthorizationPolicy
{
    public function effect(): int
    {
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

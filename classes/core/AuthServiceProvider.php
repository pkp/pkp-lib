<?php

namespace PKP\core;

use Illuminate\Support\Facades\Auth;
use PKP\user\PKPUserProvider;

class AuthServiceProvider extends \Illuminate\Auth\AuthServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot()
    {
        $this->registerPolicies();

        Auth::provider('pkp_user_provider', function ($app, array $config) {
            return new PKPUserProvider();
        });

        parent::boot();
    }
}

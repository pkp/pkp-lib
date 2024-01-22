<?php

namespace PKP\core;

use APP\core\Application;
use APP\facades\Repo;
use Throwable;
use PKP\security\Validation;
use InvalidArgumentException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;

class PKPSessionGuard extends SessionGuard
{
    /**
     * The currently authenticated user.
     *
     * @var \Illuminate\Contracts\Auth\Authenticatable|\PKP\user\User|null
     */
    protected $user;

    public function updateUser(\Illuminate\Contracts\Auth\Authenticatable|\PKP\user\User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|\PKP\user\User|null
     */
    public function user()
    {
        $this->user = parent::user();

        if ($this->user) {
            return $this->user;
        }

        $illuminateRequest = app(\Illuminate\Http\Request::class); /** @var \Illuminate\Http\Request $illuminateRequest */
        
        $sessionRow = DB::table('sessions')
            ->where('id', $illuminateRequest->getSession()->getId())
            ->first();
            
        if (!$sessionRow) {
            return $this->user;
        }

        $sessionPayload = $sessionRow->payload;

        if (!$sessionPayload) {
            return $this->user;
        }

        $data = base64_decode($sessionPayload);
        $data = isValidJson($data) ? json_decode($data, true) : unserialize($data);

        if (is_array($data) && isset($data['user_id'])) {
            $this->user = $this->provider->retrieveById($data['user_id']);
        }

        // if ($this->user) {
        //     $this->updateSession($this->user->getAuthIdentifier());

        //     $this->fireLoginEvent($this->user, true);
        // }
        
        return $this->user;
    }

    /**
     * Rehash the current user's password.
     *
     * @param  string  $password
     * @param  string  $attribute
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     *
     * @throws \InvalidArgumentException
     */
    protected function rehashUserPassword($password, $attribute)
    {
        $rehash = null;

        if (! Validation::verifyPassword($this->user->getUsername(), $password, $this->user->getPassword(), $rehash)) {
            throw new InvalidArgumentException('The given password does not match the current password.');
        }

        return tap($this->user, function(&$user) use ($password) {
            $rehash ??= Validation::encryptCredentials($user->getUsername(), $password);
            $user->setPassword($rehash);
            
            $session = Application::get()->getRequest()->getSession();
            $session->put([
                'password_hash_' . app()->get('auth')->getDefaultDriver() => $rehash,
            ]);
            $session->save();
            $session->start();

            Repo::user()->edit($user);
        });
    }
}
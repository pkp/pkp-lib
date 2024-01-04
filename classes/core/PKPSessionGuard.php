<?php

namespace PKP\core;

use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\DB;
use Throwable;

class PKPSessionGuard extends SessionGuard
{
    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
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
}
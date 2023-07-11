<?php

declare(strict_types=1);

/**
 * @file classes/invitation/models/Invitation.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Invitation
 *
 * @brief Laravel Eloquent model for Invitation (access_keys table)
 */

namespace PKP\invitation\models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\InteractsWithTime;
use PKP\invitation\invitations\enums\InvitationStatus;
use PKP\invitation\traits\Attributes;
use PKP\job\casts\DatetimeToInt;

class Invitation extends Model
{
    use Attributes;
    use InteractsWithTime;

    /**
     * Model's database table
     *
     * @var string
     */
    protected $table = 'access_keys';

    /**
     * Model's primary key
     *
     * @var string
     */
    protected $primaryKey = 'access_key_id';

    /**
     * Model's timestamp fields
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The attributes that are not mass assignable.
     *
     * @var string[]|bool
     */
    protected $guarded = [];

    /**
     * Casting attributes to their native types
     *
     * @var string[]
     */
    protected $casts = [
        'context' => 'string',
        'key_hash' => 'string',
        'payload' => 'array',
        'user_id' => 'int',
        'assoc_id' => 'int',
        'expiry_date' => 'datetime',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
        'status' => 'int',
        'context_id' => 'int',
        'type' => 'string',
        'invitation_email' => 'string',
    ];

    /**
     * Add a local scope to get invitations with certain key_hash
     */
    public function scopeCertainKeyhash(Builder $query, string $keyHash): Builder
    {
        return $query->where('key_hash', '=', $keyHash);
    }

    /**
     * Add a local scope to get invitations still unhandled
     */
    public function scopeNotHandled(Builder $query): Builder
    {
        return $query->where('status', '=', InvitationStatus::PENDING);
    }

    /**
     * Add a local scope to get invitations that are of certain invitation type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', '=', $type);
    }

    /**
     * Add a local scope to get invitations that are of certain assoc_id
     */
    public function scopeByAssocId(Builder $query, string $assoc_id): Builder
    {
        return $query->where('assoc_id', '=', $assoc_id);
    }

    /**
     * Add a local scope to get invitations that are of certain email
     */
    public function scopeByEmail(Builder $query, string $email): Builder
    {
        return $query->where('invitation_email', '=', $email);
    }

    /**
     * Add a local scope to get invitations that are of certain email
     */
    public function scopeByContextId(Builder $query, string $contextId): Builder
    {
        return $query->where('context_id', '=', $contextId);
    }

    /**
     * Mark invitation as accepted
     */
    public function markInvitationAsAccepted(): void
    {
        $this->update([
            'updated_at' => $this->currentTime(),
            'status' => InvitationStatus::ACCEPTED
        ]);
    }

    /**
     * Mark invitation as declined
     */
    public function markInvitationAsDeclined(): void
    {
        $this->update([
            'updated_at' => $this->currentTime(),
            'status' => InvitationStatus::DECLINED
        ]);
    }

    /**
     * Mark invitation as expired
     */
    public function markInvitationAsExpired(): void
    {
        $this->update([
            'updated_at' => $this->currentTime(),
            'status' => InvitationStatus::EXPIRED
        ]);
    }

    /**
     * Mark invitation as canceled
     */
    public function markInvitationAsCanceled(): void
    {
        $this->update([
            'updated_at' => $this->currentTime(),
            'status' => InvitationStatus::CANCELLED
        ]);
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        $expiryDate = $this->expiryDate;
        $currentDateTime = Carbon::now();

        if ($expiryDate > $currentDateTime) {
            return false;
        }

        return false;
    }
}

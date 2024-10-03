<?php

/**
 * @file classes/invitation/models/Invitation.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Invitation
 *
 * @brief Laravel Eloquent model for Invitation (access_keys table)
 */

namespace PKP\invitation\models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\InteractsWithTime;
use PKP\invitation\core\enums\InvitationStatus;
use Eloquence\Behaviours\HasCamelCasing;

class InvitationModel extends Model
{
    use InteractsWithTime;
    use HasCamelCasing;

    /**
     * Model's database table
     *
     * @var string
     */
    protected $table = 'invitations';

    /**
     * Model's primary key
     *
     * @var string
     */
    protected $primaryKey = 'invitation_id';

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
        'keyHash' => 'string',
        'payload' => 'array',
        'userId' => 'int',
        'expiryDate' => 'datetime',
        'updatedAt' => 'datetime',
        'createdAt' => 'datetime',
        'status' => 'string',
        'contextId' => 'int',
        'type' => 'string',
        'email' => 'string',
        'id' => 'int',
        'inviterId' => 'int',
    ];

    protected $visible = [
        'id',
        'status',
        'createdAt',
        'updatedAt',
        'userId',
        'contextId',
        'expiryDate',
        'email',
        'inviterId'
    ];


    public function id(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['invitation_id'],
            set: fn ($value) => ['invitation_id' => $value]
        );
    }

    public function status(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => InvitationStatus::from($attributes['status']),
            set: fn ($value) => ['status' => $value->value]
        );
    }

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
     * Add a local scope to get invitations by status
     */
    public function scopeByStatus(Builder $query, InvitationStatus $status): Builder
    {
        return $query->where('status', '=', $status->value);
    }

    /**
     * Add a local scope to get invitations that are of certain invitation type
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', '=', $type);
    }

    /**
     * Add a local scope to get invitations that are of certain user_id
     */
    public function scopeByUserId(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId !== null, function ($query) use ($userId) {
                return $query->where('user_id', $userId);
            }, function ($query) {
                return $query->whereNull('user_id');
            });
    }

    /**
     * Add a local scope to get invitations that are of certain email
     */
    public function scopeByEmail(Builder $query, ?string $email): Builder
    {
        return $query->when($email !== null, function ($query) use ($email) {
                return $query->where('email', $email);
            }, function ($query) {
                return $query->whereNull('email');
            });
    }

    /**
     * Add a local scope to get invitations that are of certain context id
     */
    public function scopeByContextId(Builder $query, ?int $contextId): Builder
    {
        return $query->when($contextId !== null, function ($query) use ($contextId) {
                return $query->where('context_id', $contextId);
            }, function ($query) {
                return $query->whereNull('context_id');
            });
    }

    /**
     * Add a local scope to get invitations that are expired
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<', Carbon::now())
            ->orWhereNull('expiry_date');
    }

    /**
     * Add a local scope to get invitations that are expired
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '>=', Carbon::now())
            ->orWhereNull('expiry_date');
    }

    /**
     * Scope a query to only include invitations that are not expired and not handled.
     */
    public function scopeStillActive(Builder $query): Builder
    {
        // Apply the NotExpired scope
        $query->notExpired();

        // Apply the NotHandled scope
        return $query->notHandled();
    }

    public function markAs(InvitationStatus $status): bool
    {
        $this->status = $status;
        $this->updatedAt = Carbon::now();

        return $this->save();
    }

    /**
     * Mark all invitations with a given status.
     *
     */
    public static function markAllAs(InvitationStatus $status, Collection $ids): int
    {
        $query = static::query();

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        }

        return $query->update([
            'status' => $status->value,
            'updated_at' => Carbon::now()
        ]);
    }

    public function scopeById(Builder $query, int $id)
    {
        return $query->where('invitation_id', $id);
    }

    public function scopeByNotId(Builder $query, int $id)
    {
        return $query->where('invitation_id', '!=', $id);
    }

    // Custom toArray method to ensure serialization of attributes
    public function toArray()
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'userId' => $this->userId,
            'contextId' => $this->contextId,
            'expiryDate' => $this->expiryDate,
            'email' => $this->email,
            'inviterId' => $this->inviterId,
        ];
    }
}

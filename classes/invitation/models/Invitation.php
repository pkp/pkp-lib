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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\InteractsWithTime;
use PKP\invitation\invitations\enums\InvitationStatus;

class Invitation extends Model
{
    use InteractsWithTime;

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
        'assocId' => 'int',
        'expiryDate' => 'datetime',
        'updatedAt' => 'datetime',
        'createdAt' => 'datetime',
        'status' => 'string',
        'contextId' => 'int',
        'className' => 'string',
        'email' => 'string',
        'id' => 'int',
    ];

    protected $hidden = [
        'key_hash',
        'user_id',
        'assoc_id',
        'expiry_date',
        'updated_at',
        'created_at',
        'context_id',
        'class_name',
        'invitation_id',
    ];

    public function keyHash(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['key_hash'],
            set: fn ($value) => ['key_hash' => $value]
        );
    }

    public function userId(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['user_id'],
            set: fn ($value) => ['user_id' => $value]
        );
    }

    public function assocId(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['assoc_id'],
            set: fn ($value) => ['assoc_id' => $value]
        );
    }

    public function expiryDate(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => new Carbon($attributes['expiry_date']),
            set: fn ($value) => ['expiry_date' => $value]
        );
    }

    public function updatedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => new Carbon($attributes['updated_at']),
            set: fn ($value) => ['updated_at' => $value]
        );
    }

    public function createdAt(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => new Carbon($attributes['created_at']),
            set: fn ($value) => ['created_at' => $value]
        );
    }

    public function contextId(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['context_id'],
            set: fn ($value) => ['context_id' => $value]
        );
    }
    public function className(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['class_name'],
            set: fn ($value) => ['class_name' => $value]
        );
    }
    public function id(): Attribute
    {
        return Attribute::make(
            get: fn ($user, $attributes) => $attributes['invitation_id'],
            set: fn ($value) => ['invitation_id' => $value]
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
    public function scopeByClassName(Builder $query, string $className): Builder
    {
        return $query->where('class_name', '=', $className);
    }

    /**
     * Add a local scope to get invitations that are of certain assoc_id
     */
    public function scopeByAssocId(Builder $query, ?int $assocId): Builder
    {
        return $query->when($assocId, function ($query, $assocId) {
            return $query->where('assoc_id', '=', $assocId);
        })->orWhereNull('assoc_id');
    }

    /**
     * Add a local scope to get invitations that are of certain user_id
     */
    public function scopeByUserId(Builder $query, ?int $userId): Builder
    {
        return $query->when($userId, function ($query, $userId) {
            return $query->where('user_id', '=', $userId);
        })->orWhereNull('user_id');
    }

    /**
     * Add a local scope to get invitations that are of certain email
     */
    public function scopeByEmail(Builder $query, ?string $email): Builder
    {
        return $query->when($email, function ($query, $email) {
            return $query->where('email', '=', $email);
        })->orWhereNull('email');
    }

    /**
     * Add a local scope to get invitations that are of certain context id
     */
    public function scopeByContextId(Builder $query, ?int $contextId): Builder
    {
        return $query->when($contextId, function ($query, $contextId) {
            return $query->where('context_id', '=', $contextId);
        })->orWhereNull('context_id');
    }

    /**
     * Add a local scope to get invitations that are expired
     */
    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', Carbon::now());
    }

    /**
     * Add a local scope to get invitations that are expired
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expiry_date', '>=', Carbon::now());
    }

    /**
     * Mark invitation as a certain invitation status
     */
    public function scopeMarkAs($query, InvitationStatus $status): bool
    {
        return $query->update([
            'updated_at' => Carbon::now(),
            'status' => $status->value
        ]);
    }
}

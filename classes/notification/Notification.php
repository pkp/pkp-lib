<?php

/**
 * @file classes/notification/Notification.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Notification
 *
 * @brief Basic class describing a notification
 */

namespace PKP\notification;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasCamelCasing;

    // Notification levels.  Determines notification behavior
    public const NOTIFICATION_LEVEL_TRIVIAL = 1;
    public const NOTIFICATION_LEVEL_NORMAL = 2;
    public const NOTIFICATION_LEVEL_TASK = 3;

    // Notification types.  Determines what text and URL to display for notification
    public const NOTIFICATION_TYPE_SUCCESS = 0x0000001;
    public const NOTIFICATION_TYPE_WARNING = 0x0000002;
    public const NOTIFICATION_TYPE_ERROR = 0x0000003;
    public const NOTIFICATION_TYPE_FORBIDDEN = 0x0000004;
    public const NOTIFICATION_TYPE_INFORMATION = 0x0000005;
    public const NOTIFICATION_TYPE_HELP = 0x0000006;
    public const NOTIFICATION_TYPE_FORM_ERROR = 0x0000007;
    public const NOTIFICATION_TYPE_NEW_ANNOUNCEMENT = 0x0000008;

    // define('NOTIFICATION_TYPE_LOCALE_INSTALLED',			0x4000001); // DEPRECATED; DO NOT USE

    public const NOTIFICATION_TYPE_PLUGIN_ENABLED = 0x5000001;
    public const NOTIFICATION_TYPE_PLUGIN_DISABLED = 0x5000002;

    public const NOTIFICATION_TYPE_PLUGIN_BASE = 0x6000001;

    // Workflow-level notifications
    public const NOTIFICATION_TYPE_SUBMISSION_SUBMITTED = 0x1000001;
    // public const NOTIFICATION_TYPE_METADATA_MODIFIED = 0x1000002; // DEPRECATED; DO NOT USE

    public const NOTIFICATION_TYPE_REVIEWER_COMMENT = 0x1000003;
    public const NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION = 0x1000004;
    public const NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_INTERNAL_REVIEW = 0x1000005;
    public const NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW = 0x1000006;
    public const NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING = 0x1000007;
    public const NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION = 0x1000008;
    // define('NOTIFICATION_TYPE_AUDITOR_REQUEST',			0x1000009); // DEPRECATED; DO NOT USE
    public const NOTIFICATION_TYPE_REVIEW_ASSIGNMENT = 0x100000B;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW = 0x100000D;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT = 0x100000E;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW = 0x100000F;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS = 0x1000010;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT = 0x1000011;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND = 0x1000030;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE = 0x1000012;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION = 0x1000013;
    public const NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE = 0x1000020;
    public const NOTIFICATION_TYPE_REVIEW_ROUND_STATUS = 0x1000014;
    public const NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS = 0x1000015;
    public const NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS = 0x1000016;
    public const NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT = 0x1000017;
    public const NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT = 0x1000019;
    public const NOTIFICATION_TYPE_INDEX_ASSIGNMENT = 0x100001A;
    public const NOTIFICATION_TYPE_APPROVE_SUBMISSION = 0x100001B;
    public const NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD = 0x100001C;
    public const NOTIFICATION_TYPE_FORMAT_NEEDS_APPROVED_SUBMISSION = 0x100001D;
    public const NOTIFICATION_TYPE_VISIT_CATALOG = 0x100001E;
    public const NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED = 0x100001F;
    public const NOTIFICATION_TYPE_NEW_QUERY = 0x1000021;
    public const NOTIFICATION_TYPE_QUERY_ACTIVITY = 0x1000022;

    public const NOTIFICATION_TYPE_ASSIGN_COPYEDITOR = 0x1000023;
    public const NOTIFICATION_TYPE_AWAITING_COPYEDITS = 0x1000024;
    public const NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS = 0x1000025;
    public const NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER = 0x1000026;

    public const NOTIFICATION_TYPE_EDITOR_ASSIGN = 0x1000027;
    public const NOTIFICATION_TYPE_PAYMENT_REQUIRED = 0x1000028;

    public const NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED = 0x1000029;
    public const NOTIFICATION_TYPE_EDITORIAL_REPORT = 0x100002A;

    public const NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION = 0x100002B;
    public const NOTIFICATION_TYPE_EDITORIAL_REMINDER = 0x100002C;

    // Maximum number of notifications that can be sent per job
    public const NOTIFICATION_CHUNK_SIZE_LIMIT = 100;

    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    public $timestamps = false;

    protected $fillable = [
        'contextId', 'userId', 'level', 'type',
        'dateCreated', 'dateRead', 'assocType', 'assocId',
    ];

    protected function casts(): array
    {
        return [
            'dateCreated' => 'datetime',
            'dateRead' => 'datetime',
            'assocType' => 'int',
            'assocId' => 'int',
        ];
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Compatibility function for including notifications in grids
     *
     * @deprecated
     */
    public function getId()
    {
        return $this->id;
    }

    // Scopes

    /**
     * Scope a query to only include notifications with a specific context ID.
     */
    public function scopeWithContextId(Builder $query, ?int $contextId): Builder
    {
        return $query->when(
            $contextId !== null,
            fn ($q) => $q->where('context_id', $contextId),
            fn ($q) => $q->whereNull('context_id')
        );
    }

    /**
     * Scope a query to only include notifications with a specific user ID.
     */
    public function scopeWithUserId(Builder $query, ?int $userId): Builder
    {
        return $query->when(
            $userId !== null,
            fn ($q) => $q->where('user_id', $userId),
            fn ($q) => $q->whereNull('user_id')
        );
    }

    /**
     * Scope a query to only include notifications with a specific assoc type and ID.
     */
    public function scopeWithAssoc(Builder $query, int $assocType, int $assocId): Builder
    {
        return $query->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId);
    }

    /**
     * Scope a query to only include notifications with a specific level.
     */
    public function scopeWithLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Scope a query to only include notifications with a specific type.
     */
    public function scopeWithType(Builder $query, int $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include notifications with a read/unread status.
     */
    public function scopeWithRead(Builder $query, bool $read)
    {
        return $query->when(
            $read,
            fn ($q) => $q->whereNotNull('date_read'),
            fn ($q) => $q->whereNull('date_read')
        );
    }
}

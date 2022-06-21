<?php

/**
 * @file classes/notification/Notification.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Notification
 * @ingroup notification
 *
 * @see NotificationDAO
 * @brief Class for Notification.
 */

namespace PKP\notification;

class PKPNotification extends \PKP\core\DataObject
{
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
    public const NOTIFICATION_TYPE_METADATA_MODIFIED = 0x1000002;

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

    /**
     * get user id associated with this notification
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->getData('userId');
    }

    /**
     * set user id associated with this notification
     *
     * @param int $userId
     */
    public function setUserId($userId)
    {
        $this->setData('userId', $userId);
    }

    /**
     * Get the level (NOTIFICATION_LEVEL_...) for this notification
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->getData('level');
    }

    /**
     * Set the level (NOTIFICATION_LEVEL_...) for this notification
     *
     * @param int $level
     */
    public function setLevel($level)
    {
        $this->setData('level', $level);
    }

    /**
     * get date notification was created
     *
     * @return date (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateCreated()
    {
        return $this->getData('dateCreated');
    }

    /**
     * set date notification was created
     *
     * @param date $dateCreated (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateCreated($dateCreated)
    {
        $this->setData('dateCreated', $dateCreated);
    }

    /**
     * get date notification is read by user
     *
     * @return date (YYYY-MM-DD HH:MM:SS)
     */
    public function getDateRead()
    {
        return $this->getData('dateRead');
    }

    /**
     * set date notification is read by user
     *
     * @param date $dateRead (YYYY-MM-DD HH:MM:SS)
     */
    public function setDateRead($dateRead)
    {
        $this->setData('dateRead', $dateRead);
    }

    /**
     * get notification type
     *
     * @return int
     */
    public function getType()
    {
        return $this->getData('type');
    }

    /**
     * set notification type
     *
     * @param int $type
     */
    public function setType($type)
    {
        $this->setData('type', $type);
    }

    /**
     * get notification type
     *
     * @return int
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * set notification type
     *
     * @param int $assocType
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * get notification assoc id
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * set notification assoc id
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * get context id
     *
     * @return int
     */
    public function getContextId()
    {
        return $this->getData('context_id');
    }

    /**
     * set context id
     */
    public function setContextId($contextId)
    {
        $this->setData('context_id', $contextId);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\notification\PKPNotification', '\PKPNotification');
    foreach ([
        'NOTIFICATION_LEVEL_TRIVIAL',
        'NOTIFICATION_LEVEL_NORMAL',
        'NOTIFICATION_LEVEL_TASK',
        'NOTIFICATION_TYPE_SUCCESS',
        'NOTIFICATION_TYPE_WARNING',
        'NOTIFICATION_TYPE_ERROR',
        'NOTIFICATION_TYPE_FORBIDDEN',
        'NOTIFICATION_TYPE_INFORMATION',
        'NOTIFICATION_TYPE_HELP',
        'NOTIFICATION_TYPE_FORM_ERROR',
        'NOTIFICATION_TYPE_NEW_ANNOUNCEMENT',
        'NOTIFICATION_TYPE_PLUGIN_ENABLED',
        'NOTIFICATION_TYPE_PLUGIN_DISABLED',
        'NOTIFICATION_TYPE_PLUGIN_BASE',
        'NOTIFICATION_TYPE_SUBMISSION_SUBMITTED',
        'NOTIFICATION_TYPE_METADATA_MODIFIED',
        'NOTIFICATION_TYPE_REVIEWER_COMMENT',
        'NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION',
        'NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_INTERNAL_REVIEW',
        'NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW',
        'NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING',
        'NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION',
        'NOTIFICATION_TYPE_REVIEW_ASSIGNMENT',
        'NOTIFICATION_TYPE_EDITOR_DECISION_INTERNAL_REVIEW',
        'NOTIFICATION_TYPE_EDITOR_DECISION_ACCEPT',
        'NOTIFICATION_TYPE_EDITOR_DECISION_EXTERNAL_REVIEW',
        'NOTIFICATION_TYPE_EDITOR_DECISION_PENDING_REVISIONS',
        'NOTIFICATION_TYPE_EDITOR_DECISION_RESUBMIT',
        'NOTIFICATION_TYPE_EDITOR_DECISION_NEW_ROUND',
        'NOTIFICATION_TYPE_EDITOR_DECISION_DECLINE',
        'NOTIFICATION_TYPE_EDITOR_DECISION_SEND_TO_PRODUCTION',
        'NOTIFICATION_TYPE_EDITOR_DECISION_REVERT_DECLINE',
        'NOTIFICATION_TYPE_REVIEW_ROUND_STATUS',
        'NOTIFICATION_TYPE_PENDING_INTERNAL_REVISIONS',
        'NOTIFICATION_TYPE_PENDING_EXTERNAL_REVISIONS',
        'NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT',
        'NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT',
        'NOTIFICATION_TYPE_INDEX_ASSIGNMENT',
        'NOTIFICATION_TYPE_APPROVE_SUBMISSION',
        'NOTIFICATION_TYPE_CONFIGURE_PAYMENT_METHOD',
        'NOTIFICATION_TYPE_FORMAT_NEEDS_APPROVED_SUBMISSION',
        'NOTIFICATION_TYPE_VISIT_CATALOG',
        'NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED',
        'NOTIFICATION_TYPE_NEW_QUERY',
        'NOTIFICATION_TYPE_QUERY_ACTIVITY',
        'NOTIFICATION_TYPE_ASSIGN_COPYEDITOR',
        'NOTIFICATION_TYPE_AWAITING_COPYEDITS',
        'NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS',
        'NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER',
        'NOTIFICATION_TYPE_EDITOR_ASSIGN',
        'NOTIFICATION_TYPE_PAYMENT_REQUIRED',
        'NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED',
        'NOTIFICATION_TYPE_EDITORIAL_REPORT',
        'NOTIFICATION_TYPE_SUBMISSION_NEW_VERSION',
    ] as $constantName) {
        define($constantName, constant('\PKPNotification::' . $constantName));
    }
}

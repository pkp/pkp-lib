<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/report/mappings/Notifications.inc.php
 *
 * Copyright (c) 2003-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Notifications
 * @ingroup lib_pkp_classes_user
 *
 * @brief Retrieves a list of Mapping objects to display the user notifications.
 */

namespace PKP\User\Report\Mappings;
use PKP\User\Report\{Report, Mapping, QueryBuilder, QueryListenerInterface, QueryOptions};
use Illuminate\Database\Capsule\Manager as Capsule;

class Notifications implements QueryListenerInterface {
	/** @var string The key to extract the notification title */
	private const NOTIFICATION_TITLE_KEY = 'settingKey';

	/** @var string The setting which holds the notification status */
	private const BLOCKED_NOTIFICATION_KEY = 'blocked_notification';

	/** @var string The setting which holds the email notification status */
	private const BLOCKED_EMAIL_NOTIFICATION_KEY = 'blocked_emailed_notification';

	/** @var array A dictionary containing all notifications available in the application, the key is the notification ID */
	private $_notificationsMap;

	/**
	 * Constructor
	 * @param Report $report
	 */
	public function __construct(Report $report)
	{
		$this->_loadNotificationsMap();
		$report->getQueryBuilder()->addListener($this);
		$report->addMappings(...$this->_getMappings());
	}

	/**
	 * @copydoc QueryListenerInterface::onQuery()
	 */
	public function onQuery(\Illuminate\Database\Query\Builder $query, QueryOptions $options): void
	{
		$query->leftJoin('notification_subscription_settings AS nss', function ($join) {
			$join->on('nss.user_id', 'u.user_id')
				->on('nss.context', 'ug.context_id');
		});
		switch (Capsule::connection()->getDriverName()) {
			case 'mysql':
				$options->columns[] = Capsule::raw("GROUP_CONCAT(DISTINCT CONCAT('[', nss.setting_name, '-', nss.setting_value, ']')) AS notifications");
				break;
			default:
				$options->columns[] = Capsule::raw("STRING_AGG(DISTINCT CONCAT('[', nss.setting_name, '-', nss.setting_value, ']'), ',') AS notifications");
				break;
		}
	}

	/**
	 * Retrieves the notification mappings
	 * @return Mapping[] A list of Mapping objects for all the notifications
	 */
	private function _getMappings(): array
	{
		$mappings = [];
		foreach ($this->_notificationsMap as $notificationId => [self::NOTIFICATION_TITLE_KEY => $caption]) {
			array_push(
				$mappings,
				new Mapping(
					__('notification.notification') . ': ' . __($caption),
					function (\User $user, object $userRecord) use ($notificationId): string {
						return $this->_getStatus($userRecord, $notificationId, false);
					}
				),
				new Mapping(
					__('notification.notification') . ' (' . __('email.email') . ')' . ': ' . __($caption),
					function (\User $user, object $userRecord) use ($notificationId): string {
						return $this->_getStatus($userRecord, $notificationId, true);
					}
				)
			);
		}
		return $mappings;
	}

	/**
	 * Extracts the existing notifications from the application
	 */
	private function _loadNotificationsMap(): void
	{
		import('classes.notification.form.NotificationSettingsForm');

		$extractor = new class extends \NotificationSettingsForm {
			public function extractNotifications(): array
			{
				return $this->getNotificationSettingsMap();
			}
		};

		$this->_notificationsMap = $extractor->extractNotifications();
	}

	/**
	 * Retrieves whether the given notification is enabled
	 * @param object $userRecord The user object
	 * @param int $notificationId The notification ID
	 * @param bool $isEmail Whether it's a simple or email notification
	 * @return string Localized text with Yes or No
	 */
	private static function _getStatus(object $userRecord, int $notificationId, bool $isEmail): string
	{
		$type = $isEmail ? self::BLOCKED_EMAIL_NOTIFICATION_KEY : self::BLOCKED_NOTIFICATION_KEY;
		return __(is_int(strpos($userRecord->notifications, '[' . $type . '-' . $notificationId . ']')) ? 'common.no' : 'common.yes');
	}
}

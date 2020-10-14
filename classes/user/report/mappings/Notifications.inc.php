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
use PKP\User\Report\{Report, Mapping};
use Illuminate\Database\Capsule\Manager as Capsule;

class Notifications {
	/** @var string The key to extract the notification title */
	private const NOTIFICATION_TITLE_KEY = 'settingKey';

	/** @var array A dictionary containing all notifications available in the application, the key is the notification ID */
	private $_notificationsMap;

	/**
	 * Constructor
	 * @param Report $report
	 */
	public function __construct(Report $report)
	{
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR);
		$this->_loadNotificationsMap();
		$report->addMappings(...$this->_getMappings());
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
					function (\User $user) use ($notificationId): string {
						return $this->_getStatus($user, $notificationId, false);
					}
				),
				new Mapping(
					__('notification.notification') . ' (' . __('email.email') . ')' . ': ' . __($caption),
					function (\User $user) use ($notificationId): string {
						return $this->_getStatus($user, $notificationId, true);
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
	 * @param \User $user The user object
	 * @param int $notificationId The notification ID
	 * @param bool $isEmail Whether it's a simple or email notification
	 * @return string Localized text with Yes or No
	 */
	private static function _getStatus(\User $user, int $notificationId, bool $isEmail): string
	{
		static $lastUserId = null;
		static $notifications = null;

		if ($lastUserId != $user->getId()) {
			['notifications' => $notifications] = \Services::get('user')->getProperties($user, ['notifications'], ['request' => \Application::get()->getRequest()]);
			$lastUserId = $user->getId();
		}
		
		$hasNotification = !is_int(array_search($notificationId, $notifications['notifications']));
		if ($isEmail && $hasNotification) {
			$hasNotification = !is_int(array_search($notificationId, $notifications['emailNotifications']));
		}

		return __($hasNotification ? 'common.yes' : 'common.no');
	}
}

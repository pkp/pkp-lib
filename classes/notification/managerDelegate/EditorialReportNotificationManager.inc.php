<?php

/**
 * @file classes/notification/managerDelegate/EditorialReportNotificationManager.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EditorialReportNotificationManager
 * @ingroup managerDelegate
 *
 * @brief Editorial report notification manager.
 */

import('lib.pkp.classes.notification.NotificationManagerDelegate');

class EditorialReportNotificationManager extends NotificationManagerDelegate {
	/** @var Context context instance */
	private $_context;
	/** @var Request request instance */
	private $_request;
	/** @var array cached message parameters */
	private $_params;
	/** @var array cached attachment */
	private $_attachmentFilename;
	/** @var DateTime start date filter for the ranged statistics */
	private $_dateStart;
	/** @var DateTime end date filter for the ranged statistics */
	private $_dateEnd;
	/** @var array cache of the all-time editorial statistics */
	private $_statistics;
	/** @var array cache of the ranged editorial statistics */
	private $_rangedStatistics;
	/** @var array cache of the all-time user statistics */
	private $_userStatistics;
	/** @var array cache of the ranged user statistics */
	private $_rangedUserStatistics;

	/**
	 * @copydoc NotificationManagerDelegate::__construct()
	 */
	public function __construct($notificationType) {
		parent::__construct($notificationType);
		$this->_request = Application::getRequest();
	}

	/**
	 * Initializes the class.
	 * @param $context Context The context from where the statistics shall be retrieved
	 * @param $context DateTime start date filter for the ranged statistics
	 * @param $context DateTime end date filter for the ranged statistics
	 */
	public function initialize(Context $context, DateTime $dateStart, DateTime $dateEnd) {
		$this->_context = $context;
		$this->_dateStart = $dateStart;
		$this->_dateEnd = $dateEnd;

		$editorialStatisticsService = \ServicesContainer::instance()->get('editorialStatistics');
		
		$params = [
			'dateStart' => $this->_dateStart->format('Y-m-d H:i:s'),
			'dateEnd' => $this->_dateEnd->format('Y-m-d H:i:s')
		];

		$this->_statistics = $editorialStatisticsService->getSubmissionStatistics($this->_context->getId());
		$this->_rangedStatistics = $editorialStatisticsService->getSubmissionStatistics($this->_context->getId(), $params);
		$this->_userStatistics = $editorialStatisticsService->getUserStatistics($this->_context->getId());
		$this->_rangedUserStatistics = $editorialStatisticsService->getUserStatistics($this->_context->getId(), $params);
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) {
		return __('notification.type.editorialReport');
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationContents($request, $notification) {
		$locale = AppLocale::getLocale();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplate = $emailTemplateDao->getEmailTemplate('STATISTICS_REPORT_NOTIFICATION', $locale, $notification->getContextId());
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$contextDao = Application::getContextDAO();
		$context = $contextDao->getById($notification->getContextId());
		$dispatcher = Application::getDispatcher();
		return $dispatcher->url($this->_request, ROUTE_PAGE, $context->getPath(), 'stats', 'editorialReport');
	}

	/**
	 * @copydoc PKPNotificationManager::getIconClass()
	 */
	public function getIconClass($notification) {
		return 'notifyIconInfo';
	}

	/**
	 * @copydoc PKPNotificationManager::getStyleClass()
	 */
	public function getStyleClass($notification) {
		return NOTIFICATION_STYLE_CLASS_INFORMATION;
	}

	/**
	 * Sends a notification to the given user.
	 * @param $user User The user who will be notified
	 * @return Notification The notification instance
	 */
	public function notify(User $user) {
		return parent::createNotification(
			$this->_request, $user->getId(), NOTIFICATION_TYPE_EDITORIAL_REPORT, $this->_context->getId(),
			null, null, NOTIFICATION_LEVEL_TASK,
			array('contents' => __('notification.type.editorialReport.contents')), false, function ($mail) use ($user) {
				return $this->_setupMessage($mail, $user);
			}
		);
	}

	/**
	 * @copydoc PKPNotificationManager::getMailTemplate()
	 */
	protected function getMailTemplate($emailKey = null) {
		import('lib.pkp.classes.mail.MailTemplate');
		$mail = new MailTemplate('STATISTICS_REPORT_NOTIFICATION', null, $this->_context, false);
		return $mail;
	}

	/**
	 * Setups a customized message for the given user.
	 * @param $mail Mail The message which will be customized
	 * @param $user User The user who will be notified
	 * @return Mail The prepared message
	 */
	private function _setupMessage(Mail $mail, User $user) {
		$mail->assignParams($this->_getMessageParams($user));
		if ($this->_getMessageAttachment()) {
			$mail->addAttachment($this->_getMessageAttachment(), 'editorial-report.csv');
		}
		return $mail;
	}

	/**
	 * Retrieves the message parameters.
	 * @param $user User The user who will be notified
	 * @return array An array with the parameters and their values
	 */
	private function _getMessageParams(User $user) {
		if (!$this->_params) {
			$dispatcher = Application::getDispatcher();

			$this->_params = [
				'newSubmissions' => +$this->_rangedStatistics['SUBMISSION_RECEIVED'],
				'declinedSubmissions' => +$this->_rangedStatistics['SUBMISSION_DECLINED_TOTAL'],
				'acceptedSubmissions' => +$this->_rangedStatistics['SUBMISSION_ACCEPTED'],
				'totalSubmissions' => +$this->_statistics['SUBMISSION_RECEIVED'],
				'month' => $this->_dateStart->format('F'),
				'year' => $this->_dateStart->format('Y'),
				'editorialReportLink' => $dispatcher->url($this->_request, ROUTE_PAGE, $this->_context->getPath(), 'stats', 'editorialReport'),
				'articlesReportLink' => $dispatcher->url($this->_request, ROUTE_PAGE, $this->_context->getPath(), 'stats', 'publishedSubmissions')
			];
		}
		return $this->_params + ['name' => $user->getLocalizedGivenName()];
	}

	/**
	 * Retrieves the message attachment.
	 * @return string The full path of the attachment
	 */
	private function _getMessageAttachment() {
		if (!$this->_attachmentFilename) {
			import('classes.core.ServicesContainer');

			$editorialStatisticsService = \ServicesContainer::instance()->get('editorialStatistics');

			$submissionChartData = $editorialStatisticsService->compileSubmissionChartData($this->_statistics);
			$editorialStatistics = $editorialStatisticsService->compileEditorialStatistics($this->_rangedStatistics, $this->_statistics);
			$userStatistics = $editorialStatisticsService->compileUserStatistics($this->_rangedUserStatistics, $this->_userStatistics);

			$path = tempnam(sys_get_temp_dir(), 'tmp');
			if ($handle = fopen($path, 'wb')) {
				fputcsv($handle, [
					__('navigation.submissions'),
					__('stats.total')
				]);
				foreach ($submissionChartData as list('name' => $name, 'value' => $value)) {
					fputcsv($handle, [$name, $value]);
				}

				fputcsv($handle, []);
				fputcsv($handle, [
					__('manager.statistics.editorial.trends'),
					__('manager.statistics.totalWithinDateRange'),
					__('common.average') . '/' . __('common.year'),
					__('stats.total')
				]);
				foreach ($editorialStatistics as list('name' => $name, 'period' => $period, 'average' => $average, 'total' => $total)) {
					fputcsv($handle, [str_replace('&emsp;', '', $name), $period, $average, $total]);
				}

				fputcsv($handle, []);
				fputcsv($handle, [
					__('manager.users'),
					__('manager.statistics.totalWithinDateRange'),
					__('common.average') . '/' . __('common.year'),
					__('stats.total')
				]);
				foreach ($userStatistics as list('name' => $name, 'period' => $period, 'average' => $average, 'total' => $total)) {
					fputcsv($handle, [str_replace('&emsp;', '', $name), $period, $average, $total]);
				}

				fclose($handle);
				$this->_attachmentFilename = $path;
			}
		}
		return $this->_attachmentFilename;
	}
}

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
	/** @var Context Context instance */
	private $_context;
	/** @var Request Request instance */
	private $_request;
	/** @var array Cached message parameters */
	private $_params;
	/** @var array Cached attachment */
	private $_attachmentFilename;
	/** @var array Cache of editorial stats by date range */
	private $_editorialTrends;
	/** @var array Cache of editorial stats for all time */
	private $_editorialTrendsTotal;
	/** @var array Cache of user counts by role */
	private $_userRolesOverview;

	/**
	 * @copydoc NotificationManagerDelegate::__construct()
	 */
	public function __construct(int $notificationType) {
		parent::__construct($notificationType);
		$this->_request = Application::get()->getRequest();
	}

	/**
	 * Initializes the class.
	 * @param $context Context The context from where the statistics shall be retrieved
	 * @param $context DateTimeInterface Start date filter for the ranged statistics
	 * @param $context DateTimeInterface End date filter for the ranged statistics
	 */
	public function initialize(Context $context, DateTimeInterface $dateStart, DateTimeInterface $dateEnd) : void
	{
		$this->_context = $context;
		$dateStart = $dateStart;
		$dateEnd = $dateEnd;

		$dispatcher = $this->_request->getDispatcher();

		$this->_editorialTrends = Services::get('editorialStats')->getOverview([
			'contextIds' => [$this->_context->getId()],
			'dateStart' => $dateStart->format('Y-m-d'),
			'dateEnd' => $dateEnd->format('Y-m-d'),
		]);
		$this->_editorialTrendsTotal = Services::get('editorialStats')->getOverview([
			'contextIds' => [$this->_context->getId()],
		]);

		foreach ($this->_editorialTrends as $stat) {
			switch ($stat['key']) {
				case 'submissionsReceived':
					$newSubmissions = $stat['value'];
					break;
				case 'submissionsDeclined':
					$declinedSubmissions = $stat['value'];
					break;
				case 'submissionsAccepted':
					$acceptedSubmissions = $stat['value'];
					break;
			}
		}

		$this->_params = [
			'newSubmissions' => $newSubmissions,
			'declinedSubmissions' => $declinedSubmissions,
			'acceptedSubmissions' => $acceptedSubmissions,
			'totalSubmissions' => Services::get('editorialStats')->countSubmissionsReceived(['contextIds' => [$this->_context->getId()]]),
			'month' => $dateStart->format('F'),
			'year' => $dateStart->format('Y'),
			'editorialStatsLink' => $dispatcher->url($this->_request, ROUTE_PAGE, $this->_context->getPath(), 'stats', 'editorial'),
			'publicationStatsLink' => $dispatcher->url($this->_request, ROUTE_PAGE, $this->_context->getPath(), 'stats', 'publications')
		];

		$this->_userRolesOverview = Services::get('user')->getRolesOverview(['contextId' => $this->_context->getId()]);

		// Create the CSV file attachment
		// Active submissions by stage
		$file = new SplFileObject(tempnam(sys_get_temp_dir(), 'tmp'), 'wb');
		$file->fputcsv([
			__('stats.submissionsActive'),
			__('stats.total')
		]);
		foreach (Application::get()->getApplicationStages() as $stageId) {
			$file->fputcsv([
				Application::get()->getWorkflowStageName($stageId),
				Services::get('editorialStats')->countActiveByStages($stageId)
			]);
		}

		$file->fputcsv([]);

		// Editorial trends
		$file->fputcsv([
			__('stats.trends'),
			$dateStart->format('F' . __('common.commaListSeparator') . 'Y'),
			__('stats.total')
		]);
		foreach ($this->_editorialTrends as $i => $stat) {
			$file->fputcsv([
				$stat['name'],
				$stat['value'],
				$this->_editorialTrendsTotal[$i]['value']
			]);
		}

		$file->fputcsv([]);

		// Count of users by role
		$file->fputcsv([
			__('manager.users'),
			__('stats.total')
		]);
		foreach ($this->_userRolesOverview as $role) {
			$file->fputcsv([
				$role['name'],
				$role['value']
			]);
		}

		$this->_attachmentFilename = $file->getRealPath();
		$file = null;
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationMessage($request, $notification) : string
	{
		return __('notification.type.editorialReport');
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationMessage()
	 */
	public function getNotificationContents($request, $notification) : EmailTemplate
	{
		return Services::get('emailTemplate')->getByKey($notification->getContextId(), 'STATISTICS_REPORT_NOTIFICATION');
	}

	/**
	 * @copydoc PKPNotificationOperationManager::getNotificationUrl()
	 */
	public function getNotificationUrl($request, $notification) {
		$context = Application::get()->getContextDAO()->getById($notification->getContextId());
		return $request->getDispatcher()->url($this->_request, ROUTE_PAGE, $context->getPath(), 'stats', 'editorialReport');
	}

	/**
	 * @copydoc PKPNotificationManager::getIconClass()
	 */
	public function getIconClass($notification) : string
	{
		return 'notifyIconInfo';
	}

	/**
	 * @copydoc PKPNotificationManager::getStyleClass()
	 */
	public function getStyleClass($notification) : string
	{
		return NOTIFICATION_STYLE_CLASS_INFORMATION;
	}

	/**
	 * Sends a notification to the given user.
	 * @param $user User The user who will be notified
	 * @return PKPNotification The notification instance
	 */
	public function notify(User $user) : PKPNotification
	{
		return parent::createNotification(
			$this->_request,
			$user->getId(),
			NOTIFICATION_TYPE_EDITORIAL_REPORT,
			$this->_context->getId(),
			null,
			null,
			NOTIFICATION_LEVEL_TASK,
			array('contents' => __('notification.type.editorialReport.contents')),
			false,
			function ($mail) use ($user) {
				return $this->_setupMessage($mail, $user);
			}
		);
	}

	/**
	 * @copydoc PKPNotificationManager::getMailTemplate()
	 */
	protected function getMailTemplate($emailKey = null) : MailTemplate
	{
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
	private function _setupMessage(Mail $mail, User $user) : Mail
	{
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
	private function _getMessageParams(User $user) : array
	{
		return $this->_params + ['name' => $user->getLocalizedGivenName()];
	}

	/**
	 * Retrieves the message attachment.
	 * @return string The full path of the attachment
	 */
	private function _getMessageAttachment() : string
	{
		return $this->_attachmentFilename;
	}
}

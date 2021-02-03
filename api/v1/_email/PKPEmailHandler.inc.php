<?php

/**
 * @file api/v1/_email/PKPEmailHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailHandler
 * @ingroup api_v1_announcement
 *
 * @brief Handle API requests for announcement operations.
 *
 */
use \Illuminate\Queue\Capsule\Manager as Queue;
use Illuminate\Database\Capsule\Manager as Capsule;
use \Psr\Http\Message\ServerRequestInterface;

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPEmailHandler extends APIHandler {

	/** Number of emails to send in each job */
	const EMAILS_PER_JOB = 100;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = '_email';
		$this->_endpoints = [
			'POST' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'create'],
					'roles' => [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
				],
			],
			'PUT' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{queueId}',
					'handler' => [$this, 'process'],
					'roles' => [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
				],
			],
		];
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize
	 */
	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach ($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Create a jobs queue to send a bulk email to users in one or
	 * more user groups
	 *
	 * @param ServerRequestInterface $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return APIResponse
	 */
	public function create(ServerRequestInterface $slimRequest, APIResponse $response, array $args) {
		$context = $this->getRequest()->getContext();
		$contextId = $context->getId();

		if (!in_array($contextId, (array) $this->getRequest()->getSite()->getData('enableBulkEmails'))) {
			return $response->withStatus(403)->withJsonError('api.emails.403.disabled');
		}

		$requestParams = $slimRequest->getParsedBody();

		$params = [];
		foreach ($requestParams as $param => $val) {
			switch ($param) {
				case 'userGroupIds':
					if (!is_array($val)) {
						$val = strlen(trim($val))
							? explode(',', $val)
							: [];
					}
					$params[$param] = array_map('intval', $val);
					break;
				case 'body':
				case 'subject':
					$params[$param] = $val;
					break;
				case 'copy':
					$params[$param] = (bool) $val;
					break;
			}
		}

		$errors = [];
		if (empty($params['body'])) {
			$errors['body'] = [__('api.emails.400.missingBody')];
		}

		if (empty($params['subject'])) {
			$errors['subject'] = [__('api.emails.400.missingSubject')];
		}

		if (empty($params['userGroupIds'])) {
			$errors['userGroupIds'] = [__('api.emails.400.missingUserGroups')];
		}

		if ($errors) {
			return $response->withJson($errors, 400);
		}

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		foreach ($params['userGroupIds'] as $userGroupId) {
			if (!$userGroupDao->contextHasGroup($contextId, $userGroupId)
					|| in_array($userGroupId, (array) $context->getData('disableBulkEmailUserGroups'))) {
				return $response->withJson([
						'userGroupIds' => [__('api.emails.403.notAllowedUserGroup')],
					], 400);
			}
		}

		// Only permit emails to be sent to active users in this context
		$params['status'] = 'active';
		$params['contextId'] = $contextId;

		$userIds = Services::get('user')->getIds($params);
		$subject = $params['subject'];
		$body = $params['body'];
		$fromEmail = $context->getData('contactEmail');
		$fromName = $context->getData('contactName');
		$queueId = 'email_' . uniqid();

		if (!empty($params['copy'])) {
			$currentUserId = $this->getRequest()->getUser()->getId();
			if (!in_array($currentUserId, $userIds)) {
				$userIds[] = $currentUserId;
			}
		}

		$batches = array_chunk($userIds, self::EMAILS_PER_JOB);
		foreach ($batches as $userIds) {
			Queue::push(function() use ($userIds, $contextId, $subject, $body, $fromEmail, $fromName) {
				import('lib.pkp.classes.mail.Mail');
				$users = Services::get('user')->getMany([
					'contextId' => $contextId,
					'userIds' => $userIds,
				]);
				foreach ($users as $user) {
					$mail = new Mail();
					$mail->setFrom($fromEmail, $fromName);
					$mail->setRecipients([
						[
							'name' => $user->getFullName(),
							'email' => $user->getEmail(),
						],
					]);
					$mail->setSubject($subject);
					$mail->setBody($body);
					$mail->send();
				}
			}, [], $queueId, 'persistent');
		}

		return $response->withJson([
			'queueId' => $queueId,
			'totalJobs' => count($batches),
		], 200);
	}

	/**
	 * Process a jobs queue for sending a bulk email
	 *
	 * @param ServerRequestInterface $slimRequest
	 * @param APIResponse $response
	 * @param array $args arguments
	 * @return APIResponse
	 */
	public function process(ServerRequestInterface $slimRequest, APIResponse $response, array $args) {
		$countRunning = Capsule::table('jobs')
			->where('queue', $args['queueId'])
			->whereNotNull('reserved_at')
			->count();
		$countPending = $this->countPending($args['queueId']);

		// Don't run another job if one is already running.
		// This should ensure jobs are run one after the other and
		// prevent long-running jobs from running simultaneously
		// and piling onto the server like a DDOS attack.
		if (!$countRunning && $countPending) {
			$laravelContainer = Registry::get('laravelContainer');
			$worker = new Illuminate\Queue\Worker(
				$laravelContainer['queue'],
				$laravelContainer['events'],
				$laravelContainer['exception.handler'],
				function() {
					return false; // is not down for maintenance
				}
			);
			$options = new Illuminate\Queue\WorkerOptions();
			$worker->runNextJob('persistent', $args['queueId'], $options);

			// Update count of pending jobs
			$countPending = $this->countPending($args['queueId']);
		}

		return $response->withJson([
			'pendingJobs' => $countPending,
		], 200);
	}

	/**
	 * Return a count of the pending jobs in a given queue
	 *
	 * @param string $queueId
	 * @return int
	 */
	protected function countPending(string $queueId) : int {
		return Capsule::table('jobs')
			->where('queue', $queueId)
			->count();
	}
}

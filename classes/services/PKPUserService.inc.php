<?php
/**
 * @file classes/services/PKPUserService.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUserService
 * @ingroup services
 *
 * @brief Helper class that encapsulates users business logic
 */

namespace PKP\Services;

use \Application;
use \DBResultRange;
use \DAOResultFactory;
use \DAORegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\QueryBuilders\PKPUserQueryBuilder;
use \PKP\User\Report;

class PKPUserService implements EntityPropertyInterface, EntityReadInterface {

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($userId) {
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		return $userDao->getById($userId);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getCount()
	 */
	public function getCount($args = []) {
		return $this->getQueryBuilder($args)->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getIds()
	 */
	public function getIds($args = []) {
		return $this->getQueryBuilder($args)->getIds();
	}

	/**
	 * Get a collection of User objects limited, filtered
	 * and sorted by $args
	 *
	 * @param array $args
	 *		@option int contextId If not supplied, CONTEXT_ID_NONE will be used and
	 *			no submissions will be returned. To retrieve users from all
	 *			contexts, use CONTEXT_ID_ALL.
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option string roleIds
	 * 		@option int assignedToSubmission
	 * 		@option int assignedToSubmissionStage
	 * 		@option array includeUsers
	 * 		@option array excludeUsers
	 * 		@option string status
	 * 		@option string searchPhrase
	 *  	@option array userGroupIds
	 * 		@option int count
	 * 		@option int offset
	 * @return Iterator
	 */
	public function getMany($args = []) {
		$range = null;
		if (isset($args['count'])) {
			import('lib.pkp.classes.db.DBResultRange');
			$range = new \DBResultRange($args['count'], null, isset($args['offset']) ? $args['offset'] : 0);
		}
		// Pagination is handled by the DAO, so don't pass count and offset
		// arguments to the QueryBuilder.
		if (isset($args['count'])) unset($args['count']);
		if (isset($args['offset'])) unset($args['offset']);
		$userListQO = $this->getQueryBuilder($args)->getQuery();
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$result = $userDao->retrieveRange($userListQO->toSql(), $userListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $userDao, '_returnUserFromRowWithData');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = []) {
		// Don't accept args to limit the results
		if (isset($args['count'])) unset($args['count']);
		if (isset($args['offset'])) unset($args['offset']);
		return $this->getQueryBuilder($args)->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getQueryBuilder()
	 * @return PKPUserQueryBuilder
	 */
	public function getQueryBuilder($args = []) {
		$defaultArgs = [
			'contextId' => CONTEXT_ID_NONE,
			'orderBy' => 'id',
			'orderDirection' => 'DESC',
			'roleIds' => null,
			'userGroupIds' => [],
			'userIds' => [],
			'assignedToSubmission' => null,
			'assignedToSubmissionStage' => null,
			'registeredAfter' => '',
			'registeredBefore' => '',
			'includeUsers' => null,
			'excludeUsers' => null,
			'status' => 'active',
			'searchPhrase' => null,
		];

		$args = array_merge($defaultArgs, $args);

		$userListQB = new PKPUserQueryBuilder();
		$userListQB
			->filterByContext($args['contextId'])
			->orderBy($args['orderBy'], $args['orderDirection'])
			->filterByRoleIds($args['roleIds'])
			->filterByUserGroupIds($args['userGroupIds'])
			->filterByUserIds($args['userIds'])
			->assignedToSubmission($args['assignedToSubmission'], $args['assignedToSubmissionStage'])
			->registeredAfter($args['registeredAfter'])
			->registeredBefore($args['registeredBefore'])
			->includeUsers($args['includeUsers'])
			->excludeUsers($args['excludeUsers'])
			->filterByStatus($args['status'])
			->searchPhrase($args['searchPhrase'])
			->filterByUserGroupIds($args['userGroupIds']);

		if (isset($args['count'])) {
			$userListQB->limitTo($args['count']);
		}

		if (isset($args['offset'])) {
			$userListQB->offsetBy($args['count']);
		}

		if (isset($args['assignedToSection'])) {
			$userListQB->assignedToSection($args['assignedToSection']);
		}

		if (isset($args['assignedToCategory'])) {
			$userListQB->assignedToCategory($args['assignedToCategory']);
		}

		\HookRegistry::call('User::getMany::queryBuilder', [&$userListQB, $args]);

		return $userListQB;
	}

	/**
	 * Get a collection of User objects with reviewer stats
	 * limited, filtered and sorted by $args
	 *
	 * @see self::getMany()
	 * @return \Iterator
	 */
	public function getReviewers($args = []) {
		$range = null;
		if (isset($args['count'])) {
			import('lib.pkp.classes.db.DBResultRange');
			$range = new \DBResultRange($args['count'], null, isset($args['offset']) ? $args['offset'] : 0);
		}
		// Pagination is handled by the DAO, so don't pass count and offset
		// arguments to the QueryBuilder.
		if (isset($args['count'])) unset($args['count']);
		if (isset($args['offset'])) unset($args['offset']);
		$userListQO = $this->getReviewersQueryBuilder($args)->getQuery();
		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$result = $userDao->retrieveRange($userListQO->toSql(), $userListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $userDao, '_returnUserFromRowWithReviewerStats');

		return $queryResults->toIterator();
	}

	/**
	 * Get max count of reviewers matching a query request
	 *
	 * @see self::getMax()
	 * @return int
	 */
	public function getReviewersMax($args = []) {
		// Don't accept args to limit the results
		if (isset($args['count'])) unset($args['count']);
		if (isset($args['offset'])) unset($args['offset']);
		return $this->getReviewersQueryBuilder($args)->getCount();
	}

	/**
	 * Build the reviewers query object for getReviewers requests
	 *
	 * @see self::getQueryBuilder()
	 * @return PKPUserQueryBuilder
	 */
	public function getReviewersQueryBuilder($args = []) {
		$args = array_merge([
			'contextId' => CONTEXT_ID_NONE,
			'reviewStage' => null,
			'reviewsCompleted' => null,
			'reviewsActive' => null,
			'daysSinceLastAssignment' => null,
			'averageCompletion' => null,
			'reviewerRating' => null,
		], $args, [
			'roleIds' => ROLE_ID_REVIEWER,
		]);

		$reviewerListQB = $this->getQueryBuilder($args);
		$reviewerListQB
			->getReviewerData(true)
			->filterByReviewStage($args['reviewStage'])
			->filterByReviewerRating($args['reviewerRating'])
			->filterByReviewsCompleted($args['reviewsCompleted'])
			->filterByReviewsActive($args['reviewsActive'])
			->filterByDaysSinceLastAssignment($args['daysSinceLastAssignment'])
			->filterByAverageCompletion($args['averageCompletion']);

		\HookRegistry::call('User::getReviewers::queryBuilder', [&$reviewerListQB, $args]);

		return $reviewerListQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($user, $props, $args = null) {
		$request = $args['request'];
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();

		$values = [];
		foreach ($props as $prop) {
			switch ($prop) {
				case 'id':
					$values[$prop] = (int) $user->getId();
					break;
				case 'userName':
					$values[$prop] = $user->getUserName();
					break;
				case 'fullName':
					$values[$prop] = $user->getFullName();
					break;
				case 'givenName':
					$values[$prop] = $user->getGivenName(null);
					break;
				case 'familyName':
					$values[$prop] = $user->getFamilyName(null);
					break;
				case 'affiliation':
					$values[$prop] = $user->getAffiliation(null);
					break;
				case 'country':
					$values[$prop] = $user->getCountry();
					break;
				case 'url':
					$values[$prop] = $user->getUrl();
					break;
				case 'email':
					$values[$prop] = $user->getEmail();
					break;
				case 'orcid':
					$values[$prop] = $user->getOrcid();
					break;
				case 'biography':
					$values[$prop] = $user->getBiography(null);
					break;
				case 'signature':
					$values[$prop] = $user->getSignature(null);
					break;
				case 'authId':
					$values[$prop] = $user->getAuthId();
					break;
				case 'authString':
					$values[$prop] = $user->getAuthStr();
					break;
				case 'phone':
					$values[$prop] = $user->getPhone();
					break;
				case 'mailingAddress':
					$values[$prop] = $user->getMailingAddress();
					break;
				case 'billingAddress':
					$values[$prop] = $user->getBillingAddress();
					break;
				case 'gossip':
					if ($this->canCurrentUserGossip($user->getId())) {
						$values[$prop] = $user->getGossip();
					}
					break;
				case 'reviewsActive':
					$values[$prop] = $user->getData('incompleteCount');
					break;
				case 'reviewsCompleted':
					$values[$prop] = $user->getData('completeCount');
					break;
				case 'reviewsDeclined':
					$values[$prop] = $user->getData('declinedCount');
					break;
				case 'reviewsCancelled':
					$values[$prop] = $user->getData('cancelledCount');
					break;
				case 'averageReviewCompletionDays':
					$values[$prop] = $user->getData('averageTime');
					break;
				case 'dateLastReviewAssignment':
					$values[$prop] = $user->getData('lastAssigned');
					break;
				case 'reviewerRating':
					$values[$prop] = $user->getData('reviewerRating');
					break;
				case 'disabled':
					$values[$prop] = (boolean) $user->getDisabled();
					break;
				case 'disabledReason':
					$values[$prop] = $user->getDisabledReason();
					break;
				case 'dateRegistered':
					$values[$prop] = $user->getDateRegistered();
					break;
				case 'dateValidated':
					$values[$prop] = $user->getDateValidated();
					break;
				case 'dateLastLogin':
					$values[$prop] = $user->getDateLastLogin();
					break;
				case 'mustChangePassword':
					$values[$prop] = (boolean) $user->getMustChangePassword();
					break;
				case '_href':
					$values[$prop] = null;
					if (!empty($args['slimRequest'])) {
						$route = $args['slimRequest']->getAttribute('route');
						$arguments = $route->getArguments();
						$values[$prop] = $dispatcher->url(
							$args['request'],
							ROUTE_API,
							$arguments['contextPath'],
							'users/' . $user->getId()
						);
					}
					break;
				case 'groups':
					$values[$prop] = null;
					if ($context) {
						import('lib.pkp.classes.security.UserGroupDAO');
						$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
						$userGroups = $userGroupDao->getByUserId($user->getId(), $context->getId());
						$values[$prop] = [];
						while ($userGroup = $userGroups->next()) {
							$values[$prop][] = [
								'id' => (int) $userGroup->getId(),
								'name' => $userGroup->getName(null),
								'abbrev' => $userGroup->getAbbrev(null),
								'roleId' => (int) $userGroup->getRoleId(),
								'showTitle' => (boolean) $userGroup->getShowTitle(),
								'permitSelfRegistration' => (boolean) $userGroup->getPermitSelfRegistration(),
								'permitMetadataEdit' => (boolean) $userGroup->getPermitMetadataEdit(),
								'recommendOnly' => (boolean) $userGroup->getRecommendOnly(),
							];
						}
					}
					break;
				case 'interests':
					$values[$prop] = [];
					if ($context) {
						import('lib.pkp.classes.user.InterestDAO');
						$interestDao = DAORegistry::getDAO('InterestDAO'); /* @var $interestDao InterestDAO */
						$interestEntryIds = $interestDao->getUserInterestIds($user->getId());
						if (!empty($interestEntryIds)) {
							import('lib.pkp.classes.user.InterestEntryDAO');
							$interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /* @var $interestEntryDao InterestEntryDAO */
							$results = $interestEntryDao->getByIds($interestEntryIds);
							$values[$prop] = [];
							while ($interest = $results->next()) {
								$values[$prop][] = [
									'id' => (int) $interest->getId(),
									'interest' => $interest->getInterest(),
								];
							}
						}
					}
					break;
			}

			$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_USER, $values, $context->getSupportedFormLocales());

			\HookRegistry::call('User::getProperties::values', [&$values, $user, $props, $args]);

			ksort($values);
		}

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($user, $args = null) {
		$props = ['id','_href','userName','email','fullName','orcid','groups','disabled'];

		\HookRegistry::call('User::getProperties::summaryProperties', [&$props, $user, $args]);

		return $this->getProperties($user, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($user, $args = null) {
		$props = [
			'id','userName','fullName','givenName','familyName','affiliation','country','email','url',
			'orcid','groups','interests','biography','signature','authId','authString','phone',
			'mailingAddress','billingAddress','gossip','disabled','disabledReason',
			'dateRegistered','dateValidated','dateLastLogin','mustChangePassword',
		];

		\HookRegistry::call('User::getProperties::fullProperties', [&$props, $user, $args]);

		return $this->getProperties($user, $props, $args);
	}

	/**
	 * Returns summary properties for a reviewer
	 * @param $user User
	 * @param $args array
	 *		$args['request'] PKPRequest Required
	 *		$args['slimRequest'] SlimRequest
	 * @return array
	 */
	public function getReviewerSummaryProperties($user, $args = null) {
		$props = [
			'id','_href','userName','fullName','affiliation','biography','groups','interests','gossip',
			'reviewsActive','reviewsCompleted','reviewsDeclined','reviewsCancelled','averageReviewCompletionDays',
			'dateLastReviewAssignment','reviewerRating', 'orcid','disabled',
		];

		\HookRegistry::call('User::getProperties::reviewerSummaryProperties', [&$props, $user, $args]);

		return $this->getProperties($user, $props, $args);
	}

	/**
	 * Does a user have a role?
	 *
	 * @param $userId int
	 * @param $roleIds int|array ROLE_ID_...
	 * @param $contextId int
	 * @return boolean
	 */
	public function userHasRole($userId, $roleIds, $contextId) {
		$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */
		return $roleDao->userHasRole($contextId, $userId, $roleIds);
	}

	/**
	 * Can the current user view and edit the gossip field for a user
	 *
	 * @param $userId int The user who's gossip field should be accessed
	 * @return boolean
	 */
	public function canCurrentUserGossip($userId) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		$currentUser = $request->getUser();

		// Logged out users can never view gossip fields
		if (!$currentUser) {
			return false;
		}

		// Users can never view their own gossip fields
		if ($currentUser->getId() === $userId) {
			return false;
		}

		// Only reviewers have gossip fields
		if (!$this->userHasRole($userId, ROLE_ID_REVIEWER, $contextId)) {
			return false;
		}

		// Only admins, editors and subeditors can view gossip fields
		if (!$this->userHasRole($currentUser->getId(), [ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN, ROLE_ID_SUB_EDITOR], $contextId)) {
			return false;
		}

		return true;
	}

	/**
	 * Can this user access the requested workflow stage
	 *
	 * The user must have an assigned role in the specified stage or
	 * be a manager or site admin that has no assigned role in the
	 * submission.
	 *
	 * @param string $stageId One of the WORKFLOW_STAGE_ID_* contstants.
	 * @param string $workflowType Accessing the editorial or author workflow? WORKFLOW_TYPE_*
	 * @param array $userAccessibleStages User's assignments to the workflow stages. ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES
	 * @param array $userRoles User's roles in the context
	 * @return Boolean
	 */
	public function canUserAccessStage($stageId, $workflowType, $userAccessibleStages, $userRoles) {
		$workflowRoles = Application::get()->getWorkflowTypeRoles()[$workflowType];

		if (array_key_exists($stageId, $userAccessibleStages)
			&& !empty(array_intersect($workflowRoles, $userAccessibleStages[$stageId]))) {
				return true;
		}
		if (empty($userAccessibleStages) && in_array(ROLE_ID_MANAGER, $userRoles)) {
			return true;
		}
		return false;
	}

	/**
	 * Check for roles that give access to the passed workflow stage.
	 * @param int $userId
	 * @param int $contextId
	 * @param Submission $submission
	 * @param int $stageId
	 * @return array
	 */
	public function getAccessibleStageRoles($userId, $contextId, &$submission, $stageId) {

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $userId, $stageId);

		$accessibleStageRoles = [];

		// Assigned users have access based on their assignment
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		while ($stageAssignment = $stageAssignmentsResult->next()) {
			$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $contextId);
			$accessibleStageRoles[] = $userGroup->getRoleId();
		}
		$accessibleStageRoles = array_unique($accessibleStageRoles);

		// If unassigned, only managers and admins have access
		if (empty($accessibleStageRoles)) {
			$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */
			$userRoles = $roleDao->getByUserId($userId, $contextId);
			foreach ($userRoles as $userRole) {
				if (in_array($userRole->getId(), [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER])) {
					$accessibleStageRoles[] = $userRole->getId();
				}
			}
			$accessibleStageRoles = array_unique($accessibleStageRoles);
		}

		return array_map('intval', $accessibleStageRoles);
	}

	/**
	 * Get a count of users matching the passed arguments
	 *
	 * @param array $args See self::getMany()
	 */
	public function count($args = []) {
		$qb = $this->getQueryBuilder($args);
		return $qb->getQuery()->get()->count();
	}

	/**
	 * Get a count of users matching the passed arguments broken down
	 * by role
	 *
	 * @param array $args See self::getMany()
	 * @return array List of roles with id, name and total
	 */
	public function getRolesOverview($args = []) {
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_MANAGER);

		// Ignore roles because we'll get all roles in the application
		if (isset($args['roleIds'])) {
			unset($args['roleIds']);
		}

		$result = [
			[
				'id' => 'total',
				'name' => 'stats.allUsers',
				'value' => $this->count($args),
			],
		];

		$roleNames = Application::get()->getRoleNames();

		// Don't include the admin user if we are limiting the overview to one context
		if (!empty($args['contextId'])) {
			unset($roleNames[ROLE_ID_SITE_ADMIN]);
		}

		foreach ($roleNames as $roleId => $roleName) {
			$result[] = [
				'id' => $roleId,
				'name' => $roleName,
				'value' => $this->count(array_merge($args, ['roleIds' => $roleId])),
			];
		}

		return $result;
	}

	/**
	 * Retrieves a filtered user report instance
	 *
	 * @param array $args
	 *		@option int contextId Context ID (required)
	 *		@option int[] userGroupIds List of user groups (all groups by default)
	 * @return Report
	 */
	public function getReport(array $args) : Report {
		$dataSource = \Services::get('user')->getMany([
			'userGroupIds' => $args['userGroupIds'] ?? null,
			'contextId' => $args['contextId']
		]);
		$report = new Report($dataSource);

		\HookRegistry::call('User::getReport', $report);

		return $report;
	}
}

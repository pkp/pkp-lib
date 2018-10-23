<?php
/**
 * @file classes/services/UserService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserService
 * @ingroup services
 *
 * @brief Helper class that encapsulates author business logic
 */

namespace PKP\Services;

use \Application;
use \DBResultRange;
use \DAOResultFactory;
use \DAORegistry;
use \ServicesContainer;
use \PKP\Services\EntityProperties\PKPBaseEntityPropertyService;

class UserService extends PKPBaseEntityPropertyService {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct($this);
	}

	/**
	 * Get users
	 *
	 * @param int $contextId
	 * @param array $args {
	 * 		@option string orderBy
	 * 		@option string orderDirection
	 * 		@option string roleIds
	 * 		@option int assignedToSubmission
	 * 		@option int assignedToSubmissionStage
	 * 		@option int assignedToSection
	 * 		@option array includeUsers
	 * 		@option array excludeUsers
	 * 		@option string status
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 * }
	 *
	 * @return array
	 */
	public function getUsers($contextId, $args = array()) {
		$userListQB = $this->_buildGetUsersQueryObject($contextId, $args);
		$userListQO = $userListQB->get();
		$range = new DBResultRange(isset($args['count'])?$args['count']:0, null, isset($args['offset'])?$args['offset']:0);
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieveRange($userListQO->toSql(), $userListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $userDao, '_returnUserFromRowWithData');

		return $queryResults->toArray();
	}

	/**
	 * Get max count of users matching a query request
	 *
	 * @see self::getSubmissions()
	 * @return int
	 */
	public function getUsersMaxCount($contextId, $args = array()) {
		$userListQB = $this->_buildGetUsersQueryObject($contextId, $args);
		$countQO = $userListQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$userDao = DAORegistry::getDAO('UserDAO');
		$countResult = $userDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $userDao, '_returnUserFromRowWithData');

		return (int) $countQueryResults->getCount();
	}

	/**
	 * Build the user query object for getUsers requests
	 *
	 * @see self::getUsers()
	 * @return object Query object
	 */
	private function _buildGetUsersQueryObject($contextId, $args = array()) {

		$defaultArgs = array(
			'orderBy' => 'id',
			'orderDirection' => 'DESC',
			'roleIds' => null,
			'assignedToSubmission' => null,
			'assignedToSubmissionStage' => null,
			'assignedToSection' => null,
			'includeUsers' => null,
			'excludeUsers' => null,
			'status' => 'active',
			'searchPhrase' => null,
			'count' => 20,
			'offset' => 0,
		);

		$args = array_merge($defaultArgs, $args);

		$userListQB = new QueryBuilders\UserListQueryBuilder($contextId);
		$userListQB
			->orderBy($args['orderBy'], $args['orderDirection'])
			->filterByRoleIds($args['roleIds'])
			->assignedToSubmission($args['assignedToSubmission'], $args['assignedToSubmissionStage'])
			->assignedToSection($args['assignedToSection'])
			->includeUsers($args['includeUsers'])
			->excludeUsers($args['excludeUsers'])
			->filterByStatus($args['status'])
			->searchPhrase($args['searchPhrase']);

		\HookRegistry::call('User::getUsers::queryBuilder', array($userListQB, $contextId, $args));

		return $userListQB;
	}

	/**
	 * Get reviewers
	 *
	 * @see self::getUsers()
	 */
	public function getReviewers($contextId, $args = array()) {
		$userListQB = $this->_buildGetReviewersQueryObject($contextId, $args);
		$userListQO = $userListQB->get();
		$range = $this->getRangeByArgs($args);
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieveRange($userListQO->toSql(), $userListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $userDao, '_returnUserFromRowWithReviewerStats');

		return $queryResults->toArray();
	}

	/**
	 * Get max count of reviewers matching a query request
	 *
	 * @see self::getUsersMaxCount()
	 */
	public function getReviewersMaxCount($contextId, $args = array()) {
		$userListQB = $this->_buildGetReviewersQueryObject($contextId, $args);
		$countQO = $userListQB->countOnly()->get();
		$countRange = new DBResultRange($args['count'], 1);
		$userDao = DAORegistry::getDAO('UserDAO');
		$countResult = $userDao->retrieveRange($countQO->toSql(), $countQO->getBindings(), $countRange);
		$countQueryResults = new DAOResultFactory($countResult, $userDao, '_returnUserFromRowWithReviewerStats');

		return (int) $countQueryResults->getCount();
	}

	/**
	 * Build the reviewers query object for getReviewers requests
	 *
	 * @see self::_buildGetUsersQueryObject()
	 */
	private function _buildGetReviewersQueryObject($contextId, $args = array()) {

		$defaultArgs = array(
			'reviewsCompleted' => null,
			'reviewsActive' => null,
			'daysSinceLastAssignment' => null,
			'averageCompletion' => null,
			'reviewerRating' => null,
		);

		$args = array_merge($defaultArgs, $args);

		$reviewerListQB = $this->_buildGetUsersQueryObject($contextId, $args);
		$reviewerListQB->getReviewerData(true)
			->filterByReviewerRating($args['reviewerRating'])
			->filterByReviewsCompleted($args['reviewsCompleted'])
			->filterByReviewsActive($args['reviewsActive'])
			->filterByDaysSinceLastAssignment($args['daysSinceLastAssignment'])
			->filterByAverageCompletion($args['averageCompletion']);

		\HookRegistry::call('User::getReviewers::queryBuilder', array($reviewerListQB, $contextId, $args));

		return $reviewerListQB;
	}

	/**
	 * Get a single user by ID
	 *
	 * @param $userId int
	 * @return User
	 */
	public function getUser($userId) {
		$userDao = DAORegistry::getDAO('UserDAO');
		$user = $userDao->getById($userId);
		return $user;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($user, $props, $args = null) {
		$request = $args['request'];
		$context = $request->getContext();
		$router = $request->getRouter();

		$values = array();
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
					$values[$prop] = $user->getOrcid(null);
					break;
				case 'biography':
					$values[$prop] = $user->getBiography(null);
					break;
				case 'signature':
					$values[$prop] = $user->getSignature();
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
						$values[$prop] = $router->getApiUrl(
							$args['request'],
							$arguments['contextPath'],
							$arguments['version'],
							'users',
							$user->getId()
						);
					}
					break;
				case 'groups':
					$values[$prop] = null;
					if ($context) {
						import('lib.pkp.classes.security.UserGroupDAO');
						$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
						$userGroups = $userGroupDao->getByUserId($user->getId(), $context->getId());
						$values[$prop] = array();
						while ($userGroup = $userGroups->next()) {
							$values[$prop][] = array(
								'id' => (int) $userGroup->getId(),
								'name' => $userGroup->getName(null),
								'abbrev' => $userGroup->getAbbrev(null),
								'roleId' => (int) $userGroup->getRoleId(),
								'showTitle' => (boolean) $userGroup->getShowTitle(),
								'permitSelfRegistration' => (boolean) $userGroup->getPermitSelfRegistration(),
								'recommendOnly' => (boolean) $userGroup->getRecommendOnly(),
							);
						}
					}
					break;
				case 'interests':
					$values[$prop] = [];
					if ($context) {
						import('lib.pkp.classes.user.InterestDAO');
						$interestDao = DAORegistry::getDAO('InterestDAO');
						$interestEntryIds = $interestDao->getUserInterestIds($user->getId());
						if (!empty($interestEntryIds)) {
							import('lib.pkp.classes.user.InterestEntryDAO');
							$interestEntryDao = DAORegistry::getDAO('InterestEntryDAO');
							$results = $interestEntryDao->getByIds($interestEntryIds);
							$values[$prop] = array();
							while ($interest = $results->next()) {
								$values[$prop][] = array(
									'id' => (int) $interest->getId(),
									'interest' => $interest->getInterest(),
								);
							}
						}
					}
					break;
			}

			$values = ServicesContainer::instance()->get('schema')->addMissingMultilingualValues(SCHEMA_USER, $values, $context->getSupportedLocales());

			\HookRegistry::call('User::getProperties::values', array(&$values, $user, $props, $args));

			ksort($values);
		}

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($user, $args = null) {
		$props = array (
			'id','_href','userName','email','fullName','orcid','groups','disabled',
		);

		\HookRegistry::call('User::getProperties::summaryProperties', array(&$props, $user, $args));

		return $this->getProperties($user, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($user, $args = null) {
		$props = array (
			'id','userName','fullName','givenName','familyName','affiliation','country','email','url',
			'orcid','groups','interests','biography','signature','authId','authString','phone',
			'mailingAddress','billingAddress','gossip','disabled','disabledReason',
			'dateRegistered','dateValidated','dateLastLogin','mustChangePassword',
		);

		\HookRegistry::call('User::getProperties::fullProperties', array(&$props, $user, $args));

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
		$props = array (
			'id','_href','userName','fullName','affiliation','biography','groups','interests','gossip',
			'reviewsActive','reviewsCompleted','reviewsDeclined','averageReviewCompletionDays',
			'dateLastReviewAssignment','reviewerRating', 'orcid','disabled',
		);

		\HookRegistry::call('User::getProperties::reviewerSummaryProperties', array(&$props, $user, $args));

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
		$roleDao = DAORegistry::getDAO('RoleDAO');
		return $roleDao->userHasRole($contextId, $userId, $roleIds);
	}

	/**
	 * Can the current user view and edit the gossip field for a user
	 *
	 * @param $userId int The user who's gossip field should be accessed
	 * @return boolean
	 */
	public function canCurrentUserGossip($userId) {
		$request = Application::getRequest();
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
		if (!$this->userHasRole($currentUser->getId(), array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN, ROLE_ID_SUB_EDITOR), $contextId)) {
			return false;
		}

		return true;
	}
}

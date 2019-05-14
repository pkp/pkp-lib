<?php
/**
 * @file classes/services/PKPUserService.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
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
use \PKP\Services\traits\EntityReadTrait;
use \APP\Services\QueryBuilders\UserQueryBuilder;

class PKPUserService implements EntityPropertyInterface, EntityReadInterface {
	use EntityReadTrait;

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::get()
	 */
	public function get($userId) {
		return DAORegistry::getDAO('UserDAO')->getById($userId);
	}

	/**
	 * Get a collection of users limited, filtered and sorted by $args
	 *
	 * @param array $args
	 *		@option int contextId If not supplied, CONTEXT_ID_NONE will be used and
	 *			no submissions will be returned. To retrieve submissions from all
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
	 * 		@option int count
	 * 		@option int offset
	 * @return array
	 */
	public function getMany($args = array()) {
		$userListQB = $this->_getQueryBuilder($args);
		$userListQO = $userListQB->get();
		$range = $this->getRangeByArgs($args);
		$userDao = DAORegistry::getDAO('UserDAO');
		$result = $userDao->retrieveRange($userListQO->toSql(), $userListQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $userDao, '_returnUserFromRowWithData');

		return $queryResults->toArray();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = array()) {
		$userListQB = $this->_getQueryBuilder($args);
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
	private function _getQueryBuilder($args = array()) {

		$defaultArgs = array(
			'contextId' => CONTEXT_ID_NONE,
			'orderBy' => 'id',
			'orderDirection' => 'DESC',
			'roleIds' => null,
			'assignedToSubmission' => null,
			'assignedToSubmissionStage' => null,
			'includeUsers' => null,
			'excludeUsers' => null,
			'status' => 'active',
			'searchPhrase' => null,
			'count' => 20,
			'offset' => 0,
		);

		$args = array_merge($defaultArgs, $args);

		$userListQB = new UserQueryBuilder();
		$userListQB
			->filterByContext($args['contextId'])
			->orderBy($args['orderBy'], $args['orderDirection'])
			->filterByRoleIds($args['roleIds'])
			->assignedToSubmission($args['assignedToSubmission'], $args['assignedToSubmissionStage'])
			->includeUsers($args['includeUsers'])
			->excludeUsers($args['excludeUsers'])
			->filterByStatus($args['status'])
			->searchPhrase($args['searchPhrase']);

		\HookRegistry::call('User::getMany::queryBuilder', array($userListQB, $args));

		return $userListQB;
	}

	/**
	 * Get reviewers
	 *
	 * @see self::getMeny()
	 */
	public function getReviewers($args = array()) {
		$userListQB = $this->_getReviewersQueryBuilder($args);
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
	 * @see self::getMax()
	 */
	public function getReviewersMax($args = array()) {
		$userListQB = $this->_getReviewersQueryBuilder($args);
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
	 * @see self::_getQueryBuilder()
	 */
	private function _getReviewersQueryBuilder($args = array()) {

		$defaultArgs = array(
			'contextId' => CONTEXT_ID_NONE,
			'reviewsCompleted' => null,
			'reviewsActive' => null,
			'daysSinceLastAssignment' => null,
			'averageCompletion' => null,
			'reviewerRating' => null,
		);

		$args = array_merge($defaultArgs, $args);

		$reviewerListQB = $this->_getQueryBuilder($args);
		$reviewerListQB
			->getReviewerData(true)
			->filterByReviewerRating($args['reviewerRating'])
			->filterByReviewsCompleted($args['reviewsCompleted'])
			->filterByReviewsActive($args['reviewsActive'])
			->filterByDaysSinceLastAssignment($args['daysSinceLastAssignment'])
			->filterByAverageCompletion($args['averageCompletion']);

		\HookRegistry::call('User::getReviewers::queryBuilder', array($reviewerListQB, $args));

		return $reviewerListQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($user, $props, $args = null) {
		$request = $args['request'];
		$context = $request->getContext();
		$dispatcher = $request->getDispatcher();

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

			$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_USER, $values, $context->getSupportedLocales());

			\HookRegistry::call('User::getProperties::values', array(&$values, $user, $props, $args));

			ksort($values);
		}

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($user, $args = null) {
		$props = array (
			'id','_href','userName','email','fullName','orcid','groups','disabled',
		);

		\HookRegistry::call('User::getProperties::summaryProperties', array(&$props, $user, $args));

		return $this->getProperties($user, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
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
		if (!$this->userHasRole($currentUser->getId(), array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN, ROLE_ID_SUB_EDITOR), $contextId)) {
			return false;
		}

		return true;
	}
}

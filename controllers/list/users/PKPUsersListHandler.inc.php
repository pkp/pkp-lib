<?php
/**
 * @file controllers/list/users/PKPUsersListHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUsersListHandler
 * @ingroup controllers_list
 *
 * @brief Instantiates and manages a UI component to list users.
 */
import('lib.pkp.controllers.list.ListHandler');
import('lib.pkp.classes.db.DAORegistry');
import('classes.user.User');
import('classes.core.ServicesContainer');

class PKPUsersListHandler extends ListHandler {

	/** @var int Count of items to retrieve in initial page/request */
	public $_count = 40;

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array(
		'status' => 'all',
		'orderBy' => 'familyName',
		'orderDirection' => 'ASC',
	);

	/** @var string Used to generate URLs to API endpoints for this component. */
	public $_apiPath = 'users';

	/** @var null|string Used to generate URLs to API endpoints for this component. */
	public $_apiContextPath = null;

	/** @var null|string Context ID for which to load this component. */
	public $_contextId = null;

	/** @var int User ID to merge into another user. */
	public $_mergeUserSourceId = 0;

	/** @var boolean Is this user list for the site-wide admin area */
	public $_isSiteAdmin = false;

	/**
	 * @copydoc ListHandler::init()
	 */
	public function init( $args = array() ) {
		parent::init($args);

		$this->_count = isset($args['count']) ? (int) $args['count'] : $this->_count;
		$this->_getParams = isset($args['getParams']) ? $args['getParams'] : $this->_getParams;
		$this->_mergeUserSourceId = isset($args['mergeUserSourceId']) ? $args['mergeUserSourceId'] : $this->_mergeUserSourceId;
		$this->_apiContextPath = isset($args['apiContextPath']) ? $args['apiContextPath'] : $this->_apiContextPath;
		$this->_isSiteAdmin = isset($args['isSiteAdmin']) ? $args['isSiteAdmin'] : $this->_isSiteAdmin;

		if ($this->_apiContextPath === CONTEXT_ID_NONE_API) {
			$this->_contextId = CONTEXT_ID_NONE;
		} else {
			$context = Application::getRequest()->getContext();
			if (!is_null($this->_apiContextPath) && $this->_apiContextPath !== $context->getPath()) {
				$contextDao = Application::getContextDAO();
				$context = $contextDao->getByPath($this->_apiContextPath);
				assert(is_a($context, 'Context'));
			}
			$this->_contextId = $context->getId();
		}
	}

	/**
	 * @copydoc ListHandler::getConfig()
	 */
	public function getConfig() {

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_DEFAULT);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_USER);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_USER);

		$config = array();

		if ($this->_lazyLoad) {
			$config['lazyLoad'] = true;
		} else {
			$config['items'] = $this->getItems();
			$config['itemsMax'] = $this->getItemsMax();
		}

		$config['apiPath'] = $this->_apiPath;
		if (!empty($this->_apiContextPath)) {
			$config['apiContextPath'] = $this->_apiContextPath;
		}

		$config['count'] = $this->_count;
		$config['page'] = 1;
		$config['getParams'] = $this->_getParams;

		$config['isSiteAdmin'] = $this->_isSiteAdmin;
		$config['mergeUserSourceId'] = $this->_mergeUserSourceId;

		$config['filters'] = array(
			'status' => array(
				'heading' => __('common.status'),
				'filters' => array(
					array(
						'param' => 'status',
						'val' => 'active',
						'title' => __('common.active'),
					),
					array(
						'param' => 'status',
						'val' => 'disabled',
						'title' => __('common.disabled'),
					),
				),
			),
		);

		if ($this->_contextId) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$userGroupsResult = $userGroupDao->getByContextId($this->_contextId);
			$userGroups = array();
			while ($userGroup = $userGroupsResult->next()) {
				$userGroups[] = array(
					'param' => 'userGroupIds',
					'val' => (int) $userGroup->getId(),
					'title' => $userGroup->getLocalizedName(),
				);
			}
			$config['filters']['userGroups'] = array(
				'heading' => __('user.roles'),
				'filters' => $userGroups,
			);
		} else {
			$config['filters']['roles'] = array(
				'heading' => __('user.roles'),
				'filters' => array(
					array(
						'param' => 'userGroupIds',
						'val' => 0,
						'title' => __('user.roles.noneAssigned'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_SITE_ADMIN,
						'title' => __('default.groups.name.siteAdmin'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_MANAGER,
						'title' => __('default.groups.plural.manager'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_SUB_EDITOR,
						'title' => __('admin.roles.subeditors'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_ASSISTANT,
						'title' => __('user.role.assistants'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_SUBSCRIPTION_MANAGER,
						'title' => __('default.groups.plural.subscriptionManager'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_AUTHOR,
						'title' => __('default.groups.plural.author'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_REVIEWER,
						'title' => __('user.role.reviewers'),
					),
					array(
						'param' => 'roleIds',
						'val' => ROLE_ID_READER,
						'title' => __('user.role.readers'),
					),
				),
			);

			$contextDao = Application::getContextDAO();
			$contextsResult = $contextDao->getAll();
			$config['filters']['contextIds'] = array(
				'heading' => __('journal.journals'),
				'filters' => array(),
				'isAutoSuggest' => true,
			);
			while ($context = $contextsResult->next()) {
				$config['filters']['contextIds']['filters'][] = array(
					'param' => 'contextIds',
					'val' => (int) $context->getId(),
					'title' => $context->getLocalizedName(),
					'acronym' => $context->getLocalizedAcronym(),
				);
			}
		}

		$request = Application::getRequest();

		$config['addUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'addUser',
			'__id__'
		);

		$config['loginAsUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'login',
			'signInAsUser',
			'__id__'
		);

		$config['logoutAsUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'login',
			'signOutAsUser'
		);

		$config['sendEmailUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'editEmail',
			null,
			array('rowId' => '__id__')
		);

		$config['editUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'editUser',
			null,
			array('rowId' => '__id__')
		);

		$config['assignUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'showRoleContextSelection',
			null,
			array('userId' => '__id__')
		);

		$config['enableUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'editDisableUser',
			null,
			array('rowId' => '__id__', 'enable' => true)
		);

		$config['disableUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'editDisableUser',
			null,
			array('rowId' => '__id__', 'enable' => false)
		);

		$config['removeUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'removeUser',
			null,
			array('rowId' => '__id__', 'csrfToken' => $request->getSession()->getCSRFToken())
		);

		$config['mergeUserUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.settings.user.UserGridHandler',
			'mergeUsers',
			null,
			array('rowId' => '__id__', 'oldUserId' => '__id__')
		);

		$config['i18n'] = array(
			'title' => __($this->_title),
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'itemCount' => __('user.list.count'),
			'itemsOfTotal' => __('user.list.itemsOfTotal'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
			'addUser' => __('grid.user.add'),
			'disabled' => __('common.disabled'),
			'filter' => __('common.filter'),
			'filterRemove' => __('common.filterRemove'),
			'orcid' => __('plugins.generic.orcidProfile.fieldset'),
			'loginAs' => __('grid.action.logInAs'),
			'logoutAs' => __('user.logOutAs'),
			'loginAsDescription' => __('grid.user.confirmLogInAs'),
			'listSeparator' => __('common.listSeparator'),
			'sendEmail' => __('common.sendEmail'),
			'editUser' => __('grid.user.edit'),
			'assignUser' => __('user.list.assignUser'),
			'enableUser' => __('common.enable'),
			'disableUser' => __('grid.user.disable'),
			'removeUser' => $this->_contextId ? __('common.remove') : __('user.list.removeRolesAll'),
			'removeUserConfirmation' => $this->_contextId ? __('manager.people.confirmRemove') : __('manager.people.confirmRemoveAll'),
			'confirm' => __('common.ok'),
			'cancel' => __('common.cancel'),
			'mergeUser' => __('grid.user.mergeUsers.mergeUser'),
			'mergeIntoUser' => __('grid.user.mergeUsers.mergeIntoUser'),
		);

		return $config;
	}

	/**
	 * @copydoc ListHandler::getItems()
	 */
	public function getItems() {
		$userService = ServicesContainer::instance()->get('user');
		$users = $userService->getUsers($this->_contextId, $this->_getItemsParams());
		$items = array();
		if (!empty($users)) {
			foreach ($users as $user) {
				$items[] = $userService->getSummaryProperties($user, array(
					'request' => Application::getRequest(),
				));
			}
		}

		return $items;
	}

	/**
	 * @copydoc ListHandler::getItemsMax()
	 */
	public function getItemsMax() {
		return ServicesContainer::instance()
			->get('user')
			->getUsersMaxCount($this->_contextId, $this->_getItemsParams());
	}

	/**
	 * @copydoc ListHandler::_getItemsParams()
	 */
	protected function _getItemsParams() {
		return array_merge(
			array(
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);
	}
}

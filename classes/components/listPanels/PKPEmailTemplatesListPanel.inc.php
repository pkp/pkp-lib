<?php
/**
 * @file classes/components/listPanels/PKPEmailTemplatesListPanel.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailTemplatesListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for viewing and editing email templates
 */
namespace PKP\components\listPanels;
use PKP\components\listPanels;

class PKPEmailTemplatesListPanel extends ListPanel {
	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER);
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER);
		\AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT);

		$config = parent::getConfig();

		$config['addItemUrl'] = \Application::get()->getRequest()->getDispatcher()->url(
			\Application::get()->getRequest(),
			ROUTE_COMPONENT,
			null,
			'grid.settings.preparedEmails.PreparedEmailsGridHandler',
			'addPreparedEmail',
			null
		);

		$config['editItemUrl'] = \Application::get()->getRequest()->getDispatcher()->url(
			\Application::get()->getRequest(),
			ROUTE_COMPONENT,
			null,
			'grid.settings.preparedEmails.PreparedEmailsGridHandler',
			'editPreparedEmail',
			null,
			['emailKey' => '__key__']
		);

		$config['i18n'] = array_merge($config['i18n'], [
			'add' => __('manager.emails.addEmail'),
			'cancel' => __('common.cancel'),
			'delete' => __('common.delete'),
			'deleteConfirm' => __('manager.emails.confirmDelete'),
			'disable' => __('common.disable'),
			'disabled' => __('common.disabled'),
			'edit' => __('common.edit'),
			'editTemplate' => __('manager.emails.editEmail'),
			'enable' => __('common.enable'),
			'from' => __('common.fromWithValue'),
			'goToLabel' => __('common.pagination.goToPage'),
			'nextPageLabel' => __('common.pagination.next'),
			'ok' => __('common.ok'),
			'pageLabel' => __('common.pageNumber'),
			'paginationLabel' => __('common.pagination.label'),
			'previousPageLabel' => __('common.pagination.previous'),
			'reset' => __('manager.emails.reset'),
			'resetConfirm' => __('manager.emails.confirmReset'),
			'resetAll' => __('manager.emails.resetAll'),
			'resetAllConfirm' => __('manager.emails.resetAll.message'),
			'subjectLabel' => __('manager.emails.subjectWithValue'),
			'to' => __('common.toWithValue'),
		]);

		$config['roles'] = [
			ROLE_ID_MANAGER => __('user.role.editor'),
			ROLE_ID_SITE_ADMIN => __('user.role.siteAdmin'),
			ROLE_ID_SUB_EDITOR => __('default.groups.name.sectionEditor'),
			ROLE_ID_AUTHOR => __('user.role.author'),
			ROLE_ID_REVIEWER => __('user.role.reviewer'),
			ROLE_ID_ASSISTANT => __('user.role.assistant'),
			ROLE_ID_READER => __('user.role.reader'),
			ROLE_ID_SUBSCRIPTION_MANAGER => __('default.groups.name.subscriptionManager'),
		];

		$config['csrfToken'] = \Application::get()->getRequest()->getSession()->getCSRFToken();

		$config['filters'] = [
			[
				'filters' => [
					[
						'param' => 'isEnabled',
						'title' => __('common.enabled'),
						'value' => 1,
					],
					[
						'param' => 'isEnabled',
						'title' => __('common.disabled'),
						'value' => 0,
					],
					[
						'param' => 'isCustom',
						'title' => __('manager.emails.customTemplate'),
						'value' => 1,
					],
				],
			],
			[
				'heading' => __('manager.emails.sentFrom'),
				'filters' => [
					[
						'param' => 'fromRoleIds',
						'title' => __('user.role.editor'),
						'value' => ROLE_ID_MANAGER,
					],
					[
						'param' => 'fromRoleIds',
						'title' => __('user.role.reviewer'),
						'value' => ROLE_ID_REVIEWER,
					],
					[
						'param' => 'fromRoleIds',
						'title' => __('user.role.assistant'),
						'value' => ROLE_ID_ASSISTANT,
					],
					[
						'param' => 'fromRoleIds',
						'title' => __('user.role.reader'),
						'value' => ROLE_ID_READER,
					],
				],
			],
			[
				'heading' => __('manager.emails.sentTo'),
				'filters' => [
					[
						'param' => 'toRoleIds',
						'title' => __('user.role.editor'),
						'value' => ROLE_ID_MANAGER,
					],
					[
						'param' => 'toRoleIds',
						'title' => __('user.role.reviewer'),
						'value' => ROLE_ID_REVIEWER,
					],
					[
						'param' => 'toRoleIds',
						'title' => __('user.role.assistant'),
						'value' => ROLE_ID_ASSISTANT,
					],
					[
						'param' => 'toRoleIds',
						'title' => __('user.role.author'),
						'value' => ROLE_ID_AUTHOR,
					],
					[
						'param' => 'toRoleIds',
						'title' => __('user.role.reader'),
						'value' => ROLE_ID_READER,
					],
				],
			],
		];

		return $config;
	}
}

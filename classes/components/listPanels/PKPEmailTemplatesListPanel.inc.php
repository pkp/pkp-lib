<?php
/**
 * @file classes/components/listPanels/PKPEmailTemplatesListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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

    $config['addItemUrl'] = \Application::getRequest()->getDispatcher()->url(
      \Application::getRequest(),
      ROUTE_COMPONENT,
      null,
      'grid.settings.preparedEmails.PreparedEmailsGridHandler',
      'addPreparedEmail',
      null
    );

    $config['editItemUrl'] = \Application::getRequest()->getDispatcher()->url(
      \Application::getRequest(),
      ROUTE_COMPONENT,
      null,
      'grid.settings.preparedEmails.PreparedEmailsGridHandler',
      'editPreparedEmail',
      null,
      ['emailKey' => '__key__']
    );

    $config['i18n']['add'] = __('manager.emails.addEmail');
    $config['i18n']['cancel'] = __('common.cancel');
    $config['i18n']['delete'] = __('common.delete');
    $config['i18n']['deleteConfirm'] = __('manager.emails.confirmDelete');
    $config['i18n']['disable'] = __('common.disable');
    $config['i18n']['disabled'] = __('common.disabled');
    $config['i18n']['edit'] = __('common.edit');
    $config['i18n']['editTemplate'] = __('manager.emails.editEmail');
    $config['i18n']['enable'] = __('common.enable');
    $config['i18n']['from'] = __('common.fromWithValue');
    $config['i18n']['ok'] = __('common.ok');
    $config['i18n']['reset'] = __('manager.emails.reset');
    $config['i18n']['resetConfirm'] = __('manager.emails.confirmReset');
    $config['i18n']['resetAll'] = __('manager.emails.resetAll');
    $config['i18n']['resetAllConfirm'] = __('manager.emails.resetAll.message');
    $config['i18n']['subjectLabel'] = __('manager.emails.subjectWithValue');
    $config['i18n']['to'] = __('common.toWithValue');

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

		$config['csrfToken'] = \Application::getRequest()->getSession()->getCSRFToken();

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
            'title' => __('default.groups.name.sectionEditor'),
            'value' => ROLE_ID_SUB_EDITOR,
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
          [
            'param' => 'toRoleIds',
            'title' => __('default.groups.name.subscriptionManager'),
            'value' => ROLE_ID_SUBSCRIPTION_MANAGER,
          ],
        ],
      ],
    ];

		return $config;
	}
}

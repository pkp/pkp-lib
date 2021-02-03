<?php
/**
 * @file classes/components/form/statistics/users/ReportForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AssignToIssueForm
 * @ingroup classes_controllers_form
 *
 * @brief A preset form for configuring the users report.
 */
namespace PKP\components\forms\statistics\users;
use \PKP\components\forms\{FormComponent, FieldOptions};

class ReportForm extends FormComponent {
	/**
	 * Constructor
	 *
	 * @param string $action URL to submit the form to
	 * @param \Context $context The context
	 */
	public function __construct(string $action, \Context $context) {
		$this->action = $action;
		$this->id = 'reportForm';
		$this->method = 'POST';

		$this->addPage(['id' => 'default', 'submitButton' => array('label' => __('common.export'))]);
		$this->addGroup(['id' => 'default', 'pageId' => 'default']);

		$userGroups = iterator_to_array(\DAORegistry::getDAO('UserGroupDAO')->getByContextId($context->getId())->toIterator());
		$this->addField(new FieldOptions('userGroupIds', [
			'groupId' => 'default',
			'label' => __('user.group'),
			'description' => __('manager.export.usersToCsv.description'),
			'options' => array_map(function ($userGroup) {
				return [
					'value' => $userGroup->getId(),
					'label' => $userGroup->getLocalizedName()
				];
			}, $userGroups),
			'default' => array_map(function ($userGroup) {
				return $userGroup->getId();
			}, $userGroups)
		]));
	}
}

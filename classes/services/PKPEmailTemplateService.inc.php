<?php
/**
 * @file classes/services/PKPEmailTemplateService.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailTemplateService
 * @ingroup services
 *
 * @brief Helper class that encapsulates business logic for email templates
 */

namespace PKP\Services;

use \Application;
use \DAOResultFactory;
use \DAORegistry;
use \DBResultRange;
use \HookRegistry;
use \Services;
use \PKP\Services\interfaces\EntityPropertyInterface;
use \PKP\Services\interfaces\EntityReadInterface;
use \PKP\Services\interfaces\EntityWriteInterface;
use \PKP\Services\traits\EntityReadTrait;
use \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder;

import('lib.pkp.classes.db.DBResultRange');

class PKPEmailTemplateService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface {
	use EntityReadTrait;

	/**
	 * Do not use. An email template should be retrieved by its key.
	 *
	 * @see PKPEmailTemplateService::getByKey()
	 */
	public function get($emailTemplateId) {
		throw new Exception('Use the PKPEmailTemplateService::getByKey() method to retrieve an email template.');
	}

	/**
	 * Get an email template by key
	 *
	 * Returns a custom email template if one exists for the requested context or
	 * the default template if no custom template exists.
	 *
	 * Returns null if no template is found for the requested key
	 *
	 * @param integer $contextId
	 * @param string $key
	 * @return EmailTemplate
	 */
	public function getByKey($contextId, $key) {
		$emailTemplateQB = new PKPEmailTemplateQueryBuilder();
		$emailTemplateQO = $emailTemplateQB
			->filterByContext($contextId)
			->filterByKeys([$key])
			->get();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$result = $emailTemplateDao->retrieve($emailTemplateQO->toSql(), $emailTemplateQO->getBindings());
		if ($result->RecordCount() !== 0) {
			$emailTemplate = $emailTemplateDao->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $emailTemplate ? $emailTemplate : null;
	}

	/**
	 * Get email templates
	 *
	 * @param array $args {
	 * 		@option bool isEnabled
	 * 		@option int|array fromRoleIds
	 * 		@option int|array toRoleIds
	 * 		@option string searchPhrase
	 * 		@option int count
	 * 		@option int offset
	 * }
	 * @return array
	 */
	public function getMany($args = array()) {
		$emailTemplateQB = $this->_getQueryBuilder($args);
		$emailTemplateQO = $emailTemplateQB->get();
		$range = $this->getRangeByArgs($args);
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$result = $emailTemplateDao->retrieveRange($emailTemplateQO->toSql(), $emailTemplateQO->getBindings(), $range);
		$queryResults = new DAOResultFactory($result, $emailTemplateDao, '_fromRow');

		return $queryResults->toArray();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = array()) {
		$emailTemplateQB = $this->_getQueryBuilder($args);
		return $emailTemplateQB->getCount();
	}

	/**
	 * Build the query object for getting email templates
	 *
	 * @see self::getMany()
	 * @return object Query object
	 */
	private function _getQueryBuilder($args = array()) {
		$context = Application::getRequest()->getContext();

		$defaultArgs = array(
			'contextId' => $context ? $context->getId() : CONTEXT_SITE,
			'isEnabled' => null,
			'fromRoleIds' => null,
			'toRoleIds' => null,
			'searchPhrase' => null,
		);

		$args = array_merge($defaultArgs, $args);

		$emailTemplateQB = new PKPEmailTemplateQueryBuilder();
		$emailTemplateQB
			->filterByContext($args['contextId'])
			->filterByIsEnabled($args['isEnabled'])
			->filterByFromRoleIds($args['fromRoleIds'])
			->filterByToRoleIds($args['toRoleIds'])
			->searchPhrase($args['searchPhrase']);

		HookRegistry::call('EmailTemplate::getMany::queryBuilder', array($emailTemplateQB, $args));

		return $emailTemplateQB;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getProperties()
	 */
	public function getProperties($emailTemplate, $props, $args = null) {

		$values = array();

		foreach ($props as $prop) {
			switch ($prop) {
				case '_href':
					$values[$prop] = null;
					if (!empty($args['slimRequest']) &&
							!empty($args['request']) &&
							$emailTemplate->getData('assocType') === Application::getContextAssocType()) {
						$emailTemplateContext = Services::get('context')->get($emailTemplate->getData('assocId'));

						$values[$prop] = $args['request']->getDispatcher()->url(
							$args['request'],
							ROUTE_API,
							$emailTemplateContext->getData('urlPath'),
							'emailTemplates/' . $emailTemplate->getData('key')
						);
					}
					break;
				default:
					$values[$prop] = $emailTemplate->getData($prop);
					break;
			}
		}

		if ($args['supportedLocales']) {
			$values = Services::get('schema')->addMissingMultilingualValues(SCHEMA_EMAIL_TEMPLATE, $values, $args['supportedLocales']);
		}

		HookRegistry::call('EmailTemplate::getProperties', array(&$values, $emailTemplate, $props, $args));

		ksort($values);

		return $values;
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getSummaryProperties()
	 */
	public function getSummaryProperties($emailTemplate, $args = null) {
		$props = Services::get('schema')->getSummaryProps(SCHEMA_EMAIL_TEMPLATE);

		return $this->getProperties($emailTemplate, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityPropertyInterface::getFullProperties()
	 */
	public function getFullProperties($emailTemplate, $args = null) {
		$props = Services::get('schema')->getFullProps(SCHEMA_EMAIL_TEMPLATE);

		return $this->getProperties($emailTemplate, $props, $args);
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::validate()
	 */
	public function validate($action, $props, $allowedLocales, $primaryLocale) {
		$schemaService = Services::get('schema');

		import('lib.pkp.classes.validation.ValidatorFactory');
		$validator = \ValidatorFactory::make(
			$props,
			$schemaService->getValidationRules(SCHEMA_EMAIL_TEMPLATE, $allowedLocales)
		);

		if ($action === VALIDATE_ACTION_ADD) {
			// Check required fields when adding a context
			\ValidatorFactory::required(
				$validator,
				$schemaService->getRequiredProps(SCHEMA_EMAIL_TEMPLATE),
				$schemaService->getMultilingualProps(SCHEMA_EMAIL_TEMPLATE),
				$primaryLocale
			);

			// Require an assoc type and id when adding a context
			$validator->after(function($validator) use ($props) {
				if (!isset($props['assocType']) || !isset($props['assocId'])) {
					$validator->errors()->add('assocType', __('manager.emails.emailTemplate.assocTypeRequired'));
				}
			});
		}

		// Check for input from disallowed locales
		\ValidatorFactory::allowedLocales($validator, $schemaService->getMultilingualProps(SCHEMA_EMAIL_TEMPLATE), $allowedLocales);

		if ($validator->fails()) {
			$errors = $schemaService->formatValidationErrors($validator->errors(), $schemaService->get(SCHEMA_EMAIL_TEMPLATE), $allowedLocales);
		}

		HookRegistry::call('EmailTemplate::validate', array(&$errors, $action, $props, $allowedLocales, $primaryLocale));

		return $errors;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::add()
	 */
	public function add($emailTemplate, $request) {
		if ($emailTemplate->getData('assocType') === Application::getContextAssocType()) {
			$contextId = $emailTemplate->getData('assocType');
		} else {
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_SITE;
		}

		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->insertObject($emailTemplate);
		$emailTemplate = $emailTemplateDao->getByKey($contextId, $emailTemplate->getData('key'));

		HookRegistry::call('EmailTemplate::add', array($emailTemplate, $request));

		return $emailTemplate;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($emailTemplate, $params, $request) {
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO');
		$newEmailTemplate = $emailTemplateDao->newDataObject();
		$newEmailTemplate->_data = array_merge($emailTemplate->_data, $params);

		HookRegistry::call('EmailTemplate::edit', array($newEmailTemplate, $emailTemplate, $params, $request));

		$emailTemplateDao->updateObject($newEmailTemplate);

		if ($newEmailTemplate->getData('assocType') === Application::getContextAssocType()) {
			$contextId = $emailTemplate->getData('assocType');
		} else {
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_SITE;
		}

		$newEmailTemplate = $this->getByKey($contextId, $newEmailTemplate->getData('key'));

		return $newEmailTemplate;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::delete()
	 */
	public function delete($emailTemplate) {
		HookRegistry::call('EmailTemplate::delete::before', array($emailTemplate));
		DAORegistry::getDAO('EmailTemplateDAO')->deleteObject($emailTemplate);
		HookRegistry::call('EmailTemplate::delete', array($emailTemplate));
	}
}

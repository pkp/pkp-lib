<?php
/**
 * @file classes/services/PKPEmailTemplateService.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
use \PKP\Services\QueryBuilders\PKPEmailTemplateQueryBuilder;

import('lib.pkp.classes.db.DBResultRange');

class PKPEmailTemplateService implements EntityPropertyInterface, EntityReadInterface, EntityWriteInterface {

	/**
	 * Do not use. An email template should be retrieved by its key.
	 *
	 * @see PKPEmailTemplateService::getByKey()
	 */
	public function get($emailTemplateId) {
		throw new \Exception('Use the PKPEmailTemplateService::getByKey() method to retrieve an email template.');
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
		$emailTemplateQueryParts = $emailTemplateQB
			->filterByContext($contextId)
			->filterByKeys([$key])
			->getCompiledQuery();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$result = $emailTemplateDao->retrieve($emailTemplateQueryParts[0], $emailTemplateQueryParts[1]);
		if ($result->RecordCount() !== 0) {
			$emailTemplate = $emailTemplateDao->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return !empty($emailTemplate) ? $emailTemplate : null;
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
		throw new \Exception('PKPEmailTemplateService::getIds() is not supported. Email templates should be referenced by key instead of id.');
	}

	/**
	 * Get email templates
	 *
	 * @param array $args {
	 * 		@option bool isEnabled
	 * 		@option int|array fromRoleIds
	 * 		@option int|array toRoleIds
	 * 		@option string searchPhrase
	 * }
	 * @return Iterator
	 */
	public function getMany($args = []) {
		$emailTemplateQueryParts = $this->getQueryBuilder($args)->getCompiledQuery();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$result = $emailTemplateDao->retrieveRange($emailTemplateQueryParts[0], $emailTemplateQueryParts[1]);
		$queryResults = new DAOResultFactory($result, $emailTemplateDao, '_fromRow');

		return $queryResults->toIterator();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getMax()
	 */
	public function getMax($args = []) {
		// Count/offset is not supported so getMax is always
		// the same as getCount
		return $this->getCount();
	}

	/**
	 * @copydoc \PKP\Services\interfaces\EntityReadInterface::getQueryBuilder()
	 * @return PKPEmailTemplateQueryBuilder
	 */
	public function getQueryBuilder($args = []) {
		$context = Application::get()->getRequest()->getContext();

		$defaultArgs = array(
			'contextId' => $context ? $context->getId() : CONTEXT_SITE,
			'isEnabled' => null,
			'isCustom' => null,
			'fromRoleIds' => null,
			'toRoleIds' => null,
			'searchPhrase' => null,
		);

		$args = array_merge($defaultArgs, $args);

		$emailTemplateQB = new PKPEmailTemplateQueryBuilder();
		$emailTemplateQB
			->filterByContext($args['contextId'])
			->filterByIsEnabled($args['isEnabled'])
			->filterByIsCustom($args['isCustom'])
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
					if ($emailTemplate->getData('contextId')) {
						$context = Services::get('context')->get($emailTemplate->getData('contextId'));
					} else {
						$context = $args['request']->getContext();
					}
					$values[$prop] = $args['request']->getDispatcher()->url(
						$args['request'],
						ROUTE_API,
						$context->getData('urlPath'),
						'emailTemplates/' . $emailTemplate->getData('key')
					);
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

		\AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER
		);

		// Check required fields
		\ValidatorFactory::required(
			$validator,
			$action,
			$schemaService->getRequiredProps(SCHEMA_EMAIL_TEMPLATE),
			$schemaService->getMultilingualProps(SCHEMA_EMAIL_TEMPLATE),
			$allowedLocales,
			$primaryLocale
		);

		if ($action === VALIDATE_ACTION_ADD) {

			// Require a context id
			$validator->after(function($validator) use ($props) {
				if (!isset($props['contextId'])) {
					$validator->errors()->add('contextId', __('manager.emails.emailTemplate.contextRequired'));
				}
			});

			// Don't allow duplicate keys in the same context
			$validator->after(function($validator) use ($props) {
				if (!isset($props['contextId'])) {
					return;
				}
				$existingEmailTemplate = $this->getByKey($props['contextId'], $props['key']);
				if (!empty($existingEmailTemplate) && !empty($existingEmailTemplate->getData('id'))) {
					$validator->errors()->add('key', __('manager.emails.emailTemplate.noDuplicateKeys'));
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
		if ($emailTemplate->getData('contextId')) {
			$contextId = $emailTemplate->getData('contextId');
		} else {
			$context = $request->getContext();
			$contextId = $context ? $context->getId() : CONTEXT_SITE;
		}

		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$emailTemplateDao->insertObject($emailTemplate);
		$emailTemplate = $this->getByKey($contextId, $emailTemplate->getData('key'));

		HookRegistry::call('EmailTemplate::add', array($emailTemplate, $request));

		return $emailTemplate;
	}

	/**
	 * @copydoc \PKP\Services\EntityProperties\EntityWriteInterface::edit()
	 */
	public function edit($emailTemplate, $params, $request) {
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$newEmailTemplate = $emailTemplateDao->newDataObject();
		$newEmailTemplate->_data = array_merge($emailTemplate->_data, $params);

		HookRegistry::call('EmailTemplate::edit', array($newEmailTemplate, $emailTemplate, $params, $request));

		$emailTemplateKey = $emailTemplate->getData('key');

		// When editing a default template for the first time, we must insert a new entry
		// in the email_templates table.
		if ($newEmailTemplate->getData('id')) {
			$emailTemplateDao->updateObject($newEmailTemplate);
		} else {
			$emailTemplateDao->insertObject($newEmailTemplate);
		}

		if ($newEmailTemplate->getData('contextId')) {
			$contextId = $newEmailTemplate->getData('contextId');
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
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$emailTemplateDao->deleteObject($emailTemplate);
		HookRegistry::call('EmailTemplate::delete', array($emailTemplate));
	}

	/**
	 * Remove all custom templates and template modifications. Resets the
	 * email template settings to their installed defaults.
	 *
	 * @return array List of keys that were deleted or reset
	 */
	public function restoreDefaults($contextId) {
		$emailTemplateQB = new PKPEmailTemplateQueryBuilder();
		$emailTemplateQB->filterByContext($contextId);
		$emailTemplateQO = $emailTemplateQB->getModified();
		$emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /* @var $emailTemplateDao EmailTemplateDAO */
		$result = $emailTemplateDao->retrieve($emailTemplateQO->toSql(), $emailTemplateQO->getBindings());
		$queryResults = new DAOResultFactory($result, $emailTemplateDao, '_fromRow');
		$deletedKeys = [];
		while ($emailTemplate = $queryResults->next()) {
			$deletedKeys[] = $emailTemplate->getData('key');
			$this->delete($emailTemplate);
		}
		HookRegistry::call('EmailTemplate::restoreDefaults', array(&$deletedKeys, $contextId));
		return $deletedKeys;
	}
}

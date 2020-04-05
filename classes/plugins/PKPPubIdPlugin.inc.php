<?php

/**
 * @file classes/plugins/PKPPubIdPlugin.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPubIdPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for public identifiers plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class PKPPubIdPlugin extends LazyLoadPlugin {

	//
	// Implement template methods from Plugin
	//
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (!parent::register($category, $path, $mainContextId)) return false;
		if ($this->getEnabled($mainContextId)) {
			// Enable storage of additional fields.
			foreach($this->getDAOs() as $dao) {
				// Augment the object with the additional properties required by the pub ID plugin.
				if ($dao instanceof SchemaDAO) {
					// Schema-backed DAOs need the schema extended.
					HookRegistry::register('Schema::get::' . $dao->schemaName, array($this, 'addToSchema'));
				} else {
					// For non-schema-backed DAOs, DAOName::getAdditionalFieldNames can be used.
					HookRegistry::register(strtolower_codesafe(get_class($dao)).'::getAdditionalFieldNames', array($this, 'getAdditionalFieldNames'));
					if (strtolower_codesafe(get_class($dao)) == 'submissionfiledao') {
						// if it is a file, consider all file delegates
						$fileDAOdelegates = $this->getFileDAODelegates();
						foreach ($fileDAOdelegates as $fileDAOdelegate) {
							HookRegistry::register(strtolower_codesafe($fileDAOdelegate).'::getAdditionalFieldNames', array($this, 'getAdditionalFieldNames'));
						}
					}
				}
			}
		}
		$this->addLocaleData();
		return true;
	}

	/**
	 * @copydoc Plugin::getActions()
	 */
	function getActions($request, $actionArgs) {
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		return array_merge(
			$this->getEnabled()?array(
				new LinkAction(
					'settings',
					new AjaxModal(
						$router->url($request, null, null, 'manage', null, $actionArgs),
						$this->getDisplayName()
					),
					__('manager.plugins.settings'),
					null
				),
			):array(),
			parent::getActions($request, $actionArgs)
		);
	}

 	/**
	 * @copydoc Plugin::manage()
	 */
	function manage($args, $request) {
		$user = $request->getUser();
		$router = $request->getRouter();
		$context = $router->getContext($request);

		$form = $this->instantiateSettingsForm($context->getId());
		$notificationManager = new NotificationManager();
		switch ($request->getUserVar('verb')) {
			case 'save':
				$form->readInputData();
				if ($form->validate()) {
					$form->execute();
					$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS);
					return new JSONMessage(true);
				}
				return new JSONMessage(true, $form->fetch($request));
			case 'clearPubIds':
				if (!$request->checkCSRF()) return new JSONMessage(false);
				$contextDao = Application::getContextDAO();
				$contextDao->deleteAllPubIds($context->getId(), $this->getPubIdType());
				return new JSONMessage(true);
			default:
				$form->initData();
				return new JSONMessage(true, $form->fetch($request));
		}
		return parent::manage($args, $request);
	}


	//
	// Protected template methods to be implemented by sub-classes.
	//
	/**
	 * Get the public identifier.
	 * @param $pubObject object
	 * 	Submission, Representation, SubmissionFile + OJS Issue
	 * @return string
	 */
	abstract function getPubId($pubObject);

	/**
	 * Construct the public identifier from its prefix and suffix.
	 * @param $pubIdPrefix string
	 * @param $pubIdSuffix string
	 * @param $contextId integer
	 * @return string
	 */
	abstract function constructPubId($pubIdPrefix, $pubIdSuffix, $contextId);

	/**
	 * Public identifier type, see
	 * http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html
	 * @return string
	 */
	abstract function getPubIdType();

	/**
	 * Public identifier type that will be displayed to the reader.
	 * @return string
	 */
	abstract function getPubIdDisplayType();

	/**
	 * Full name of the public identifier.
	 * @return string
	 */
	abstract function getPubIdFullName();

	/**
	 * Get the whole resolving URL.
	 * @param $contextId integer
	 * @param $pubId string
	 * @return string resolving URL
	 */
	abstract function getResolvingURL($contextId, $pubId);

	/**
	 * Get the file (path + filename)
	 * to be included into the object's
	 * identifiers tab, e.g. for suffix editing.
	 * @return string
	 */
	abstract function getPubIdMetadataFile();

	/**
	 * Add JavaScript files to be loaded in the metadata file.
	 * @param $request PKPRequest
	 * @param $templateMgr PKPTemplateManager
	 */
	function addJavaScript($request, $templateMgr) { }

	/**
	 * Get the file (path + filename)
	 * for the pub id assignment
	 * to be included into other pages.
	 * @return string
	 */
	abstract function getPubIdAssignFile();

	/**
	 * Get the settings form.
	 * @param $contextId integer
	 * @return object Settings form
	 */
	abstract function instantiateSettingsForm($contextId);

	/**
	 * Get the additional form field names,
	 * for metadata, e.g. suffix field name.
	 * @return array
	 */
	abstract function getFormFieldNames();

	/**
	 * Get the assign option form field name.
	 * @return string
	 */
	abstract function getAssignFormFieldName();

	/**
	 * Get the the prefix form field name.
	 * @return string
	 */
	abstract function getPrefixFieldName();

	/**
	 * Get the the suffix form field name.
	 * @return string
	 */
	abstract function getSuffixFieldName();

	/**
	 * Get the link actions used in the pub id forms,
	 * e.g. clear pub id.
	 * @return array
	 */
	abstract function getLinkActions($pubObject);

	/**
	 * Get the suffix patterns form field names for all objects.
	 * @return array (pub object type => suffix pattern field name)
	 */
	abstract function getSuffixPatternsFieldNames();

	/**
	 * Get additional field names to be considered for storage.
	 * @return array
	 */
	abstract function getDAOFieldNames();

	/**
	 * Get the possible publication object types.
	 * @return array
	 */
	function getPubObjectTypes()  {
		return array('Publication', 'Representation', 'SubmissionFile');
	}

	/**
	 * Is this object type enabled in plugin settings
	 * @param $pubObjectType object
	 * @param $contextId integer
	 * @return boolean
	 */
	abstract function isObjectTypeEnabled($pubObjectType, $contextId);

	/**
	 * Get the error message for not unique pub id
	 * @return string
	 */
	abstract function getNotUniqueErrorMsg();

	/**
	 * Verify form data.
	 * @param $fieldName string The form field to be checked.
	 * @param $fieldValue string The value of the form field.
	 * @param $pubObject object
	 * @param $contextId integer
	 * @param $errorMsg string Return validation error messages here.
	 * @return boolean
	 */
	function verifyData($fieldName, $fieldValue, $pubObject, $contextId, &$errorMsg) {
		// Verify pub id uniqueness.
		if ($fieldName == $this->getSuffixFieldName()) {
			if (empty($fieldValue)) return true;

			// Construct the potential new pub id with the posted suffix.
			$pubIdPrefix = $this->getSetting($contextId, $this->getPrefixFieldName());
			if (empty($pubIdPrefix)) return true;
			$newPubId = $this->constructPubId($pubIdPrefix, $fieldValue, $contextId);

			if (!$this->checkDuplicate($newPubId, $pubObject->getId(), get_class($pubObject), $contextId)) {
				$errorMsg = $this->getNotUniqueErrorMsg();
				return false;
			}
		}
		return true;
	}

	/**
	 * Check whether the given pubId is valid.
	 * @param $pubId string
	 * @return boolean
	 */
	function validatePubId($pubId) {
		return true; // Assume a valid ID by default;
	}

	/**
	 * Return an array of publication object types and
	 * the corresponding DAOs.
	 * @return array
	 */
	function getDAOs() {
		return  array(
			DAORegistry::getDAO('PublicationDAO'),
			DAORegistry::getDAO('SubmissionDAO'),
			Application::getRepresentationDAO(),
			DAORegistry::getDAO('SubmissionFileDAO'),
		);
	}

	/**
	 * Get the possible submission file DAO delegates.
	 * @return array
	 */
	function getFileDAODelegates()  {
		return array('SubmissionFileDAODelegate', 'SupplementaryFileDAODelegate', 'SubmissionArtworkFileDAODelegate');
	}

	/**
	 * Can a pub id be assigned to the object.
	 * @param $pubObject object
	 * 	Submission, Representation, SubmissionFile + OJS Issue
	 * @return boolean
	 * 	false, if the pub id contains an unresolved pattern i.e. '%' or
	 * 	if the custom suffix is empty i.e. the pub id null.
	 */
	function canBeAssigned($pubObject) {
		// Has the pub id already been assigned.
		$pubIdType = $this->getPubIdType();
		$storedPubId = $pubObject->getStoredPubId($pubIdType);
		if ($storedPubId) return false;
		// Get the pub id.
		$pubId = $this->getPubId($pubObject);
		// Is the custom suffix empty i.e. the pub id null.
		if (!$pubId) return false;
		// Does the suffix contain unresolved pattern.
		$containPatterns = strpos($pubId, '%') !== false;
		return !$containPatterns;
	}

	/**
	 * Add properties for this type of public identifier to the entity's list for
	 * storage in the database.
	 * This is used for SchemaDAO-backed entities only.
	 * @see PKPPubIdPlugin::getAdditionalFieldNames()
	 * @param $hookName string `Schema::get::publication`
	 * @param $params array
	 */
	public function addToSchema($hookName, $params) {
		$schema =& $params[0];
		foreach (array_merge($this->getFormFieldNames(), $this->getDAOFieldNames()) as $fieldName) {
			$schema->properties->{$fieldName} = (object) [
				'type' => 'string',
				'apiSummary' => true,
				'validation' => ['nullable'],
			];
		}
		return false;
	}

	/**
	 * Add properties for this type of public identifier to the entity's list for
	 * storage in the database.
	 * This is used for non-SchemaDAO-backed entities only.
	 * @see PKPPubIdPlugin::addToSchema()
	 * @param $hookName string
	 * @param $params array
	 */
	function getAdditionalFieldNames($hookName, $params) {
		$fields =& $params[1];
		foreach (array_merge($this->getFormFieldNames(), $this->getDAOFieldNames()) as $fieldName) {
			$fields[] = $fieldName;
		}
		return false;
	}

	/**
	 * Return the object type.
	 * @param $pubObject object
	 * @return string?
	 */
	function getPubObjectType($pubObject) {
		$allowedTypes = $this->getPubObjectTypes();
		$pubObjectType = null;
		foreach ($allowedTypes as $allowedType) {
			if (is_a($pubObject, $allowedType)) {
				$pubObjectType = $allowedType;
				break;
			}
		}
		if (is_null($pubObjectType)) {
			// This must be a dev error, so bail with an assertion.
			assert(false);
			return null;
		}
		return $pubObjectType;
	}

	/**
	 * Set and store a public identifier.
	 * @param $pubObject object
	 * @param $pubId string
	 */
	function setStoredPubId(&$pubObject, $pubId) {
		$dao = $pubObject->getDAO();
		$dao->changePubId($pubObject->getId(), $this->getPubIdType(), $pubId);
		$pubObject->setStoredPubId($this->getPubIdType(), $pubId);
	}


	//
	// Public API
	//
	/**
	 * Check for duplicate public identifiers.
	 *
	 * Checks to see if a pubId has already been assigned to any object
	 * in the context.
	 *
	 * @param $pubId string
	 * @param $pubObjectType string Class name of the pub object being checked
	 * @param $excludeId integer This object id will not be checked for duplicates
	 * @param $contextId integer
	 * @return boolean
	 */
	function checkDuplicate($pubId, $pubObjectType, $excludeId, $contextId) {
		foreach ($this->getPubObjectTypes() as $type) {
			if ($type === 'Publication') {
				$typeDao = DAORegistry::getDAO('PublicationDAO'); /* @var $typeDao PublicationDAO */
			} elseif ($type === 'Representation') {
				$typeDao = Application::getRepresentationDAO();
			} elseif ($type === 'SubmissionFile') {
				$typeDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $typeDao SubmissionFileDAO */
			}
			$excludeTypeId = $type === $pubObjectType ? $excludeId : null;
			if (isset($typeDao) && $typeDao->pubIdExists($this->getPubIdType(), $pubId, $excludeTypeId, $contextId)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the context object.
	 * @param $contextId integer
	 * @return object Context
	 */
	function getContext($contextId) {
		assert(is_numeric($contextId));

		// Get the context object from the context (optimized).
		$request = Application::get()->getRequest();
		$router = $request->getRouter();
		$context = $router->getContext($request);
		if ($context && $context->getId() == $contextId) return $context;

		// Fall back the database.
		$contextDao = Application::getContextDAO();
		return $contextDao->getById($contextId);
	}

}



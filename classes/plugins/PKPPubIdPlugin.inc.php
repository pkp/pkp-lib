<?php

/**
 * @file classes/plugins/PKPPubIdPlugin.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPubIdPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for public identifiers plugins
 */

import('lib.pkp.classes.plugins.Plugin');

abstract class PKPPubIdPlugin extends LazyLoadPlugin {

	/**
	 * Constructor
	 */
	function PKPPubIdPlugin() {
		parent::LazyLoadPlugin();
	}


	//
	// Implement template methods from Plugin
	//
	/**
	 * @copydoc Plugin::register()
	 */
	function register($category, $path) {
		if (!parent::register($category, $path)) return false;
		// Enable storage of additional fields.
		foreach($this->getDAOs() as $publicObjectType => $daoName) {
			HookRegistry::register(strtolower_codesafe($daoName).'::getAdditionalFieldNames', array($this, 'getAdditionalFieldNames'));
		}
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

		$settingsFormName = $this->getSettingsFormName();
		$settingsFormNameParts = explode('.', $settingsFormName);
		$settingsFormClassName = array_pop($settingsFormNameParts);
		$this->import($settingsFormName);
		$form = new $settingsFormClassName($this, $context->getId());
		$notificationManager = new NotificationManager();
		if ($request->getUserVar('save')) {
			$form->readInputData();
			if ($form->validate()) {
				$form->execute();
				$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS);
				return new JSONMessage(true);
			} else {
				return new JSONMessage(true, $form->fetch($request));
			}
		} elseif ($request->getUserVar('clearPubIds')) {
			$contextDao = Application::getContextDAO();
			$contextDao->deleteAllPubIds($context->getId(), $this->getPubIdType());
			$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS);
			return new JSONMessage(true);
		} else {
			$form->initData();
			return new JSONMessage(true, $form->fetch($request));
		}
	}


	//
	// Protected template methods to be implemented by sub-classes.
	//
	/**
	 * Get the public identifier.
	 * @param $pubObject object
	 * 	OJS Article, Issue, or ArticleGalley i.e. OMP PublicationFormat
	 * @param $preview boolean
	 *  when true, the public identifier will not be stored
	 * @return string
	 */
	abstract function getPubId($pubObject, $preview = false);

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
	 * metadata pages.
	 * @return string
	 */
	abstract function getPubIdMetadataFile();

	/**
	 * Get the class name of the settings form.
	 * @return string
	 */
	abstract function getSettingsFormName();

	/**
	 * Get the additional form field names.
	 * @return array
	 */
	abstract function getFormFieldNames();

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
	 * Get the the suffix patterns form field names.
	 * @return array (pub object type => suffix pattern field name)
	 */
	abstract function getSuffixPatternsFieldNames();

	/**
	 * Get the checkbox form field name that
	 * is used to define if a pub object should
	 * be excluded from assigning the pub id to it.
	 * @return string
	 */
	abstract function getExcludeFormFieldName();

	/**
	 * Get additional field names to be considered for storage.
	 * @return array
	 */
	abstract function getDAOFieldNames();

	/**
	 * Get the possible publication object types.
	 * @return array
	 */
	abstract function getPubObjectTypes();

	/**
	 * Get all publication objects of the given type.
	 * @param $pubObjectType object
	 * @param $contextId integer
	 * @return array
	 */
	abstract function getPubObjects($pubObjectType, $contextId);

	/**
	 * Is this object type enabled in plugin settings
	 * @param $pubObjectType object
	 * @param $contextId integer
	 * @return boolean
	 */
	abstract function isObjectTypeEnabled($pubObjectType, $contextId);

	/**
	 * Should the object be excluded from
	 * assigning it the pub id
	 * @param $pubObject object
	 * @return boolean
	 */
	function isObjectExcluded($pubObject) {
		$excludeFormFieldName = $this->getExcludeFormFieldName();
		$excluded = $pubObject->getData($excludeFormFieldName);
		return $excluded;
	}

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
	function verifyData($fieldName, $fieldValue, &$pubObject, $contextId, &$errorMsg) {
		// Verify pub id uniqueness.
		if ($fieldName == $this->getSuffixFieldName()) {
			if (empty($fieldValue)) return true;

			// Construct the potential new pub id with the posted suffix.
			$pubIdPrefix = $this->getSetting($contextId, $this->getSuffixFieldName());
			if (empty($pubIdPrefix)) return true;
			$newPubId = $pubIdPrefix . '/' . $fieldValue;

			if($this->checkDuplicate($newPubId, $pubObject, $contextId)) {
				return true;
			} else {
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
	abstract function getDAOs();

	/**
	 * Add the suffix element and the public identifier
	 * to the object.
	 * @param $hookName string
	 * @param $params array ()
	 */
	function getAdditionalFieldNames($hookName, $params) {
		$fields =& $params[1];
		$formFieldNames = $this->getFormFieldNames();
		foreach ($formFieldNames as $formFieldName) {
			$fields[] = $formFieldName;
		}
		$daoFieldNames = $this->getDAOFieldNames();
		foreach ($daoFieldNames as $daoFieldName) {
			$fields[] = $daoFieldName;
		}
		return false;
	}

	/**
	 * Return the object type.
	 * @param $pubObject object
	 * @return array
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
	 * @param $pubObjectType string As returned from self::getPubObjectType()
	 * @param $pubId string
	 * @return string
	 */
	function setStoredPubId(&$pubObject, $pubObjectType, $pubId) {
		$daos = $this->getDAOs();
		$dao = DAORegistry::getDAO($daos[$pubObjectType]);
		$dao->changePubId($pubObject->getId(), $this->getPubIdType(), $pubId);
		$pubObject->setStoredPubId($this->getPubIdType(), $pubId);
	}


	//
	// Public API
	//
	/**
	 * Check for duplicate public identifiers.
	 * @param $pubId string
	 * @param $pubObject object
	 * @param $contextId integer
	 * @return boolean
	 */
	function checkDuplicate($pubId, &$pubObject, $contextId) {

		// Check all objects of the context whether they have
		// the same pubId. This includes pubIds that are not yet generated
		// but could be generated at any moment if someone accessed
		// the object publicly. We have to check "real" pubIds rather than
		// the pubId suffixes only as a pubId with the given suffix may exist
		// (e.g. through import) even if the suffix itself is not in the
		// database.
		// TO DO: ???
		//$typesToCheck = array('Issue', 'Article', 'ArticleGalley');
		$typesToCheck = $this->getPubObjectTypes();
		$objectsToCheck = null; // Suppress scrutinizer warn

		foreach($typesToCheck as $pubObjectType) {
			$objectsToCheck = $this->getPubObjects($pubObjectType, $contextId);

			$excludedId = (is_a($pubObject, $pubObjectType) ? $pubObject->getId() : null);
			while ($objectToCheck = $objectsToCheck->next()) {
				// The publication object for which the new pubId
				// should be admissible is to be ignored. Otherwise
				// we might get false positives by checking against
				// a pubId that we're about to change anyway.
				if ($objectToCheck->getId() == $excludedId) continue;

				// Check for ID clashes.
				$existingPubId = $this->getPubId($objectToCheck, true);
				if ($pubId == $existingPubId) return false;
			}

			unset($objectsToCheck);
		}

		// We did not find any ID collision, so go ahead.
		return true;
	}

	/**
	 * Get the journal object.
	 * @param $contextId integer
	 * @return object Context
	 */
	function getContext($contextId) {
		assert(is_numeric($contextId));

		// Get the context object from the context (optimized).
		$request = $this->getRequest();
		$router = $request->getRouter();
		$context = $router->getContext($request);
		if ($context && $context->getId() == $contextId) return $context;

		// Fall back the database.
		$contextDao = Application::getContextDAO();
		return $contextDao->getById($contextId);
	}

}

?>

<?php
/**
 * @defgroup controllers_api_user
 */

/**
 * @file controllers/api/user/UserApiHandler.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserApiHandler
 * @ingroup controllers_api_user
 *
 * @brief Class defining the headless AJAX API for backend user manipulation.
 */

// import the base Handler
import('lib.pkp.classes.handler.PKPHandler');

// import JSON class for API responses
import('lib.pkp.classes.core.JSONMessage');

class UserApiHandler extends PKPHandler {
	/**
	 * Constructor.
	 */
	function UserApiHandler() {
		parent::PKPHandler();
	}


	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize(&$request, $args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
		$this->addPolicy(new PKPSiteAccessPolicy(
			$request,
			array('updateUserMessageState'),
			SITE_ACCESS_ALL_ROLES
		));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods
	//
	/**
	 * Update the information whether user messages should be
	 * displayed or not.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string a JSON message
	 */
	function updateUserMessageState($args, &$request) {
		// Exit with a fatal error if request parameters are missing.
		if (!(isset($args['setting-name'])) && isset($args['setting-value'])) {
			fatalError('Required request parameter "setting-name" or "setting-value" missing!');
		}

		// Retrieve the user from the session.
		$user =& $request->getUser();
		assert(is_a($user, 'User'));

		// Validate the setting.
		// FIXME: We don't have to retrieve the setting type (which is always bool
		// for user messages) but only whether the setting name is valid and the
		// value is boolean.
		$settingName = $args['setting-name'];
		$settingValue = $args['setting-value'];
		$settingType = $this->_settingType($settingName);
		switch($settingType) {
			case 'bool':
				if (!($settingValue === 'false' || $settingValue === 'true')) {
					// Exit with a fatal error when the setting value is invalid.
					fatalError('Invalid setting value! Must be "true" or "false".');
				}
				$settingValue = ($settingValue === 'true' ? true : false);
				break;

			default:
				// Exit with a fatal error when an unknown setting is found.
				fatalError('Unknown setting!');
		}

		// Persist the validated setting.
		$userSettingsDao =& DAORegistry::getDAO('UserSettingsDAO');
		$userSettingsDao->updateSetting($user->getId(), $settingName, $settingValue, $settingType);

		// Return a success message.
		$json = new JSONMessage(true);
		return $json->getString();

	}

	/**
	 * Checks the requested setting against a whitelist of
	 * settings that can be changed remotely.
	 * @param $settingName string
	 * @return string a string representation of the setting type
	 *  for further validation if the setting is whitelisted, otherwise
	 *  null.
	 */
	function _settingType($settingName) {
		// Settings whitelist.
		static $allowedSettings = array(
			'citation-editor-hide-intro' => 'bool',
			'citation-editor-hide-raw-editing-warning' => 'bool'
		);

		// Identify the setting type.
		if (isset($allowedSettings[$settingName])) {
			return $allowedSettings[$settingName];
		} else {
			return null;
		}
	}
}

?>

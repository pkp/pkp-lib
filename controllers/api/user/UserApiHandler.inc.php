<?php
/**
 * @defgroup controllers_api_user User API controller
 */

/**
 * @file controllers/api/user/UserApiHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserApiHandler
 * @ingroup controllers_api_user
 *
 * @brief Class defining the headless AJAX API for backend user manipulation.
 */

use PKP\core\JSONMessage;
use PKP\handler\PKPHandler;
use PKP\security\authorization\PKPSiteAccessPolicy;

class UserApiHandler extends PKPHandler
{
    //
    // Implement template methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new PKPSiteAccessPolicy(
            $request,
            ['updateUserMessageState', 'suggestUsername'],
            PKPSiteAccessPolicy::SITE_ACCESS_ALL_ROLES
        ));
        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods
    //
    /**
     * Update the information whether user messages should be
     * displayed or not.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function updateUserMessageState($args, $request)
    {
        // Exit with a fatal error if request parameters are missing.
        if (!(isset($args['setting-name'])) && isset($args['setting-value'])) {
            fatalError('Required request parameter "setting-name" or "setting-value" missing!');
        }

        // Retrieve the user from the session.
        $user = $request->getUser();
        assert(is_a($user, 'User'));

        // Validate the setting.
        // FIXME: We don't have to retrieve the setting type (which is always bool
        // for user messages) but only whether the setting name is valid and the
        // value is boolean.
        $settingName = $args['setting-name'];
        $settingValue = $args['setting-value'];
        $settingType = $this->_settingType($settingName);
        switch ($settingType) {
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
        $userSettingsDao = DAORegistry::getDAO('UserSettingsDAO'); /** @var UserSettingsDAO $userSettingsDao */
        $userSettingsDao->updateSetting($user->getId(), $settingName, $settingValue, $settingType);

        // Return a success message.
        return new JSONMessage(true);
    }


    /**
     * Get a suggested username, making sure it's not already used.
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function suggestUsername($args, $request)
    {
        $suggestion = Validation::suggestUsername(
            $request->getUserVar('givenName'),
            $request->getUserVar('familyName')
        );

        return new JSONMessage(true, $suggestion);
    }

    /**
     * Checks the requested setting against a whitelist of
     * settings that can be changed remotely.
     *
     * @param $settingName string
     *
     * @return string a string representation of the setting type
     *  for further validation if the setting is whitelisted, otherwise
     *  null.
     */
    public function _settingType($settingName)
    {
        // Settings whitelist.
        static $allowedSettings = [
            'citation-editor-hide-intro' => 'bool',
            'citation-editor-hide-raw-editing-warning' => 'bool'
        ];

        // Identify the setting type.
        if (isset($allowedSettings[$settingName])) {
            return $allowedSettings[$settingName];
        } else {
            return null;
        }
    }
}

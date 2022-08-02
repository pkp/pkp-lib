<?php
/**
 * @defgroup controllers_api_user User API controller
 */

/**
 * @file controllers/api/user/UserApiHandler.php
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

namespace PKP\controllers\api\user;

use PKP\core\JSONMessage;
use PKP\handler\PKPHandler;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Validation;

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
            ['suggestUsername'],
            PKPSiteAccessPolicy::SITE_ACCESS_ALL_ROLES
        ));
        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods
    //
    /**
     * Get a suggested username, making sure it's not already used.
     *
     * @param array $args
     * @param PKPRequest $request
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
}

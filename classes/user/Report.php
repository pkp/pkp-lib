<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/Report.inc.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Report
 * @ingroup lib_pkp_classes_user
 *
 * @brief Generates a CSV report with basic user information given a list of users and an output stream.
 */

namespace PKP\user;

use APP\core\Application;
use APP\core\Request;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\security\UserGroup;
use PKP\security\UserGroupDAO;

class Report
{
    /** @var iterable The report data source, should yield /User objects */
    private iterable $_dataSource;

    private Request $_request;

    /**
     * Constructor
     *
     * @param iterable $dataSource The data source, should yield /User objects
     */
    public function __construct(iterable $dataSource)
    {
        $this->_dataSource = $dataSource;
        $this->_request = Application::get()->getRequest();
    }

    /**
     * Serializes the report to the given output
     *
     * @param resource $output A ready to write stream
     */
    public function serialize($output): void
    {
        // Adds BOM (byte order mark) to enforce the UTF-8 format
        fwrite($output, "\xEF\xBB\xBF");

        // Outputs column headings
        fputcsv($output, $this->_getHeadings());

        // Outputs each user
        foreach ($this->_dataSource as $user) {
            fputcsv($output, $this->_getDataRow($user));
        }
    }

    /**
     * Retrieves the report headings
     *
     * @return string[]
     */
    private function _getHeadings(): array
    {
        return [
            __('common.id'),
            __('user.givenName'),
            __('user.familyName'),
            __('user.email'),
            __('user.phone'),
            __('common.country'),
            __('common.mailingAddress'),
            __('user.dateRegistered'),
            __('common.updated'),
            ...array_map(fn (UserGroup $userGroup) => $userGroup->getLocalizedName(), $this->_getUserGroups())
        ];
    }

    /**
     * Retrieves the report row
     *
     * @return string[]
     */
    private function _getDataRow(User $user): array
    {
        /** @var UserGroupDAO */
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userGroups = $userGroupDao->getByUserId($user->getId());
        $groups = [];
        while ($userGroup = $userGroups->next()) {
            $groups[$userGroup->getId()] = 0;
        }

        return [
            $user->getId(),
            $user->getLocalizedGivenName(),
            $user->getFamilyName(Locale::getLocale()),
            $user->getEmail(),
            $user->getPhone(),
            $user->getCountryLocalized(),
            $user->getMailingAddress(),
            $user->getDateRegistered(),
            $user->getLocalizedData('dateProfileUpdated'),
            ...array_map(fn (UserGroup $userGroup) => __(isset($groups[$userGroup->getId()]) ? 'common.yes' : 'common.no'), $this->_getUserGroups())
        ];
    }

    /**
     * Retrieves the user groups
     *
     * @return UserGroup[]
     */
    private function _getUserGroups(): array
    {
        static $cache;
        return $cache ??= iterator_to_array(
            DAORegistry::getDAO('UserGroupDAO')->getByContextId($this->_request->getContext()->getId())->toIterator()
        );
    }
}

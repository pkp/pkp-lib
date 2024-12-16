<?php
/**
 * @defgroup lib_pkp_classes_user
 */

/**
 * @file lib/pkp/classes/user/Report.php
 *
 * Copyright (c) 2003-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class Report
 *
 * @ingroup lib_pkp_classes_user
 *
 * @brief Generates a CSV report with basic user information given a list of users and an output stream.
 */

namespace PKP\user;

use APP\core\Application;
use APP\core\Request;
use PKP\facades\Locale;
use PKP\userGroup\UserGroup;

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
            ...array_map(fn (UserGroup $userGroup) => $userGroup->getLocalizedData('name'), $this->_getUserGroups())
        ];
    }

    /**
     * Retrieves the report row
     *
     * @return string[]
     */
    private function _getDataRow(User $user): array
    {
        // fetch user groups where the user is assigned
        $userGroups = UserGroup::query()
            ->whereHas('userUserGroups', function ($query) use ($user) {
                $query->where('user_id', $user->getId())
                    ->where(function ($q) {
                        $q->whereNull('date_end')
                            ->orWhere('date_end', '>', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('date_start')
                            ->orWhere('date_start', '<=', now());
                    });
            })
            ->get();

        // get the IDs of the user's groups
        $userGroupIds = $userGroups->pluck('user_group_id')->all();

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
            ...array_map(
                fn (UserGroup $userGroup) => __(in_array($userGroup->id, $userGroupIds) ? 'common.yes' : 'common.no'),
                $this->_getUserGroups()
            )
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
        return $cache ??= UserGroup::withContextIds([$this->_request->getContext()->getId()])
            ->get()
            ->toArray();
    }
}

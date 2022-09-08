<?php

/**
 * @file classes/user/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 * @ingroup user
 *
 * @see User
 *
 * @brief Operations for retrieving and modifying User objects.
 */

namespace PKP\user;

use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\DataObject;
use PKP\core\EntityDAO;
use PKP\identity\Identity;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_USER;

    /** @copydoc EntityDAO::$table */
    public $table = 'users';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'user_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'user_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'user_id',
        'userName' => 'username',
        'password' => 'password',
        'email' => 'email',
        'url' => 'url',
        'phone' => 'phone',
        'mailingAddress' => 'mailing_address',
        'billingAddress' => 'billing_address',
        'country' => 'country',
        'locales' => 'locales',
        'gossip' => 'gossip',
        'dateLastEmail' => 'date_last_email',
        'dateRegistered' => 'date_registered',
        'dateValidated' => 'date_validated',
        'dateLastLogin' => 'date_last_login',
        'mustChangePassword' => 'must_change_password',
        'authStr' => 'auth_str',
        'disabled' => 'disabled',
        'disabledReason' => 'disabled_reason',
        'inlineHelp' => 'inline_help',
    ];

    /* These constants are used user-selectable search fields. */
    public const USER_FIELD_USERID = 'user_id';
    public const USER_FIELD_USERNAME = 'username';
    public const USER_FIELD_EMAIL = 'email';
    public const USER_FIELD_URL = 'url';
    public const USER_FIELD_INTERESTS = 'interests';
    public const USER_FIELD_AFFILIATION = 'affiliation';
    public const USER_FIELD_NONE = null;

    /**
     * Construct a new User object.
     *
     * @return User
     */
    public function newDataObject()
    {
        return new User();
    }

    /**
     * Get an user by its ID
     *
     * @param bool $allowDisabled If true, allow fetching a disabled user.
     */
    public function get(int $id, $allowDisabled = false): ?User
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        $user = $row ? $this->fromRow($row) : null;
        if (!$allowDisabled && $user?->getDisabled()) {
            return null;
        }
        return $user;
    }

    /**
     * Check if an user exists with this ID
     */
    public function exists(int $id): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->exists();
    }

    /**
     * Get a collection of users matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows, $query) {
            foreach ($rows as $row) {
                yield $row->user_id => $this->fromRow($row, $query->includeReviewerData);
            }
        });
    }

    /**
     * Get the total count of users matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('u.' . $this->primaryKeyColumn)
            ->pluck('u.' . $this->primaryKeyColumn);
    }

    /**
     * Retrieve a user by username.
     *
     * @return ?User
     */
    public function getByUsername(string $username, bool $allowDisabled = false): ?User
    {
        $row = DB::table($this->table)
            ->where('username', '=', $username)
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', '=', false);
            })
            ->get('user_id')
            ->first();
        return $row ? $this->get($row->user_id, $allowDisabled) : null;
    }

    /**
     * Retrieve a user by email address.
     *
     * @return ?User
     */
    public function getByEmail(string $email, bool $allowDisabled = false): ?User
    {
        $row = DB::table('users')
            ->where('email', '=', $email)
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', '=', false);
            })
            ->get('user_id')
            ->first();
        return $row ? $this->get($row->user_id) : null;
    }

    /**
     * Get the user by the TDL ID (implicit authentication).
     *
     * @param string $authstr
     * @param bool $allowDisabled
     *
     * @return ?User
     */
    public function getUserByAuthStr($authstr, $allowDisabled = true): ?User
    {
        $row = DB::table('users')
            ->where('auth_str', $authstr)
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', 0);
            })
            ->get('user_id')
            ->first();
        return $row ? $this->get($row->user_id) : null;
    }

    /**
     * Retrieve a user by username and (encrypted) password.
     *
     * @param string $username
     * @param string $password encrypted password
     * @param bool $allowDisabled
     *
     * @return ?User
     */
    public function getUserByCredentials($username, $password, $allowDisabled = true): ?User
    {
        $row = DB::table('users')
            ->where('username', '=', $username)
            ->where('password', '=', $password)
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', '=', false);
            })
            ->get('user_id')
            ->first();
        return $row ? $this->get($row->user_id) : null;
    }

    /**
     * @copydoc EntityDAO::fromRow
     *
     */
    public function fromRow(object $row, bool $includeReviewerData = false): DataObject
    {
        $user = parent::fromRow($row);
        if ($includeReviewerData) {
            $user->setData('lastAssigned', $row->last_assigned);
            $user->setData('incompleteCount', (int) $row->incomplete_count);
            $user->setData('completeCount', (int) $row->complete_count);
            $user->setData('declinedCount', (int) $row->declined_count);
            $user->setData('cancelledCount', (int) $row->cancelled_count);
            $user->setData('averageTime', (int) $row->average_time);

            // 0 values should return null. They represent a reviewer with no ratings
            if ($reviewerRating = $row->reviewer_rating) {
                $user->setData('reviewerRating', max(1, round($reviewerRating)));
            }
        }
        return $user;
    }

    /**
     * @copydoc EntityDAO::_insert()
     */
    public function insert(User $user): int
    {
        return parent::_insert($user);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(User $user)
    {
        parent::_update($user);
    }

    /**
     * @copydoc EntityDAO::_delete()
     */
    public function delete(User $user)
    {
        parent::_delete($user);
    }

    /**
     * Update user names when the site primary locale changes.
     *
     * @param string $oldLocale
     * @param string $newLocale
     */
    public function changeSitePrimaryLocale($oldLocale, $newLocale)
    {
        // remove all empty user names in the new locale
        // so that we do not have to take care if we should insert or update them -- we can then only insert them if needed
        $settingNames = [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName'];
        foreach ($settingNames as $settingName) {
            DB::delete("DELETE from user_settings WHERE locale = ? AND setting_name = ? AND setting_value = ''", [$newLocale, $settingName]);
        }
        // get all names of all users in the new locale
        $result = DB::select(
            'SELECT DISTINCT us.user_id, usg.setting_value AS given_name, usf.setting_value AS family_name, usp.setting_value AS preferred_public_name
            FROM user_settings us
                LEFT JOIN user_settings usg ON (usg.user_id = us.user_id AND usg.locale = ? AND usg.setting_name = ?)
                LEFT JOIN user_settings usf ON (usf.user_id = us.user_id AND usf.locale = ? AND usf.setting_name = ?)
                LEFT JOIN user_settings usp ON (usp.user_id = us.user_id AND usp.locale = ? AND usp.setting_name = ?)',
            [$newLocale, Identity::IDENTITY_SETTING_GIVENNAME, $newLocale, Identity::IDENTITY_SETTING_FAMILYNAME, $newLocale, 'preferredPublicName']
        );
        foreach ($result as $row) {
            $userId = $row->user_id;
            if (empty($row->given_name) && empty($row->family_name) && empty($row->preferred_public_name)) {
                // if no user name exists in the new locale, insert them all
                foreach ($settingNames as $settingName) {
                    DB::insert(
                        'INSERT INTO user_settings (user_id, locale, setting_name, setting_value)
                        SELECT DISTINCT us.user_id, ?, ?, us.setting_value
                        FROM user_settings us
                        WHERE us.setting_name = ? AND us.locale = ? AND us.user_id = ?',
                        [$newLocale, $settingName, $settingName, $oldLocale, $userId]
                    );
                }
            } elseif (empty($row->given_name)) {
                // if the given name does not exist in the new locale (but one of the other names do exist), insert it
                DB::insert(
                    'INSERT INTO user_settings (user_id, locale, setting_name, setting_value)
                    SELECT DISTINCT us.user_id, ?, ?, us.setting_value
                    FROM user_settings us
                    WHERE us.setting_name = ? AND us.locale = ? AND us.user_id = ?',
                    [$newLocale, Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_GIVENNAME, $oldLocale, $userId]
                );
            }
        }
    }

    /**
     * Delete unvalidated expired users
     *
     * @param object<Carbon\Carbon> $dateTillValid      The dateTime till before which user will consider expired
     * @param array                 $excludableUsersId  The users id to exclude form delete operation
     *
     * @return int The number rows affected by DB operation
     */
    public function deleteUnvalidatedExpiredUsers(Carbon $dateTillValid, array $excludableUsersId = [])
    {
        $users = DB::table('users')
            ->whereNull('date_validated')
            ->whereNull('date_last_login')
            ->where('date_registered', '<', $dateTillValid)
            ->when(!empty($excludableUsersId), fn ($query) => $query->whereNotIn('id', $excludableUsersId))
            ->get();

        $userRepository = Repo::user();

        $users->each(fn ($user) => $userRepository->delete($userRepository->get($user->user_id, true)));

        return $users->count();
    }
}

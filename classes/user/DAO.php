<?php

/**
 * @file classes/user/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @ingroup user
 *
 * @see User
 *
 * @brief Operations for retrieving and modifying User objects.
 */

namespace PKP\user;

use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\DataObject;
use PKP\core\EntityDAO;
use PKP\identity\Identity;
use PKP\security\Role;

/**
 * @template T of User
 *
 * @extends EntityDAO<T>
 */
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
        'rememberToken' => 'remember_token',
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
     */
    public function newDataObject()
    {
        return new User();
    }

    /**
     * Get a user
     *
     * @param bool $allowDisabled If true, allow fetching a disabled user.
     */
    public function get(int $id, $allowDisabled = false): ?User
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->first();
        /** @var User */
        $user = $row ? $this->fromRow($row) : null;
        if (!$allowDisabled && $user?->getDisabled()) {
            return null;
        }
        return $user;
    }

    /**
     * Check if a user exists
     */
    public function exists(int $id): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->exists();
    }

    /**
     * Get a collection of users matching the configured query
     *
     * @return LazyCollection<int,T>
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
            ->getCountForPagination();
    }

    /**
     * Get a list of ids matching the configured query
     *
     * @return Collection<int,int>
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
        $row = DB::table('users')
            ->whereRaw('LOWER(username) = LOWER(?)', [$username])
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
            ->whereRaw('LOWER(email) = LOWER(?)', [$email])
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', '=', false);
            })
            ->get('user_id')
            ->first();
        return $row ? $this->get($row->user_id, $allowDisabled) : null;
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
     */
    public function changeSitePrimaryLocale(string $oldLocale, string $newLocale): void
    {
        // remove all empty user names in the new locale
        // so that we do not have to take care if we should insert or update them -- we can then only insert them if needed
        $settingNames = [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName'];
        foreach ($settingNames as $settingName) {
            DB::delete("DELETE from user_settings WHERE locale = ? AND setting_name = ? AND setting_value = ''", [$newLocale, $settingName]);
        }
    
        // escape new locale value
        $newLocaleEscaped = DB::getPdo()->quote($newLocale);
    
        // insert missing data
        DB::table('user_settings')->insertUsing(
            ['user_id', 'locale', 'setting_name', 'setting_value'],
            DB::table('user_settings AS us_old')
                ->select('us_old.user_id', DB::raw("{$newLocaleEscaped} AS locale"), 'us_old.setting_name', 'us_old.setting_value')
                ->leftJoin('user_settings AS us_new', function ($join) use ($newLocale) {
                    $join->on('us_new.user_id', '=', 'us_old.user_id')
                        ->where('us_new.locale', '=', $newLocale)
                        ->whereColumn('us_new.setting_name', 'us_old.setting_name');
                })
                ->where('us_old.locale', '=', $oldLocale)
                ->whereIn('us_old.setting_name', $settingNames)
                ->whereNull('us_new.setting_value')
        );
    }

    /**
     * Delete unvalidated expired users
     *
     * @param Carbon $dateTillValid The dateTime till before which user will consider expired
     * @param array $excludableUsersId  The users id to exclude form delete operation
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

    /** Get admin users */
    public function getAdminUsers(): LazyCollection
    {
        $adminGroups = Repo::userGroup()->getArrayIdByRoleId(Role::ROLE_ID_SITE_ADMIN);
        $rows = collect();
        if (count($adminGroups)) {
            $rows = DB::table('users', 'u')
                ->select('u.*')
                ->where('u.disabled', '=', 0)
                ->whereExists(
                    fn (Builder $query) => $query->from('user_user_groups', 'uug')
                        ->join('user_groups AS ug', 'uug.user_group_id', '=', 'ug.user_group_id')
                        ->whereColumn('uug.user_id', '=', 'u.user_id')
                        ->whereIn('uug.user_group_id', $adminGroups)
                )
                ->get();
        }
        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->user_id => $this->fromRow($row);
            }
        });
    }
}

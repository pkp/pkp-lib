<?php

/**
 * @file classes/user/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserDAO
 * @ingroup user
 *
 * @see User
 *
 * @brief Operations for retrieving and modifying User objects.
 */

namespace PKP\user;

use APP\core\Application;
use APP\i18n\AppLocale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\Core;
use PKP\db\DAOResultFactory;
use PKP\identity\Identity;
use PKP\plugins\HookRegistry;
use PKP\security\Role;

class DAO extends \PKP\core\EntityDAO
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
        'authId' => 'auth_id',
        'authString' => 'auth_str',
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
     * @copydoc EntityDAO::_getFetchQuery()
     */
    /*    protected function _getFetchQuery(): \Illuminate\Database\Query\Builder {
            $locale = AppLocale::getLocale();
            $site = Application::get()->getRequest()->getSite();
            $primaryLocale = $site->getPrimaryLocale();
            return parent::_getFetchQuery()
                ->leftJoin($this->settingsTable . ' AS ugl', function($join) use ($locale) {
                    $join->on('ugl.user_id', '=', $this->tableName . '.user_id')
                        ->where('ugl.setting_name', '=', Identity::IDENTITY_SETTING_GIVENNAME)
                        ->where('ugl.locale', '=', $locale);
                })
                ->leftJoin($this->settingsTable . ' AS ugpl', function($join) use ($primaryLocale) {
                    $join->on('ugpl.user_id', '=', $this->tableName . '.user_id')
                        ->where('ugpl.setting_name', '=', Identity::IDENTITY_SETTING_GIVENNAME)
                        ->where('ugpl.locale', '=', $primaryLocale);
                })
                ->leftJoin($this->settingsTable . ' AS ufl', function($join) use ($locale) {
                    $join->on('ufl.user_id', '=', $this->tableName . '.user_id')
                        ->where('ufl.setting_name', '=', Identity::IDENTITY_SETTING_FAMILYNAME)
                        ->where('ufl.locale', '=', $locale);
                })
                ->leftJoin($this->settingsTable . ' AS ufpl', function($join) use ($primaryLocale) {
                    $join->on('ufpl.user_id', '=', $this->tableName . '.user_id')
                        ->where('ufpl.setting_name', '=', Identity::IDENTITY_SETTING_FAMILYNAME)
                        ->where('ufpl.locale', '=', $primaryLocale);
                })
                ->addSelect(
        }*/

    /**
     * @copydoc EntityDAO::get()
     *
     * @param $allowDisabled boolean If true, allow fetching a disabled user.
     */
    public function get($id, $allowDisabled = true): ?User
    {
        $user = parent::get($id);
        if (!$allowDisabled && $user->getDisabled()) {
            return null;
        }
        return $user;
    }

    /**
     * Get a collection of announcements matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $this->fromRow($row);
            }
        });
    }

    /**
     * Retrieve a user by username.
     *
     * @param $username string
     * @param $allowDisabled boolean
     *
     * @return User?
     */
    public function getByUsername(string $username, bool $allowDisabled = true): ?User
    {
        $row = DB::table('users')
            ->where('username', '=', $username)
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', '=', false);
            })
            ->get('user_id')
            ->first();
        return $this->get($row->user_id);
    }

    /**
     * Retrieve a user by email address.
     *
     * @param $username string
     * @param $allowDisabled boolean
     *
     * @return User?
     */
    public function getByEmail(string $username, bool $allowDisabled = true): ?User
    {
        $row = DB::table('users')
            ->where('email', '=', $username)
            ->when(!$allowDisabled, function ($query) {
                return $query->where('disabled', '=', false);
            })
            ->get('user_id')
            ->first();
        return $this->get($row->user_id);
    }

    /**
     * Retrieve a user by setting.
     *
     * @param $settingName string
     * @param $settingValue string
     * @param $allowDisabled boolean
     *
     * @return User?
     */
    public function getBySetting($settingName, $settingValue, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT u.* FROM users u JOIN user_settings us ON (u.user_id = us.user_id) WHERE us.setting_name = ? AND us.setting_value = ?' . ($allowDisabled ? '' : ' AND u.disabled = 0'),
            [$settingName, $settingValue]
        );
        $row = $result->current();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the user by the TDL ID (implicit authentication).
     *
     * @param $authstr string
     * @param $allowDisabled boolean
     *
     * @return User?
     */
    public function getUserByAuthStr($authstr, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT * FROM users WHERE auth_str = ?' . ($allowDisabled ? '' : ' AND disabled = 0'),
            [$authstr]
        );
        $row = $result->current();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Retrieve a user by email address.
     *
     * @param $email string
     * @param $allowDisabled boolean
     *
     * @return User?
     */
    public function getUserByEmail($email, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT * FROM users WHERE email = ?' . ($allowDisabled ? '' : ' AND disabled = 0'),
            [$email]
        );
        $row = $result->current();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Retrieve a user by username and (encrypted) password.
     *
     * @param $username string
     * @param $password string encrypted password
     * @param $allowDisabled boolean
     *
     * @return User?
     */
    public function getUserByCredentials($username, $password, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT * FROM users WHERE username = ? AND password = ?' . ($allowDisabled ? '' : ' AND disabled = 0'),
            [$username, $password]
        );
        $row = $result->current();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Retrieve a list of all reviewers not assigned to the specified submission.
     *
     * @param $contextId int
     * @param $submissionId int
     * @param $reviewRound ReviewRound
     * @param $name string
     *
     * @return array matching Users
     */
    public function getReviewersNotAssignedToSubmission($contextId, $submissionId, &$reviewRound, $name = '')
    {
        $params = [(int) $contextId, Role::ROLE_ID_REVIEWER, (int) $reviewRound->getStageId(), (int) $submissionId, (int) $reviewRound->getId()];
        if (!empty($name)) {
            $nameSearchJoins = 'LEFT JOIN user_settings usgs ON (u.user_id = usgs.user_id AND usgs.setting_name = \'' . Identity::IDENTITY_SETTING_GIVENNAME . '\')
				LEFT JOIN user_settings usfs ON (u.user_id = usfs.user_id AND usfs.setting_name = \'' . Identity::IDENTITY_SETTING_FAMILYNAME . '\')';
            $params[] = $params[] = $params[] = $params[] = "%${name}%";
        }

        $result = $this->retrieve(
            'SELECT	DISTINCT u.*
			FROM	users u
			JOIN user_user_groups uug ON (uug.user_id = u.user_id)
			JOIN user_groups ug ON (ug.user_group_id = uug.user_group_id AND ug.context_id = ? AND ug.role_id = ?)
			JOIN user_group_stage ugs ON (ugs.user_group_id = ug.user_group_id AND ugs.stage_id = ?)' .
            (!empty($name) ? $nameSearchJoins : '') . '
			WHERE 0=(SELECT COUNT(r.reviewer_id)
				FROM review_assignments r
				WHERE r.submission_id = ? AND r.reviewer_id = u.user_id AND r.review_round_id = ?)' .
            (!empty($name) ? ' AND (usgs.setting_value LIKE ? OR usfs.setting_value LIKE ? OR username LIKE ? OR email LIKE ?)' : '') . '
			' . $this->getOrderBy(),
            $params
        );
        return new DAOResultFactory($result, $this, 'fromRow');
    }

    /**

     * Return a user object from a DB row, including dependent data and reviewer stats.
     *
     * @param $row array
     *
     * @return User
     */
    public function _returnUserFromRowWithReviewerStats($row)
    {
        $user = $this->fromRow($row);
        $user->setData('lastAssigned', $row['last_assigned']);
        $user->setData('incompleteCount', (int) $row['incomplete_count']);
        $user->setData('completeCount', (int) $row['complete_count']);
        $user->setData('declinedCount', (int) $row['declined_count']);
        $user->setData('cancelledCount', (int) $row['cancelled_count']);
        $user->setData('averageTime', (int) $row['average_time']);

        // 0 values should return null. They represent a reviewer with no ratings
        if ($row['reviewer_rating']) {
            $user->setData('reviewerRating', max(1, round($row['reviewer_rating'])));
        }

        HookRegistry::call('UserDAO::_returnUserFromRowWithReviewerStats', [&$user, &$row]);

        return $user;
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(User $user)
    {
        parent::_update($user);
    }

    /**
     * Insert a new user.
     *
     * @param $user User
     */
    public function insertObject($user)
    {
        if ($user->getDateRegistered() == null) {
            $user->setDateRegistered(Core::getCurrentDate());
        }
        if ($user->getDateLastLogin() == null) {
            $user->setDateLastLogin(Core::getCurrentDate());
        }
        $this->update(
            sprintf(
                'INSERT INTO users
				(username, password, email, url, phone, mailing_address, billing_address, country, locales, date_last_email, date_registered, date_validated, date_last_login, must_change_password, disabled, disabled_reason, auth_id, auth_str, inline_help, gossip)
				VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, %s, %s, %s, %s, ?, ?, ?, ?, ?, ?, ?)',
                $this->datetimeToDB($user->getDateLastEmail()),
                $this->datetimeToDB($user->getDateRegistered()),
                $this->datetimeToDB($user->getDateValidated()),
                $this->datetimeToDB($user->getDateLastLogin())
            ),
            [
                $user->getUsername(),
                $user->getPassword(),
                $user->getEmail(),
                $user->getUrl(),
                $user->getPhone(),
                $user->getMailingAddress(),
                $user->getBillingAddress(),
                $user->getCountry(),
                join(':', $user->getLocales()),
                $user->getMustChangePassword() ? 1 : 0,
                $user->getDisabled() ? 1 : 0,
                $user->getDisabledReason(),
                $user->getAuthId() == '' ? null : (int) $user->getAuthId(),
                $user->getAuthStr(),
                (int) $user->getInlineHelp(),
                $user->getGossip(),
            ]
        );

        $user->setId($this->getInsertId());
        $this->updateLocaleFields($user);
        return $user->getId();
    }

    /**
     * @copydoc DAO::getLocaleFieldNames
     */
    public function getLocaleFieldNames()
    {
        return ['biography', 'signature', 'affiliation',
            Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName'];
    }

    /**
     * @copydoc DAO::getAdditionalFieldNames()
     */
    public function getAdditionalFieldNames()
    {
        return array_merge(parent::getAdditionalFieldNames(), [
            'orcid',
            'apiKey',
            'apiKeyEnabled',
        ]);
    }

    /**
     * @copydoc DAO::updateLocaleFields
     */
    public function updateLocaleFields($user)
    {
        $this->updateDataObjectSettings('user_settings', $user, [
            'user_id' => (int) $user->getId(),
            // assoc_type and assoc_id must be included for upsert, or PostgreSQL's ON CONFLICT will not work:
            // "there is no unique or exclusion constraint matching the ON CONFLICT specification"
            // However, no localized context-specific data is currently used, so we can rely on the pkey.
            'assoc_type' => \PKP\core\PKPApplication::CONTEXT_SITE,
            'assoc_id' => 0,
        ]);
    }

    /**
     * Update an existing user.
     *
     * @param $user User
     */
    public function updateObject($user)
    {
        if ($user->getDateLastLogin() == null) {
            $user->setDateLastLogin(Core::getCurrentDate());
        }

        $this->updateLocaleFields($user);

        return $this->update(
            sprintf(
                'UPDATE	users
				SET	username = ?,
					password = ?,
					email = ?,
					url = ?,
					phone = ?,
					mailing_address = ?,
					billing_address = ?,
					country = ?,
					locales = ?,
					date_last_email = %s,
					date_validated = %s,
					date_last_login = %s,
					must_change_password = ?,
					disabled = ?,
					disabled_reason = ?,
					auth_id = ?,
					auth_str = ?,
					inline_help = ?,
					gossip = ?
				WHERE	user_id = ?',
                $this->datetimeToDB($user->getDateLastEmail()),
                $this->datetimeToDB($user->getDateValidated()),
                $this->datetimeToDB($user->getDateLastLogin())
            ),
            [
                $user->getUsername(),
                $user->getPassword(),
                $user->getEmail(),
                $user->getUrl(),
                $user->getPhone(),
                $user->getMailingAddress(),
                $user->getBillingAddress(),
                $user->getCountry(),
                join(':', $user->getLocales()),
                $user->getMustChangePassword() ? 1 : 0,
                $user->getDisabled() ? 1 : 0,
                $user->getDisabledReason(),
                $user->getAuthId() == '' ? null : (int) $user->getAuthId(),
                $user->getAuthStr(),
                (int) $user->getInlineHelp(),
                $user->getGossip(),
                (int) $user->getId(),
            ]
        );
    }

    /**
     * Delete a user.
     *
     * @param $user User
     */
    public function deleteObject($user)
    {
        $this->deleteUserById($user->getId());
    }

    /**
     * Delete a user by ID.
     *
     * @param $userId int
     */
    public function deleteUserById($userId)
    {
        $this->update('DELETE FROM user_settings WHERE user_id = ?', [(int) $userId]);
        $this->update('DELETE FROM users WHERE user_id = ?', [(int) $userId]);
    }

    /**
     * Retrieve a user's name.
     *
     * @param $userId int
     * @param $allowDisabled boolean
     *
     * @return string|null
     */
    public function getUserFullName($userId, $allowDisabled = true)
    {
        $user = $this->getById($userId, $allowDisabled);
        return $user ? $user->getFullName() : null;
    }

    /**
     * Retrieve a user's email address.
     *
     * @param $userId int
     * @param $allowDisabled boolean
     *
     * @return string
     */
    public function getUserEmail($userId, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT email FROM users WHERE user_id = ?' . ($allowDisabled ? '' : ' AND disabled = 0'),
            [(int) $userId]
        );
        $row = $result->current();
        return $row ? $row->email : null;
    }

    /**
     * Retrieve an array of users with no role defined.
     *
     * @param $allowDisabled boolean
     * @param $dbResultRange object The desired range of results to return
     *
     * @return DAOResultFactory
     */
    public function getUsersWithNoRole($allowDisabled = true, $dbResultRange = null)
    {
        return new DAOResultFactory(
            $this->retrieveRange(
                'SELECT u.* FROM users u LEFT JOIN roles r ON u.user_id=r.user_id WHERE r.role_id IS NULL' .
                ($allowDisabled ? '' : ' AND u.disabled = 0') . $this->getOrderBy(),
                [],
                $dbResultRange
            ),
            $this,
            'fromRow'
        );
    }

    /**
     * Check if a user exists with the specified username.
     *
     * @param $username string
     * @param $userId int optional, ignore matches with this user ID
     * @param $allowDisabled boolean
     *
     * @return boolean
     */
    public function userExistsByUsername($username, $userId = null, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM users WHERE username = ?' . (isset($userId) ? ' AND user_id != ?' : '') . ($allowDisabled ? '' : ' AND disabled = 0'),
            isset($userId) ? [$username, (int) $userId] : [$username]
        );
        $row = $result->current();
        return $row && $row->row_count;
    }

    /**
     * Check if a user exists with the specified email address.
     *
     * @param $email string
     * @param $userId int optional, ignore matches with this user ID
     * @param $allowDisabled boolean
     *
     * @return boolean
     */
    public function userExistsByEmail($email, $userId = null, $allowDisabled = true)
    {
        $result = $this->retrieve(
            'SELECT COUNT(*) AS row_count FROM users WHERE email = ?' . (isset($userId) ? ' AND user_id != ?' : '') . ($allowDisabled ? '' : ' AND disabled = 0'),
            isset($userId) ? [$email, (int) $userId] : [$email]
        );
        $row = $result->current();
        return $row && $row->row_count;
    }

    /**
     * Update user names when the site primary locale changes.
     *
     * @param $oldLocale string
     * @param $newLocale string
     */
    public function changeSitePrimaryLocale($oldLocale, $newLocale)
    {
        // remove all empty user names in the new locale
        // so that we do not have to take care if we should insert or update them -- we can then only insert them if needed
        $settingNames = [Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_FAMILYNAME, 'preferredPublicName'];
        foreach ($settingNames as $settingName) {
            $params = [$newLocale, $settingName];
            $this->update(
                "DELETE from user_settings
				WHERE locale = ? AND setting_name = ? AND setting_value = ''",
                $params
            );
        }
        // get all names of all users in the new locale
        $result = $this->retrieve(
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
                    $this->update(
                        "INSERT INTO user_settings (user_id, locale, setting_name, setting_value, setting_type)
						SELECT DISTINCT us.user_id, ?, ?, us.setting_value, 'string'
						FROM user_settings us
						WHERE us.setting_name = ? AND us.locale = ? AND us.user_id = ?",
                        [$newLocale, $settingName, $settingName, $oldLocale, $userId]
                    );
                }
            } elseif (empty($row->given_name)) {
                // if the given name does not exist in the new locale (but one of the other names do exist), insert it
                $this->update(
                    "INSERT INTO user_settings (user_id, locale, setting_name, setting_value, setting_type)
					SELECT DISTINCT us.user_id, ?, ?, us.setting_value, 'string'
					FROM user_settings us
					WHERE us.setting_name = ? AND us.locale = ? AND us.user_id = ?",
                    [$newLocale, Identity::IDENTITY_SETTING_GIVENNAME, Identity::IDENTITY_SETTING_GIVENNAME, $oldLocale, $userId]
                );
            }
        }
    }

    /**
     * Get the ID of the last inserted user.
     *
     * @return int
     */
    public function getInsertId()
    {
        return $this->_getInsertId('users', 'user_id');
    }

    /**
     * Return a default sorting.
     *
     * @return string
     */
    public function getOrderBy()
    {
        return 'ORDER BY user_family, user_given';
    }
}

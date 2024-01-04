<?php

namespace PKP\core;

use Closure;
use APP\facades\Repo;
use Illuminate\Contracts\Auth\Guard;
use PKP\validation\ValidatorFactory;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;

class PKPUserProvider implements UserProvider
{
    /**
     * The hasher implementation.
     *
     * @var \Illuminate\Contracts\Hashing\Hasher
     */
    protected $hasher;

    /**
     * The callback that may modify the user retrieval queries.
     *
     * @var (\Closure(\Illuminate\Database\Eloquent\Builder):mixed)|null
     */
    protected $queryCallback;

    /**
     * Create a new database user provider.
     *
     */
    public function __construct()
    {
        $this->hasher = new \Illuminate\Hashing\BcryptHasher();
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($id)
    {
        error_log('RETRIEVE BY ID: ' . $id);
        return Repo::user()->get($id);
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  string  $token
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        error_log('RETRIEVE BY TOKEN');
        return null;
        /*$model = $this->createModel();

        $retrievedModel = $this->newModelQuery($model)->where(
            $model->getAuthIdentifierName(), $identifier
        )->first();

        if (! $retrievedModel) {
            return;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $retrievedModel : null;*/
    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  string  $token
     */
    public function updateRememberToken(UserContract $user, $token)
    {
        error_log('Unimplemented');
        /*$user->setRememberToken($token);

        $timestamps = $user->timestamps;

        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;*/
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return;
        }

        $user = ValidatorFactory::make(['email' => $credentials['username']], ['email' => 'email'])->passes()
            ? Repo::user()->getByEmail($credentials['username'], true)
            : Repo::user()->getByUsername($credentials['username'], true);
        
        app()->make(Guard::class)->setUser($user);

        return $user;
    }

    /**
     * Validate a user against the given credentials.
     *
     * @return bool
     */
    public function validateCredentials(UserContract $user, array $credentials)
    {
        error_log('VALIDATE CREDENTIALS');
        if (is_null($plain = $credentials['password'])) {
            return false;
        }

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Get a new query builder for the model instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model|null  $model
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newModelQuery($model = null)
    {
        error_log('NEW MODEL QUERY');
        $query = is_null($model)
                ? $this->createModel()->newQuery()
                : $model->newQuery();

        with($query, $this->queryCallback);

        return $query;
    }

    /**
     * Create a new instance of the model.
     */
    public function createModel()
    {
        error_log('CREATE MODEL');
        return new User();
    }

    /**
     * Gets the hasher implementation.
     *
     * @return \Illuminate\Contracts\Hashing\Hasher
     */
    public function getHasher()
    {
        error_log('GET HASHER');
        return $this->hasher;
    }

    /**
     * Sets the hasher implementation.
     *
     * @return $this
     */
    public function setHasher(HasherContract $hasher)
    {
        error_log('SET HASHER');
        $this->hasher = $hasher;

        return $this;
    }

    /**
     * Gets the name of the Eloquent user model.
     *
     * @return string
     */
    public function getModel()
    {
        error_log('GET MODEL');
        return $this->model;
    }

    /**
     * Sets the name of the Eloquent user model.
     *
     * @param  string  $model
     *
     * @return $this
     */
    public function setModel($model)
    {
        error_log('SET MODEL');
        $this->model = $model;

        return $this;
    }

    /**
     * Get the callback that modifies the query before retrieving users.
     *
     * @return \Closure|null
     */
    public function getQueryCallback()
    {
        error_log('GET QUERY CALLBACK');
        return $this->queryCallback;
    }

    /**
     * Sets the callback to modify the query before retrieving users.
     *
     * @param  (\Closure(\Illuminate\Database\Eloquent\Builder):mixed)|null  $queryCallback
     *
     * @return $this
     */
    public function withQuery($queryCallback = null)
    {
        error_log('WITH QUERY');
        $this->queryCallback = $queryCallback;

        return $this;
    }
}

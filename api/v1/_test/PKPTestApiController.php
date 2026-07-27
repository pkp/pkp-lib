<?php

/**
 * @file api/v1/_test/PKPTestApiController.php
 *
 * Copyright (c) 2023-2026 Simon Fraser University
 * Copyright (c) 2023-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTestApiController
 *
 * @ingroup api_v1__test
 *
 * @brief Base controller for the test-only /api/v1/_test/* namespace.
 *
 * Everything in this namespace exists for the Playwright e2e harness and is
 * registered only when the TEST_API_KEY environment variable is present (see
 * api/v1/_test/index.php and \PKP\testing\TestApiGate). Access control is the
 * shared secret alone: there is no session, no CSRF token and no role policy,
 * because the caller is a test runner seeding its own state, not a user.
 */

namespace PKP\API\v1\_test;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\testing\RequireTestApiKey;
use PKP\testing\scenario\BuildJournal;
use PKP\testing\scenario\ScenarioException;
use PKP\testing\scenario\SpecValidator;
use PKP\user\User;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;
use Throwable;

abstract class PKPTestApiController extends PKPBaseController
{
    /** Locale-key prefix that identifies a default user group across all apps */
    public const USER_GROUP_NAME_KEY_PREFIX = 'default.groups.name.';

    protected BuildJournal $journal;

    public function __construct()
    {
        $this->journal = new BuildJournal();
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_test';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::isSiteWide()
     */
    public function isSiteWide(): bool
    {
        return true;
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [RequireTestApiKey::class];
    }

    /**
     * The harness serves over plain HTTP on localhost.
     */
    public function requireSSL(): bool
    {
        return false;
    }

    /**
     * The RequireTestApiKey middleware is the whole authorization story here.
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        return true;
    }

    //
    // Spec handling
    //

    /**
     * App-specific properties this endpoint's schema declares in addition to the
     * app-neutral core (OJS sections/issues, OMP series, ...).
     *
     * Overriding this in the app subclass is how app concepts enter the schema.
     * Declaring them means an OJS-only key sent to another app fails with
     * "unknown key" instead of being quietly ignored.
     */
    public function schemaOverlayProperties(): array
    {
        return [];
    }

    /**
     * App-specific properties a USER spec declares in addition to the app-neutral
     * core (OJS section-editor assignments, OMP series assignments, ...).
     *
     * Same contract as schemaOverlayProperties(), one level down: the user spec is
     * shared by bootstrap and the context scenario, so an app declares its user
     * concepts once here and both endpoints validate them.
     */
    public function userSchemaOverlayProperties(): array
    {
        return [];
    }

    /**
     * The user spec schema with this app's overlay merged in.
     */
    protected function userSchema(): array
    {
        return SpecValidator::withOverlay(SpecValidator::load('user'), $this->userSchemaOverlayProperties());
    }

    /**
     * Run a scenario build with mail faked and failures cleaned up.
     *
     * Mail::fake() covers the SEEDING side only: emails the real services send
     * while walking the app to the requested state never reach Mailpit, so a test
     * asserting on mail sees only the mail its own actions produced. Test-action
     * mail, sent outside this request, flows normally.
     */
    protected function build(callable $builder): JsonResponse
    {
        Mail::fake();

        try {
            $result = $builder();
        } catch (ScenarioException $e) {
            $this->journal->rollBack();

            return response()->json(
                $e->toArray() + $this->orphanReport(),
                $e->status
            );
        } catch (Throwable $e) {
            $this->journal->rollBack();

            return response()->json([
                'error' => $e->getMessage(),
                'exception' => $e::class,
                'at' => $e->getFile() . ':' . $e->getLine(),
                // A trace is safe here (test-only namespace) and turns "seeding
                // broke" into an actionable line number without a second run.
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 25),
            ] + $this->orphanReport(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json($result, Response::HTTP_OK);
    }

    protected function orphanReport(): array
    {
        $orphans = $this->journal->orphans();

        return $orphans ? ['orphans' => $orphans] : [];
    }

    /**
     * Decode and validate the request body against a schema.
     *
     * @throws ScenarioException
     */
    protected function readSpec(\Illuminate\Http\Request $illuminateRequest, array $schema): array
    {
        $spec = $illuminateRequest->json()->all();

        if (!is_array($spec)) {
            throw new ScenarioException('The request body must be a JSON object.');
        }

        SpecValidator::validate($spec, $schema);

        return $spec;
    }

    //
    // Shared seeding helpers
    //

    /**
     * Act as the given user for the duration of the callback.
     *
     * The application's services read the acting user from the request; seeding
     * through the real services therefore means telling them who is acting,
     * exactly as a signed-in session would.
     */
    protected function actingAs(?User $user, callable $callback): mixed
    {
        $previous = Registry::get('user', true, null);
        Registry::set('user', $user);

        try {
            return $callback();
        } finally {
            Registry::set('user', $previous);
        }
    }

    /**
     * Run a callback with the request scoped to a context.
     *
     * The test namespace is site-wide (it has to be: bootstrap creates the very
     * context everything else runs in), but the services it drives expect the
     * context-scoped request a workflow screen would have made — notification
     * managers read $request->getContext() directly. Setting the router's context
     * for the duration of the build is what makes the seeded state identical to
     * the state the same services produce from inside the journal.
     */
    protected function inContext(Context $context, callable $callback): mixed
    {
        $router = Application::get()->getRequest()->getRouter();
        $previous = $router->_context;
        $router->_context = $context;

        try {
            return $callback();
        } finally {
            $router->_context = $previous;
        }
    }

    /**
     * Feed request variables the application's own forms and actions read.
     *
     * Several real services (EditorAction::addReviewer among them) take options
     * from the request rather than their arguments. Merging them into the current
     * request is how the seeding call supplies the same values the UI form posts.
     */
    protected function withRequestVars(array $vars, callable $callback): mixed
    {
        $illuminateRequest = app('request');
        $previous = $illuminateRequest->all();

        $illuminateRequest->merge($vars);

        try {
            return $callback();
        } finally {
            $illuminateRequest->replace($previous);
        }
    }

    /**
     * The site administrator; the acting user for site-level seeding.
     */
    protected function siteAdmin(): User
    {
        $admin = Repo::user()->getCollector()
            ->filterByUserGroupIds([$this->siteAdminUserGroupId()])
            ->getMany()
            ->first();

        if (!$admin) {
            throw new ScenarioException('No site administrator exists; run tools/installTest.php first.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $admin;
    }

    protected function siteAdminUserGroupId(): int
    {
        $group = UserGroup::withRoleIds([Role::ROLE_ID_SITE_ADMIN])->first();

        if (!$group) {
            throw new ScenarioException('No site administrator user group exists.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $group->id;
    }

    /**
     * Resolve a role key ('manager', 'sectionEditor', 'reviewer', ...) to the
     * context's default user group.
     *
     * Resolution is by the group's stored nameLocaleKey, which every app writes
     * from its own registry/userGroups.xml. That keeps the role vocabulary
     * app-neutral without hard-coding role ids or translated names: a key an app
     * does not ship simply does not resolve, and the caller is told so.
     *
     * @throws ScenarioException
     */
    protected function resolveUserGroup(Context $context, string $roleKey, string $specKey): UserGroup
    {
        $wanted = static::USER_GROUP_NAME_KEY_PREFIX . $roleKey;

        $group = UserGroup::withContextIds([$context->getId()])
            ->get()
            ->first(fn (UserGroup $group) => $group->nameLocaleKey === $wanted);

        if (!$group) {
            $available = UserGroup::withContextIds([$context->getId()])
                ->get()
                ->map(fn (UserGroup $group) => str_replace(static::USER_GROUP_NAME_KEY_PREFIX, '', (string) $group->nameLocaleKey))
                ->filter()
                ->values()
                ->all();

            throw new ScenarioException(
                "No default user group for role '{$roleKey}' in context '{$context->getPath()}'. "
                    . 'Available roles: ' . implode(', ', $available) . '.',
                $specKey
            );
        }

        return $group;
    }

    /**
     * Find a user by username.
     *
     * @throws ScenarioException
     */
    protected function requireUser(string $username, string $specKey): User
    {
        $user = Repo::user()->getByUsername($username, true);

        if (!$user) {
            throw new ScenarioException("No user with username '{$username}'.", $specKey);
        }

        return $user;
    }

    /**
     * Seed users and enrol them in the context's roles.
     *
     * Returns [username => userId]. Existing usernames are reused and only their
     * missing role enrolments are added, so a re-run is not an error; NEW users
     * are recorded in the build journal so a later failure removes them.
     *
     * @throws ScenarioException
     */
    protected function seedUsers(Context $context, array $userSpecs, string $specKeyPrefix): array
    {
        $seeded = [];

        foreach (array_values($userSpecs) as $index => $userSpec) {
            $specKey = "{$specKeyPrefix}.{$index}";
            $username = $userSpec['username'] ?? null;

            if (!$username) {
                throw new ScenarioException('A user spec needs a username.', "{$specKey}.username");
            }

            $user = Repo::user()->getByUsername($username, true);

            if (!$user) {
                $user = $this->createUser($userSpec, $context);
                $this->journal->recordUser($user->getId());
            }

            foreach ($userSpec['roles'] as $roleIndex => $roleKey) {
                $group = $this->resolveUserGroup($context, $roleKey, "{$specKey}.roles.{$roleIndex}");

                $alreadyEnrolled = UserUserGroup::query()
                    ->withUserId($user->getId())
                    ->withUserGroupIds([$group->id])
                    ->exists();

                if (!$alreadyEnrolled) {
                    Repo::userGroup()->assignUserToGroup($user->getId(), $group->id);
                }
            }

            $this->afterUserSeeded($context, $userSpec, $user, $specKey);

            $seeded[$username] = $user->getId();
        }

        return $seeded;
    }

    /**
     * Hook for the app subclass to act on an app-specific user overlay key once
     * the user exists and its role enrolments are in place (OJS section-editor
     * assignments, for instance). Runs for every user in the spec, new or reused.
     */
    protected function afterUserSeeded(Context $context, array $userSpec, User $user, string $specKey): void
    {
    }

    /**
     * Create a user the way the registration form does.
     */
    protected function createUser(array $userSpec, Context $context): User
    {
        $username = $userSpec['username'];
        $locale = $context->getPrimaryLocale();
        $password = $userSpec['password'] ?? static::defaultPasswordFor($username);

        $user = Repo::user()->newDataObject();
        $user->setUsername($username);
        $user->setGivenName($userSpec['givenName'] ?? ucfirst(explode('.', $username)[1] ?? $username), $locale);
        $user->setFamilyName($userSpec['familyName'] ?? ucfirst(explode('.', $username)[0]), $locale);
        $user->setEmail($userSpec['email'] ?? $username . '@example.org');
        $user->setCountry($userSpec['country'] ?? 'CA');

        if (isset($userSpec['affiliation'])) {
            $user->setAffiliation($userSpec['affiliation'], $locale);
        }

        $user->setDateRegistered(Core::getCurrentDate());
        $user->setInlineHelp(1);
        $user->setPassword(Validation::encryptCredentials($username, $password));
        $user->setMustChangePassword(false);
        $user->setDisabled(false);

        Repo::user()->add($user);

        return Repo::user()->get($user->getId());
    }

    /**
     * The harness password rule: the username, doubled.
     */
    public static function defaultPasswordFor(string $username): string
    {
        return $username . $username;
    }

    /**
     * Values for a multilingual property in every locale the context supports.
     */
    protected function localized(Context $context, ?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $locales = $context->getSupportedFormLocales() ?: [$context->getPrimaryLocale()];

        return array_fill_keys($locales, $value);
    }

    /**
     * The initial workflow stage of this application.
     *
     * Read from the app's own stage roster rather than named: a hard-coded
     * initial stage once made every seeded OPS submission invisible.
     */
    protected function initialStageId(): int
    {
        $stages = Application::get()->getApplicationStages();

        if (empty($stages)) {
            throw new ScenarioException('The application declares no workflow stages.', null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return (int) reset($stages);
    }

    /**
     * Load the app-neutral schema for a spec, with this app's overlay merged in.
     */
    protected function schema(string $name): array
    {
        $schema = SpecValidator::load($name);

        if (isset($schema['properties']['users'])) {
            $schema['properties']['users']['items'] = $this->userSchema();
        }

        return SpecValidator::withOverlay($schema, $this->schemaOverlayProperties());
    }
}

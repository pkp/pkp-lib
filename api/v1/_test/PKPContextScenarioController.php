<?php

/**
 * @file api/v1/_test/PKPContextScenarioController.php
 *
 * Copyright (c) 2023-2026 Simon Fraser University
 * Copyright (c) 2023-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextScenarioController
 *
 * @ingroup api_v1__test
 *
 * @brief POST /api/v1/_test/scenarios/context — seed a scratch context.
 *
 * A scratch context is what a test uses when it needs to mutate context-level
 * state: the shared base context seeded by bootstrap is read-only. The spec is
 * declarative — a tag, a path, throwaway users with roles, a small set of
 * settings — and the builder walks the application to that state through the
 * real context service, so the scratch context has the genres, user groups,
 * email templates and navigation menus a real one has.
 */

namespace PKP\API\v1\_test;

use APP\core\Application;
use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\context\Context;
use PKP\invitation\core\enums\InvitationAction;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\services\PKPSchemaService;
use PKP\testing\scenario\ScenarioException;

class PKPContextScenarioController extends PKPTestApiController
{
    /**
     * Context spec keys that are structural rather than context settings.
     */
    protected const STRUCTURAL_KEYS = ['tag', 'urlPath', 'name', 'acronym', 'primaryLocale', 'supportedLocales', 'users', 'categories', 'invitations'];

    /**
     * How far past its own validity window a `status: 'expired'` invitation is placed.
     */
    protected const EXPIRED_BY_SECONDS = 86400;

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::post('scenarios/context', $this->addContext(...))->name('_test.scenarios.context');
    }

    public function addContext(Request $illuminateRequest): JsonResponse
    {
        return $this->build(function () use ($illuminateRequest) {
            $spec = $this->readSpec($illuminateRequest, $this->schema('context'));

            return $this->actingAs($this->siteAdmin(), function () use ($spec) {
                $context = $this->createContext($spec, '');
                $users = $this->seedUsers($context, $spec['users'] ?? [], 'users');
                $invitations = $this->inContext(
                    $context,
                    fn () => $this->seedInvitations($context, $spec['invitations'] ?? [], 'invitations')
                );

                return [
                    'tag' => $spec['tag'],
                    'contextId' => $context->getId(),
                    'urlPath' => $context->getPath(),
                    'users' => $users,
                    'invitations' => $invitations,
                ] + $this->contextEcho($context, $spec);
            });
        });
    }

    /**
     * Additional response fields an app wants echoed back (created section ids,
     * issue ids, ...). Ids are echoed so tests never have to look them up.
     */
    protected function contextEcho(Context $context, array $spec): array
    {
        return [];
    }

    /**
     * Build a context to the state the spec describes.
     *
     * @throws ScenarioException
     */
    protected function createContext(array $spec, string $specKeyPrefix): Context
    {
        $request = Application::get()->getRequest();
        $tag = $spec['tag'] ?? null;
        $urlPath = $spec['urlPath'] ?? ($tag ? 'scratch-' . $this->slug($tag) : null);
        $key = fn (string $name) => $specKeyPrefix === '' ? $name : "{$specKeyPrefix}.{$name}";

        if (!$urlPath) {
            throw new ScenarioException('A context spec needs a urlPath (or a tag to derive one from).', $key('urlPath'));
        }

        if (Application::getContextDAO()->getByPath($urlPath)) {
            throw new ScenarioException("A context already exists at urlPath '{$urlPath}'.", $key('urlPath'));
        }

        $site = $request->getSite();
        $primaryLocale = $spec['primaryLocale'] ?? $site->getPrimaryLocale();
        $supportedLocales = $spec['supportedLocales'] ?? [$primaryLocale];

        foreach ($supportedLocales as $index => $locale) {
            if (!in_array($locale, $site->getInstalledLocales())) {
                // Locales are chosen at INSTALL time, so this is not fixable from
                // the spec: name the remedy rather than leaving the reader to
                // discover that `installed_locales` exists.
                throw new ScenarioException(
                    "Locale '{$locale}' is not installed on this site (installed: "
                        . implode(', ', $site->getInstalledLocales()) . '). Add it to [i18n] '
                        . 'installed_locales in the configuration file and re-run '
                        . 'tools/installTest.php --recreate-db.',
                    $key("supportedLocales.{$index}")
                );
            }
        }

        $context = Application::getContextDAO()->newDataObject();
        $context->setData('primaryLocale', $primaryLocale);
        $context->setData('supportedLocales', array_values(array_unique(array_merge([$primaryLocale], $supportedLocales))));
        $context->setData('urlPath', $urlPath);
        $context->setData('name', array_fill_keys($context->getData('supportedLocales'), $spec['name'] ?? $urlPath));
        $context->setData('acronym', array_fill_keys($context->getData('supportedLocales'), $spec['acronym'] ?? strtoupper(substr($urlPath, 0, 6))));
        $context->setData('enabled', true);

        $context = app()->get('context')->add($context, $request);
        $this->journal->recordContext($context->getId());

        return $this->inContext($context, function () use ($context, $spec, $specKeyPrefix, $request, $key) {
            $settings = $this->settingsFromSpec($context, $spec);

            if ($settings) {
                $context = app()->get('context')->edit($context, $settings, $request);
            }

            $this->afterContextCreated($context, $spec, $specKeyPrefix);

            $this->seedCategories($context, $spec['categories'] ?? [], $key('categories'));

            return app()->get('context')->get($context->getId());
        });
    }

    /**
     * Turn declared setting passthroughs into an edit() payload.
     *
     * Only keys the schema declares reach this point, and each is written through
     * the real context service, so the same hooks and validation run as when an
     * editor saves the settings form.
     */
    protected function settingsFromSpec(Context $context, array $spec): array
    {
        $schema = app()->get('schema')->get(PKPSchemaService::SCHEMA_CONTEXT);
        $settings = [];

        foreach ($spec as $name => $value) {
            if (in_array($name, static::STRUCTURAL_KEYS) || in_array($name, $this->nonSettingOverlayKeys())) {
                continue;
            }

            if (!isset($schema->properties->{$name})) {
                continue;
            }

            $settings[$name] = ($schema->properties->{$name}->multilingual ?? false)
                ? $this->localized($context, (string) $value)
                : $value;
        }

        return $settings;
    }

    /**
     * Overlay keys that describe structure rather than a context setting
     * (OJS sections and issues, for instance).
     */
    protected function nonSettingOverlayKeys(): array
    {
        return [];
    }

    /**
     * Hook for the app subclass to seed its own context-level structure.
     */
    protected function afterContextCreated(Context $context, array $spec, string $specKeyPrefix): void
    {
    }

    /**
     * @throws ScenarioException
     */
    protected function seedCategories(Context $context, array $categorySpecs, string $specKeyPrefix): array
    {
        $byPath = [];

        foreach (array_values($categorySpecs) as $index => $categorySpec) {
            $parentId = null;

            if (isset($categorySpec['parentPath'])) {
                if (!isset($byPath[$categorySpec['parentPath']])) {
                    throw new ScenarioException(
                        "Parent category '{$categorySpec['parentPath']}' must be listed before its child.",
                        "{$specKeyPrefix}.{$index}.parentPath"
                    );
                }
                $parentId = $byPath[$categorySpec['parentPath']];
            }

            $category = Repo::category()->newDataObject();
            $category->setContextId($context->getId());
            $category->setPath($categorySpec['path']);
            $category->setTitle($categorySpec['title'], $context->getPrimaryLocale());

            if (isset($categorySpec['description'])) {
                $category->setDescription($categorySpec['description'], $context->getPrimaryLocale());
            }

            $category->setParentId($parentId);

            $byPath[$categorySpec['path']] = Repo::category()->add($category);
        }

        return $byPath;
    }

    /**
     * Seed the user-role invitations the spec asks for, in order.
     *
     * @throws ScenarioException
     */
    protected function seedInvitations(Context $context, array $invitationSpecs, string $specKeyPrefix): array
    {
        $seeded = [];

        foreach (array_values($invitationSpecs) as $index => $invitationSpec) {
            $seeded[] = $this->seedInvitation($context, $invitationSpec, "{$specKeyPrefix}.{$index}");
        }

        return $seeded;
    }

    /**
     * Send one user-role invitation, the way the Invite-a-user wizard sends it.
     *
     * The wizard is three API calls over one invitation object — add (create the
     * row for a user id OR an email address), populate (the roles and, for a
     * newcomer, the name the manager typed), invite (dispatch: key, expiry date,
     * email, PENDING) — so the builder walks the same three, through the
     * invitation type's own controller. Nothing here writes an invitation row by
     * hand, and a step the application refuses THROWS with its own error text.
     *
     * The recipient's accept/decline URLs are returned because they are the only
     * part of the state a test cannot see from the outside: they carry the
     * one-time key, which exists in plaintext for the length of this request and
     * is a bcrypt hash afterwards. Tests that are ABOUT the delivered email read
     * Mailpit instead; these URLs let every other test skip the inbox.
     *
     * @throws ScenarioException
     */
    protected function seedInvitation(Context $context, array $spec, string $specKey): array
    {
        $email = $spec['email'] ?? null;
        $username = $spec['user'] ?? null;

        if (isset($email) === isset($username)) {
            throw new ScenarioException(
                'An invitation names its recipient with exactly one of '
                    . "'email' (a newcomer with no account) or 'user' (an existing account).",
                $specKey
            );
        }

        $invitee = isset($username) ? $this->requireUser($username, "{$specKey}.user") : null;
        $inviter = isset($spec['inviter'])
            ? $this->requireUser($spec['inviter'], "{$specKey}.inviter")
            : $this->siteAdmin();

        $userGroupsToAdd = [];

        foreach (array_values($spec['roles']) as $roleIndex => $roleKey) {
            $group = $this->resolveUserGroup($context, $roleKey, "{$specKey}.roles.{$roleIndex}");
            $userGroupsToAdd[] = [
                'userGroupId' => $group->id,
                'masthead' => $spec['masthead'] ?? true,
                'dateStart' => Carbon::now()->format('Y-m-d'),
            ];
        }

        return $this->actingAs($inviter, function () use ($context, $spec, $specKey, $email, $invitee, $inviter, $userGroupsToAdd) {
            $invitation = app(Invitation::class)->createNew(UserRoleAssignmentInvite::getType());
            $controller = $invitation->getCreateInvitationController($invitation);

            // What InvitationController::add() does before delegating to the type's
            // own controller: an invitation is initialized for a user id OR an email,
            // never both, and remembers who sent it.
            $invitation->initialize($invitee?->getId(), $context->getId(), $invitee ? null : $email, $inviter->getId());
            $this->journal->recordInvitation($invitation->getId());
            $controller->add(app('request'));

            $payload = ['userGroupsToAdd' => $userGroupsToAdd];

            if (!$invitee) {
                // Only a newcomer's details are the manager's to type; for an existing
                // account the invitation form shows them read-only and the payload
                // rules prohibit sending them.
                foreach (['givenName', 'familyName', 'affiliation'] as $property) {
                    if (isset($spec[$property])) {
                        $payload[$property] = $this->localized($context, $spec[$property]);
                    }
                }

                if (isset($spec['country'])) {
                    $payload['userCountry'] = $spec['country'];
                }
            }

            $this->requireInvitationStep(
                $this->withRequestVars(
                    ['invitationData' => $payload],
                    fn () => $controller->populate(app('request'))
                ),
                'populate',
                $specKey
            );

            $this->requireInvitationStep($controller->invite(app('request')), 'invite', $specKey);

            if (($spec['status'] ?? 'pending') === 'expired') {
                $this->backdateBeyondExpiry($invitation);
            }

            $model = $invitation->invitationModel;

            return [
                'id' => $invitation->getId(),
                'status' => $model->status->value,
                'email' => $email ?? $invitee->getEmail(),
                'userId' => $invitee?->getId(),
                'roles' => array_values($spec['roles']),
                'invitedAt' => $model->createdAt?->format('Y-m-d H:i:s'),
                'expiryDate' => $model->expiryDate?->format('Y-m-d H:i:s'),
                'key' => $invitation->getKey(),
                'acceptUrl' => $invitation->getActionURL(InvitationAction::ACCEPT),
                'declineUrl' => $invitation->getActionURL(InvitationAction::DECLINE),
            ];
        });
    }

    /**
     * Move a dispatched invitation entirely into the past, so it is expired.
     *
     * This is the one place the builder writes state the application will not
     * write itself: `Invitation::invite()` always stamps the expiry date forward
     * from now, and `setExpiryDate()` refuses to run once the invitation has been
     * dispatched, so there is no service call that produces a lapsed invitation.
     * The alternative — shrinking `[invitations] expiration_days` in the running
     * configuration — is a global, cross-worker change and must not appear in a
     * test suite. The validity WINDOW is still the application's: it is read back
     * from the dates the app just wrote, and the whole invitation is slid
     * backwards so it lapsed one day ago, whatever that window is configured to be.
     */
    protected function backdateBeyondExpiry(Invitation $invitation): void
    {
        $model = $invitation->invitationModel;
        $window = $model->createdAt->diffInSeconds($model->expiryDate);
        $sentAt = Carbon::now()->subSeconds($window + static::EXPIRED_BY_SECONDS);

        $model->createdAt = $sentAt;
        $model->updatedAt = $sentAt;
        $model->expiryDate = (clone $sentAt)->addSeconds($window);

        // Eloquent would otherwise stamp updated_at with "now" and undo half of this.
        $model->timestamps = false;

        try {
            $model->save();
        } finally {
            $model->timestamps = true;
        }
    }

    /**
     * A wizard step the application refused must stop the build and say why.
     *
     * @throws ScenarioException
     */
    protected function requireInvitationStep(JsonResponse $response, string $step, string $specKey): void
    {
        if ($response->getStatusCode() === Response::HTTP_OK) {
            return;
        }

        $body = json_decode($response->getContent(), true) ?: [];

        throw new ScenarioException(
            "The invitation was refused at the '{$step}' step.",
            $specKey,
            Response::HTTP_BAD_REQUEST,
            $body['errors'] ?? $body
        );
    }

    protected function slug(string $value): string
    {
        return trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($value))), '-');
    }
}

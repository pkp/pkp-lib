<?php
/**
 * @file classes/user/maps/Schema.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map users to the properties defined in the user schema
 */

namespace PKP\user\maps;

use APP\facades\Repo;
use Illuminate\Support\Enumerable;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\user\User;

class Schema extends \PKP\core\maps\Schema
{
    public Enumerable $collection;

    public string $schema = PKPSchemaService::SCHEMA_USER;

    /**
     * Map a publication
     *
     * Includes all properties in the user schema.
     */
    public function map(User $item): array
    {
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a user
     *
     * Includes properties with the apiSummary flag in the user schema.
     */
    public function summarize(User $item): array
    {
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Summarize a user with reviewer data
     *
     * Includes properties with the apiSummary flag in the user schema.
     */
    public function summarizeReviewer(User $item): array
    {
        return $this->mapByProperties(array_merge($this->getSummaryProps(), ['reviewsActive', 'reviewsCompleted', 'reviewsDeclined', 'reviewsCancelled', 'averageReviewCompletionDays', 'dateLastReviewAssignment', 'reviewerRating']), $item);
    }

    /**
     * Map a collection of Users
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->map($item);
        });
    }

    /**
     * Summarize a collection of users
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->summarize($item);
        });
    }

    /**
     * Summarize a collection of reviewers
     *
     * @see self::summarizeReviewer
     */
    public function summarizeManyReviewers(Enumerable $collection): Enumerable
    {
        $this->collection = $collection;
        return $collection->map(function ($item) {
            return $this->summarizeReviewer($item);
        });
    }

    /**
     * Map schema properties of a user to an assoc array
     */
    protected function mapByProperties(array $props, User $user): array
    {
        $output = [];

        foreach ($props as $prop) {
            switch ($prop) {
                case 'id':
                    $output[$prop] = (int) $user->getId();
                    break;
                case 'fullName':
                    $output[$prop] = $user->getFullName();
                    break;
                case 'gossip':
                    if (Repo::user()->canCurrentUserGossip($user->getId())) {
                        $output[$prop] = $user->getGossip();
                    }
                    break;
                case 'reviewsActive':
                    $output[$prop] = $user->getData('incompleteCount');
                    break;
                case 'reviewsCompleted':
                    $output[$prop] = $user->getData('completeCount');
                    break;
                case 'reviewsDeclined':
                    $output[$prop] = $user->getData('declinedCount');
                    break;
                case 'reviewsCancelled':
                    $output[$prop] = $user->getData('cancelledCount');
                    break;
                case 'averageReviewCompletionDays':
                    $output[$prop] = $user->getData('averageTime');
                    break;
                case 'dateLastReviewAssignment':
                    $output[$prop] = $user->getData('lastAssigned');
                    break;
                case 'disabledReason':
                    $output[$prop] = $user->getDisabledReason();
                    break;
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'users/' . $user->getId(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'groups':
                    $output[$prop] = null;
                    if ($this->context) {
                        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
                        $userGroups = $userGroupDao->getByUserId($user->getId(), $this->context->getId());
                        $output[$prop] = [];
                        while ($userGroup = $userGroups->next()) {
                            $output[$prop][] = [
                                'id' => (int) $userGroup->getId(),
                                'name' => $userGroup->getName(null),
                                'abbrev' => $userGroup->getAbbrev(null),
                                'roleId' => (int) $userGroup->getRoleId(),
                                'showTitle' => (bool) $userGroup->getShowTitle(),
                                'permitSelfRegistration' => (bool) $userGroup->getPermitSelfRegistration(),
                                'permitMetadataEdit' => (bool) $userGroup->getPermitMetadataEdit(),
                                'recommendOnly' => (bool) $userGroup->getRecommendOnly(),
                            ];
                        }
                    }
                    break;
                case 'interests':
                    $output[$prop] = [];
                    if ($this->context) {
                        $interestDao = DAORegistry::getDAO('InterestDAO'); /** @var InterestDAO $interestDao */
                        $interestEntryIds = $interestDao->getUserInterestIds($user->getId());
                        if (!empty($interestEntryIds)) {
                            $interestEntryDao = DAORegistry::getDAO('InterestEntryDAO'); /** @var InterestEntryDAO $interestEntryDao */
                            $results = $interestEntryDao->getByIds($interestEntryIds);
                            $output[$prop] = [];
                            while ($interest = $results->next()) {
                                $output[$prop][] = [
                                    'id' => (int) $interest->getId(),
                                    'interest' => $interest->getInterest(),
                                ];
                            }
                        }
                    }
                    break;
                default:
                    $output[$prop] = $user->getData($prop);
                    break;
            }

            $output = $this->schemaService->addMissingMultilingualValues($this->schema, $output, $this->context->getSupportedFormLocales());

            Hook::call('UserSchema::getProperties::values', [$this, &$output, $user, $props]);

            ksort($output);
        }

        return $output;
    }
}

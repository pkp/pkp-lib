<?php

/**
 * @file classes/plugins/PubIdPlugin.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PubIdPlugin
 * @ingroup plugins
 *
 * @brief Public identifiers plugins common functions
 */

namespace APP\plugins;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\publication\Publication;
use APP\submission\Submission;

use PKP\context\Context;
use PKP\core\DataObject;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\core\PKPString;

use PKP\plugins\PKPPubIdPlugin;
use PKP\submission\Representation;
use PKP\submissionFile\SubmissionFile;

abstract class PubIdPlugin extends PKPPubIdPlugin
{
    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        $user = $request->getUser();
        $router = $request->getRouter();
        $context = $router->getContext($request);

        $notificationManager = new NotificationManager();
        switch ($request->getUserVar('verb')) {
            case 'assignPubIds':
                if (!$request->checkCSRF()) {
                    return new JSONMessage(false);
                }
                return $this->assignPubIds($request, $context);
            default:
                return parent::manage($args, $request);
        }
    }

    /**
     * Handles pubId assignment for any submission, galley, or issue pubIds
     */
    protected function assignPubIds(PKPRequest $request, Context $context): JSONMessage
    {
        $suffixFieldName = $this->getSuffixFieldName();
        $suffixGenerationStrategy = $this->getSetting($context->getId(), $suffixFieldName);
        if ($suffixGenerationStrategy != 'customId') {
            $submissionEnabled = $this->isObjectTypeEnabled('Publication', $context->getId());
            $representationEnabled = $this->isObjectTypeEnabled('Representation', $context->getId());
            if ($submissionEnabled || $representationEnabled) {
                $representationDao = Application::getRepresentationDAO();
                $submissions = Repo::submission()
                        ->getCollector()
                        ->filterByContextIds([$context->getId()])
                        ->filterByStatus([Submission::STATUS_PUBLISHED])
                        ->getMany();

                foreach ($submissions as $submission) {
                    $publications = $submission->getData('publications');
                    if ($submissionEnabled) {
                        foreach ($publications as $publication) {
                            $publicationPubId = $publication->getStoredPubId($this->getPubIdType());
                            if (empty($publicationPubId)) {
                                $publicationPubId = $this->getPubId($publication);
                                Repo::publication()->dao->changePubId($publication->getId(), $this->getPubIdType(), $publicationPubId);
                            }
                        }
                    }
                    if ($representationEnabled) {
                        foreach ($publications as $publication) {
                            $representations = Repo::galley()->getCollector()
                                ->filterByPublicationIds([$publication->getId()])
                                ->getMany();

                            foreach ($representations as $representation) {
                                $representationPubId = $representation->getStoredPubId($this->getPubIdType());
                                if (empty($representationPubId)) {
                                    $representationPubId = $this->getPubId($representation);
                                    $representationDao->changePubId($representation->getId(), $this->getPubIdType(), $representationPubId);
                                }
                            }
                        }
                    }
                }
            }
        }
        return new JSONMessage(true);
    }

    //
    // Protected template methods from PKPPlubIdPlugin
    //

    /**
     * @copydoc PKPPubIdPlugin::getPubId()
     */
    public function getPubId($pubObject)
    {
        // Get the pub id type
        $pubIdType = $this->getPubIdType();

        // If we already have an assigned pub id, use it.
        $storedPubId = $pubObject->getStoredPubId($pubIdType);
        if ($storedPubId) {
            return $storedPubId;
        }

        // Determine the type of the publishing object.
        $pubObjectType = $this->getPubObjectType($pubObject);

        // Initialize variables for publication objects.
        $submission = ($pubObjectType == 'Submission' ? $pubObject : null);
        $representation = ($pubObjectType == 'Representation' ? $pubObject : null);
        $submissionFile = ($pubObjectType == 'SubmissionFile' ? $pubObject : null);

        // Get the context id.
        if ($pubObjectType === 'Representation') {
            $publication = Repo::publication()->get($pubObject->getData('publicationId'));
            $submission = Repo::submission()->get($publication->getData('submissionId'));
            $contextId = $submission->getData('contextId');
        } elseif ($pubObjectType === 'Publication') {
            $submission = Repo::submission()->get($pubObject->getData('submissionId'));
            $publication = Repo::publication()->get($pubObject->getId());
            $contextId = $submission->getData('contextId');
        } elseif ($pubObjectType === 'SubmissionFile') {
            $submission = Repo::submission()->get($pubObject->getData('submissionId'));
            $contextId = $submission->getData('contextId');
        }


        // Check the context
        $context = $this->getContext($contextId);
        if (!$context) {
            return null;
        }
        $contextId = $context->getId();

        // Check whether pub ids are enabled for the given object type.
        $objectTypeEnabled = $this->isObjectTypeEnabled($pubObjectType, $contextId);
        if (!$objectTypeEnabled) {
            return null;
        }

        // Retrieve the pub id prefix.
        $pubIdPrefix = $this->getSetting($contextId, $this->getPrefixFieldName());
        if (empty($pubIdPrefix)) {
            return null;
        }

        // Generate the pub id suffix.
        $suffixFieldName = $this->getSuffixFieldName();
        $suffixGenerationStrategy = $this->getSetting($contextId, $suffixFieldName);
        switch ($suffixGenerationStrategy) {
            case 'customId':
                $pubIdSuffix = $pubObject->getData($suffixFieldName);
                break;

            case 'pattern':
                $suffixPatternsFieldNames = $this->getSuffixPatternsFieldNames();
                $pubIdSuffix = $this->getSetting($contextId, $suffixPatternsFieldNames[$pubObjectType]);

                $pubIdSuffix = $this::generateCustomPattern($context, $pubIdSuffix, $pubObject, $submission, $publication ?? null, $representation, $submissionFile);

                break;

            default:
                $pubIdSuffix = $this::generateDefaultPattern($context, $submission, $representation, $submissionFile);
        }
        if (empty($pubIdSuffix)) {
            return null;
        }

        // Construct the pub id from prefix and suffix.
        $pubId = $this->constructPubId($pubIdPrefix, $pubIdSuffix, $contextId);

        return $pubId;
    }

    /**
     * Generate the default, semantic-based pub-id pattern suffix
     *
     */
    public static function generateDefaultPattern(Context $context, ?Submission $submission = null, ?Representation $representation = null, ?SubmissionFile $submissionFile = null): string
    {
        $pubIdSuffix = PKPString::regexp_replace('/[^A-Za-z0-9]/', '', PKPString::strtolower($context->getAcronym($context->getPrimaryLocale())));

        if ($submission) {
            $pubIdSuffix .= '.' . $submission->getId();
        }

        if ($representation) {
            $pubIdSuffix .= '.g' . $representation->getId();
        }

        if ($submissionFile) {
            $pubIdSuffix .= '.f' . $submissionFile->getId();
        }

        return $pubIdSuffix;
    }

    /**
     * Generate the custom, user-defined pub-id pattern suffix
     *
     */
    public static function generateCustomPattern(Context $context, string $pubIdSuffix, DataObject $pubObject, ?Submission $submission = null, ?Publication $publication = null, ?Representation $representation = null, ?SubmissionFile $submissionFile = null): string
    {
        // %j - server initials, remove special characters and uncapitalize
        $pubIdSuffix = PKPString::regexp_replace('/%j/', PKPString::regexp_replace('/[^A-Za-z0-9]/', '', PKPString::strtolower($context->getAcronym($context->getPrimaryLocale()))), $pubIdSuffix);

        // %x - custom identifier
        if ($pubObject->getStoredPubId('publisher-id')) {
            $pubIdSuffix = PKPString::regexp_replace('/%x/', $pubObject->getStoredPubId('publisher-id'), $pubIdSuffix);
        }

        if ($submission) {
            // %a - preprint id
            $pubIdSuffix = PKPString::regexp_replace('/%a/', $submission->getId(), $pubIdSuffix);
        }

        if ($publication) {
            // %b - publication id
            $pubIdSuffix = PKPString::regexp_replace('/%b/', $publication->getId(), $pubIdSuffix);
        }

        if ($representation) {
            // %g - galley id
            $pubIdSuffix = PKPString::regexp_replace('/%g/', $representation->getId(), $pubIdSuffix);
        }

        if ($submissionFile) {
            // %f - file id
            $pubIdSuffix = PKPString::regexp_replace('/%f/', $submissionFile->getId(), $pubIdSuffix);
        }

        return $pubIdSuffix;
    }

    /**
     * Version a publication pubId
     */
    public function versionPubId($pubObject)
    {
        $pubObjectType = $this->getPubObjectType($pubObject);
        $submission = Repo::submission()->get($pubObject->getData('submissionId'));
        $publication = Repo::publication()->get($pubObject->getId());
        $contextId = $submission->getData('contextId');

        // Check the context
        $context = $this->getContext($contextId);
        if (!$context) {
            return null;
        }
        $contextId = $context->getId();

        // Check whether pub ids are enabled for the given object type.
        $objectTypeEnabled = $this->isObjectTypeEnabled($pubObjectType, $contextId);
        if (!$objectTypeEnabled) {
            return null;
        }

        // Retrieve the pub id prefix.
        $pubIdPrefix = $this->getSetting($contextId, $this->getPrefixFieldName());
        if (empty($pubIdPrefix)) {
            return null;
        }

        // Retrieve the pub id suffix.
        $suffixPatternsFieldNames = $this->getSuffixPatternsFieldNames();

        $pubIdSuffix = $this->getSetting($contextId, $suffixPatternsFieldNames[$pubObjectType]);

        // %j - server initials
        $pubIdSuffix = PKPString::regexp_replace('/%j/', PKPString::regexp_replace('/[^A-Za-z0-9]/', '', PKPString::strtolower($context->getAcronym($context->getPrimaryLocale()))), $pubIdSuffix);

        // %x - custom identifier
        if ($pubObject->getStoredPubId('publisher-id')) {
            $pubIdSuffix = PKPString::regexp_replace('/%x/', $pubObject->getStoredPubId('publisher-id'), $pubIdSuffix);
        }

        // %a - preprint id
        if ($submission) {
            $pubIdSuffix = PKPString::regexp_replace('/%a/', $submission->getId(), $pubIdSuffix);
        }

        // %b - publication id
        if ($publication) {
            $pubIdSuffix = PKPString::regexp_replace('/%b/', $publication->getId(), $pubIdSuffix);
        }

        if (empty($pubIdSuffix)) {
            return null;
        }

        // Costruct the pub id from prefix and suffix.
        $pubId = $this->constructPubId($pubIdPrefix, $pubIdSuffix, $contextId);

        return $pubId;
    }
}

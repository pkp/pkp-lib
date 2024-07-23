<?php

/**
 * @file classes/orcid/PKPOrcidWork.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPOrcidWork
 *
 * @brief Builds ORCID work object for deposit
 */

namespace PKP\orcid;

use APP\author\Author;
use APP\core\Application;
use APP\facades\Repo;
use APP\plugins\PubIdPlugin;
use APP\publication\Publication;
use APP\submission\Submission;
use Carbon\Carbon;
use PKP\context\Context;
use PKP\i18n\LocaleConversion;
use PKP\plugins\PluginRegistry;

abstract class PKPOrcidWork
{
    public const PUBID_TO_ORCID_EXT_ID = ['doi' => 'doi', 'other::urn' => 'urn'];
    public const USER_GROUP_TO_ORCID_ROLE = ['Author' => 'AUTHOR', 'Translator' => 'CHAIR_OR_TRANSLATOR', 'Journal manager' => 'AUTHOR'];

    protected array $data = [];

    public function __construct(
        protected Publication $publication,
        protected Context $context,
        protected array $authors,
    ) {
        $this->data = $this->build();
    }

    /**
     * Returns ORCID work data as an associative array, ready for deposit.
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * Builds the internal data structure for the ORCID work.
     */
    private function build(): array
    {
        $submission = Repo::submission()->get($this->publication->getData('submissionId'));

        $publicationLocale = $this->publication->getData('locale');

        $request = Application::get()->getRequest();

        $publicationUrl = Application::get()->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            $this->context->getPath(),
            'article',
            'view',
            $submission->getId(),
            urlLocaleForPage: '',
        );

        $orcidWork = [
            'title' => [
                'title' => [
                    'value' => trim(strip_tags($this->publication->getLocalizedTitle($publicationLocale))) ?? ''
                ],
                'subtitle' => [
                    'value' => trim(strip_tags($this->publication->getLocalizedData('subtitle', $publicationLocale))) ?? ''
                ]
            ],
            'journal-title' => [
                'value' => $this->context->getName($publicationLocale) ?? $this->context->getName($this->context->getPrimaryLocale()),
            ],
            'short-description' => trim(strip_tags($this->publication->getLocalizedData('abstract', $publicationLocale))) ?? '',

            'external-ids' => [
                'external-id' => $this->buildOrcidExternalIds($submission, $this->publication, $this->context, $publicationUrl)
            ],
            'publication-date' => $this->buildOrcidPublicationDate($this->publication),
            'url' => $publicationUrl,
            'language-code' => LocaleConversion::getIso1FromLocale($publicationLocale),
            'contributors' => [
                'contributor' => $this->buildOrcidContributors($this->authors, $this->context, $this->publication)
            ]
        ];

        $bibtexCitation = $this->getBibtexCitation($submission);
        if (!empty($bibtexCitation)) {
            $orcidWork['citation'] = [
                'citation-type' => 'bibtex',
                'citation-value' => $bibtexCitation,
            ];
        }

        $orcidWork['type'] = $this->getOrcidPublicationType();

        foreach ($this->publication->getData('title') as $locale => $title) {
            if ($locale !== $publicationLocale) {
                $orcidWork['title']['translated-title'] = ['value' => $title, 'language-code' => LocaleConversion::getIso1FromLocale($locale)];
            }
        }

        return $orcidWork;
    }

    /**
     * Build the external identifiers ORCID JSON structure from article, journal and issue meta data.
     *
     * @see  https://pub.orcid.org/v2.0/identifiers Table of valid ORCID identifier types.
     *
     * @param Publication $publication The publication object for which the external identifiers should be built.
     * @param Context $context Context the publication is part of.
     * @param string $publicationUrl Resolving URL for the publication.
     *
     * @return array            An associative array corresponding to ORCID external-id JSON.
     */
    private function buildOrcidExternalIds(Submission $submission, Publication $publication, Context $context, string $publicationUrl): array
    {
        $contextId = $context->getId();

        $externalIds = [];
        $pubIdPlugins = PluginRegistry::loadCategory('pubIds', true, $contextId);
        // Add doi, urn, etc. for article
        $articleHasStoredPubId = false;

        // Handle non-DOI pubIds
        if (!empty($pubIdPlugins)) {
            foreach ($pubIdPlugins as $plugin) {
                if (!$plugin->getEnabled()) {
                    continue;
                }

                $pubIdType = $plugin->getPubIdType();

                # Add article ids
                $pubId = $publication->getStoredPubId($pubIdType);

                if ($pubId) {
                    $externalIds[] = [
                        'external-id-type' => self::PUBID_TO_ORCID_EXT_ID[$pubIdType],
                        'external-id-value' => $pubId,
                        'external-id-url' => [
                            'value' => $plugin->getResolvingURL($contextId, $pubId)
                        ],
                        'external-id-relationship' => 'self'
                    ];

                    $articleHasStoredPubId = true;
                }

                # Add app-specific ids if they exist
                $appSpecificOtherIds = $this->getAppPubIdExternalIds($plugin);
                if (!empty($appSpecificOtherIds)) {
                    foreach ($appSpecificOtherIds as $appSpecificOtherId) {
                        $externalIds[] = $appSpecificOtherId;
                    }
                }
            }
        }

        // Handle DOIs
        if ($context->areDoisEnabled()) {
            # Add article ids
            $publicationDoiObject = $publication->getData('doiObject');

            if ($publicationDoiObject) {
                $externalIds[] = [
                    'external-id-type' => self::PUBID_TO_ORCID_EXT_ID['doi'],
                    'external-id-value' => $publicationDoiObject->getData('doi'),
                    'external-id-url' => [
                        'value' => $publicationDoiObject->getResolvingUrl()
                    ],
                    'external-id-relationship' => 'self'
                ];

                $articleHasStoredPubId = true;
            }

            // Add apps-specific ids if they exist
            $appSpecificDoiIds = $this->getAppDoiExternalIds();
            if (!empty($appSpecificDoiIds)) {
                foreach ($appSpecificDoiIds as $appSpecificDoiId) {
                    $externalIds[] = $appSpecificDoiId;
                }

                $articleHasStoredPubId = true;
            }
        }

        if (!$articleHasStoredPubId) {
            // No pubidplugins available or article does not have any stored pubid
            // Use URL as an external-id
            $externalIds[] = [
                'external-id-type' => 'uri',
                'external-id-value' => $publicationUrl,
                'external-id-relationship' => 'self'
            ];
        }

        // Add journal online ISSN, if it exists
        if ($context->getData('onlineIssn')) {
            $externalIds[] = [
                'external-id-type' => 'issn',
                'external-id-value' => $context->getData('onlineIssn'),
                'external-id-relationship' => 'part-of'
            ];
        }

        return $externalIds;
    }

    /**
     * Parse publication date and use  as the publication date of the ORCID work.
     *
     * @return array Associative array with year, month and day
     */
    private function buildOrcidPublicationDate(Publication $publication): array
    {
        $publicationPublishDate = Carbon::parse($publication->getData('datePublished'));

        return [
            'year' => ['value' => $publicationPublishDate->format('Y')],
            'month' => ['value' => $publicationPublishDate->format('m')],
            'day' => ['value' => $publicationPublishDate->format('d')]
        ];
    }

    /**
     * Build associative array fitting for ORCID contributor mentions in an
     * ORCID work from the supplied Authors array.
     *
     * @param Author[] $authors Array of Author objects
     *
     * @return array[]           Array of associative arrays,
     *                           one for each contributor
     */
    private function buildOrcidContributors(array $authors, Context $context, Publication $publication): array
    {
        $contributors = [];
        $first = true;

        foreach ($authors as $author) {
            $contributor = [
                'credit-name' => $author->getFullName(),
                'contributor-attributes' => [
                    'contributor-sequence' => $first ? 'first' : 'additional'
                ]
            ];

            $userGroup = $author->getUserGroup();
            $role = self::USER_GROUP_TO_ORCID_ROLE[$userGroup->getName('en')];

            if ($role) {
                $contributor['contributor-attributes']['contributor-role'] = $role;
            }

            if ($author->getOrcid()) {
                $orcid = basename(parse_url($author->getOrcid(), PHP_URL_PATH));

                if ($author->getData('orcidSandbox')) {
                    $uri = ORCID_URL_SANDBOX . $orcid;
                    $host = 'sandbox.orcid.org';
                } else {
                    $uri = $author->getOrcid();
                    $host = 'orcid.org';
                }

                $contributor['contributor-orcid'] = [
                    'uri' => $uri,
                    'path' => $orcid,
                    'host' => $host
                ];
            }

            $first = false;

            $contributors[] = $contributor;
        }

        return $contributors;
    }

    /**
     * Gets any non-DOI PubId external IDs, e.g. for Issues
     *
     * @param PubIdPlugin $plugin
     * @return array
     */
    protected function getAppPubIdExternalIds(PubIdPlugin $plugin): array
    {
        return [];
    }

    /**
     * Gets any app-specific DOI external IDs, e.g. for Issues
     *
     * @return array
     */
    protected function getAppDoiExternalIds(): array
    {
        return [];
    }

    /**
     * Uses the CitationStyleLanguage plugin to get bibtex citation if possible
     *
     * @param Submission $submission
     * @return string
     */
    protected function getBibtexCitation(Submission $submission): string
    {
        return '';
    }

    /**
     * Get app-specific 'type' of work for item
     */
    abstract protected function getOrcidPublicationType(): string;
}

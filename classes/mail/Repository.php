<?php
/**
 * @file classes/mailable/Repository.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to find and edit Mailables.
 */

namespace PKP\mail;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\PKPString;
use PKP\mail\mailables\StatisticsReportNotify;
use PKP\mail\mailables\SubmissionAcknowledgement;
use PKP\mail\mailables\SubmissionAcknowledgementNotAuthor;

class Repository
{
    /**
     * Get an array of mailables
     */
    public function getMany(Context $context, ?string $searchPhrase = null, ?bool $includeDisabled = false): Collection
    {
        return collect(Mail::getMailables($context->getId()))
            ->filter(fn(string $class) => !$searchPhrase || $this->containsSearchPhrase($class, $searchPhrase))
            ->filter(function(string $class) use ($context, $includeDisabled) {
                if ($includeDisabled) {
                    return true;
                }
                if ($class === StatisticsReportNotify::class) {
                    return $context->getData('editorialStatsEmail');
                } elseif (in_array($class, [SubmissionAcknowledgement::class, SubmissionAcknowledgementNotAuthor::class])) {
                    $setting = $context->getData('submissionAcknowledgement');
                    if ($setting === Context::SUBMISSION_ACKNOWLEDGEMENT_ALL_AUTHORS) {
                        return true;
                    } elseif ($setting === Context::SUBMISSION_ACKNOWLEDGEMENT_OFF) {
                        return false;
                    } elseif ($class === SubmissionAcknowledgementNotAuthor::class) {
                        return false;
                    }
                    return true;
                }
                return true;
            })
            ->map(fn(string $class) => $this->summarizeMailable($class))
            ->sortBy('name');
    }

    /**
     * Simple check if mailable's name and description contains a search phrase
     * doesn't look up in associated email templates
     *
     * @param string $className the fully qualified class name of the Mailable
     */
    protected function containsSearchPhrase(string $className, string $searchPhrase): bool
    {
        $searchPhrase = PKPString::strtolower($searchPhrase);

        /** @var Mailable $className */
        return str_contains(PKPString::strtolower($className::getName()), $searchPhrase) ||
            str_contains(PKPString::strtolower($className::getDescription()), $searchPhrase);
    }

    /**
     * Get a mailable by its email template key
     */
    public function get(string $emailTemplateKey, int $contextId): ?array
    {
        /** @var ?string $mailable */
        $mailable = collect(Mail::getMailables($contextId))
            ->first(fn(string $mailable) => $mailable::getEmailTemplateKey() === $emailTemplateKey);

        if (!$mailable) {
            return null;
        }

        return $this->describeMailable($mailable, $contextId);
    }

    protected function summarizeMailable(string $class): array
    {
        $dataDescriptions = $class::getDataDescriptions();
        ksort($dataDescriptions);

        return [
            '_href' => Application::get()->getRequest()->getDispatcher()->url(
                Application::get()->getRequest(),
                Application::ROUTE_API,
                Application::get()->getRequest()->getContext()->getPath(),
                'mailables/' . $class::getEmailTemplateKey(),
            ),
            'dataDescriptions' => $dataDescriptions,
            'description' => $class::getDescription(),
            'emailTemplateKey' => $class::getEmailTemplateKey(),
            'fromRoleIds' => $class::getFromRoleIds(),
            'groupIds' => $class::getGroupIds(),
            'name' => $class::getName(),
            'supportsTemplates' => $class::getSupportsTemplates(),
            'toRoleIds' => $class::getToRoleIds(),
        ];
    }

    /**
     * Gets information about the mailable with its assigned templates
     */
    protected function describeMailable(string $class, int $contextId): array
    {
        $data = $this->summarizeMailable($class);

        if (!$class::getSupportsTemplates()) {
            $data['emailTemplates'] = [];
        } else {
            $templates = Repo::emailTemplate()
                ->getCollector()
                ->filterByContext($contextId)
                ->alternateTo([$class::getEmailTemplateKey()])
                ->getMany();

            $defaultTemplate = Repo::emailTemplate()->getByKey($contextId, $class::getEmailTemplateKey());

            $data['emailTemplates'] = Repo::emailTemplate()
                ->getSchemaMap()
                ->summarizeMany(
                    collect(
                        array_merge(
                            [$defaultTemplate],
                            $templates->values()->toArray()
                        )
                    )
                )
                ->values();
        }


        return $data;
    }
}

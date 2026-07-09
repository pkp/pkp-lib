<?php

/**
 * @file classes/funder/Repository.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A helper class to handle operations with Funder data
 */

namespace PKP\funder;

use APP\core\Application;
use APP\core\Request;
use DateInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PKP\context\Context;
use PKP\facades\Locale;
use PKP\i18n\LocaleConversion;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    /** @var string Backstop max lifetime for the funder facet cache; see getUniqueFunderNames(). */
    public const FUNDER_FACET_CACHE_LIFETIME = '30 days';

    protected Request $request;

    /** @var PKPSchemaService<Funder> $schemaService */
    protected PKPSchemaService $schemaService;

    // Funders with awards that can be validated via the Zenodo API, and their corresponding ROR IDs.
    public const AWARD_FUNDERS = [
        '05k73zm37', // Research Council of Finland
        '00rbzpz17', // French National Research Agency
        '05mmh0f86', // Australian Research Council
        '03zj4c476', // Aligning Science Across Parkinson's
        '01gavpb45', // Canadian Institutes of Health Research
        '00k4n6c32', // European Commission
        '02k4b9v70', // European Environment Agency
        '00snfqn58', // Portuguese Science and Technology Foundation
        '013tf3c58', // Austrian Science Fund
        '03m8vkq32', // The French National Cancer Institute
        '03n51vw80', // Croatian Science Foundation
        '02ar66p97', // Latvian Council of Science
        '01znas443', // Ministry of Education, Science and Technological Development of the Republic of Serbia
        '011kf5r70', // National Health and Medical Research Council
        '01cwqze88', // National Institutes of Health
        '01h531d29', // Natural Sciences and Engineering Research Council of Canada
        '021nxhr62', // National Science Foundation
        '04jsz6e67', // Dutch Research Council
        '00dq2kk65', // Research Councils UK
        '0271asj38', // Science Foundation Ireland
        '00yjd3n13', // Swiss National Science Foundation
        '006cvnv84', // Social Science Research Council
        '04w9kkr77', // Scientific and Technological Research Council of Turkey
        '00x0z1472', // Templeton World Charity Foundation
        '001aqnf71', // UK Research and Innovation
        '029chgv08', // Wellcome Trust
    ];

    public function __construct(Request $request, PKPSchemaService $schemaService)
    {
        $this->request = $request;
        $this->schemaService = $schemaService;
    }

    /**
     * Validate properties for a funder
     *
     * Perform validation checks on data used to add or edit a funder
     *
     * @param Funder|null $funder Funder being edited. Pass `null` if creating a new funder
     * @param array $props A key/value array with the new data to validate
     *
     * @return array A key/value array with validation errors. Empty if no errors
     *
     * @hook Funder::validate [[&$errors, $funder, $props]]
     */
    public function validate(?Funder $funder, array $props): array
    {
        $schema = Funder::getSchemaName();

        $validator = ValidatorFactory::make(
            $props,
            $this->schemaService->getValidationRules($schema, [])
        );

        // Check required fields
        ValidatorFactory::required(
            $validator,
            $funder,
            $this->schemaService->getRequiredProps($schema),
            $this->schemaService->getMultilingualProps($schema),
            [],
            ''
        );

        // Validate grant numbers if funder grant validation is enabled and a ROR ID is provided
        $context = $this->request->getContext();
        $funderGrantValidationSetting = (bool) $context?->getData('funderGrantValidation');

        if ($funderGrantValidationSetting) {
            $ror = $props['ror'] ?? null;

            if ($ror) {
                $ror = preg_replace('#^https?://ror\.org/#i', '', trim($ror));

                if (in_array($ror, self::AWARD_FUNDERS, true)) {

                    $validator->after(function ($validator) use ($props, $ror) {

                        $grants = $props['grants'] ?? [];
                        $httpClient = Application::get()->getHttpClient();

                        foreach ($grants as $key => $grant) {
                            $grantNumber = $grant['grantNumber'] ?? null;

                            if (!$grantNumber) {
                                continue;
                            }

                            try {
                                $awardResponse = $httpClient->request(
                                    'GET',
                                    "https://zenodo.org/api/awards?funders={$ror}&q=" . urlencode($grantNumber),
                                    ['timeout' => 5]
                                );

                                $body = json_decode($awardResponse->getBody(), true);

                                if ($body === null) {
                                    throw new \Exception('Zenodo returned a non-JSON response');
                                }

                                $success = array_reduce(
                                    $body['hits']['hits'] ?? [],
                                    fn ($carry, $item) => $carry || $item['number'] == $grantNumber,
                                    false
                                );

                                if (!$success) {
                                    $validator->errors()->add("grants.{$key}.grantNumber", __('submission.funders.grantNumberInvalid'));
                                }
                            } catch (\Exception $e) {
                                // Zenodo unavailable or timed out — skip validation silently
                                continue;
                            }
                        }
                    });
                }
            }
        }

        // Validate that either a ror or funder name exists
        $validator->after(function ($validator) use ($props) {
            if (empty($props['ror']) && empty(array_filter($props['name'] ?? []))) {
                $validator->errors()->add('funder', __('submission.funders.funderNameOrRorRequired'));
            }
        });

        $errors = [];

        if ($validator->fails()) {
            $errors = $this->schemaService->formatValidationErrors($validator->errors());
        }

        Hook::call('Funder::validate', [&$errors, $funder, $props]);

        return $errors;
    }

    /**
     * Get an instance of the map class for mapping
     * data citations to their schema
     */
    public function getSchemaMap(): maps\Schema
    {
        return app('maps')->withExtensions($this->schemaMap);
    }

    /**
     * Get the distinct funders attached to submissions in a context, for use as facets in a
     * "browse/search by funder" UI.
     *
     * Each entry has a `value` (ror, or else a lowercased name — no canonical id exists for
     * manually-entered funders, so same-name entries just collapse together; see
     * Collector::filterByFunder()) and a `label` (display name, resolved for the given locale).
     *
     * Not scoped to published/current-publication-status — a funder name isn't sensitive the
     * way unpublished content is, so callers filtering results for a reader must apply that
     * scoping themselves via Collector::filterByFunder().
     *
     * @return Collection<int,array{value:string,label:?string}>
     */
    public function getUniqueFunderNames(int $contextId, ?string $locale = null): Collection
    {
        $locale ??= Locale::getLocale();
        $cacheKey = $this->getFacetCacheKey($contextId, $locale);
        $expiration = DateInterval::createFromDateString(self::FUNDER_FACET_CACHE_LIFETIME);

        return Cache::remember($cacheKey, $expiration, function () use ($contextId, $locale) {
            // ror_settings.locale is keyed by the bare ISO 639-1 code from the ROR dataset itself
            $rorLocale = LocaleConversion::getIso1FromLocale($locale);

            $rows = DB::select('
                SELECT
                    COALESCE(x.ror, LOWER(TRIM(x.identity_name))) AS value,
                    MIN(COALESCE(x.label, x.ror)) AS label
                FROM (
                    SELECT
                        f.submission_id,
                        f.ror,
                        COALESCE(
                            (SELECT fs.setting_value FROM funder_settings fs
                             WHERE fs.funder_id = f.funder_id AND fs.setting_name = \'name\' AND fs.locale = s.locale),
                            (SELECT fs.setting_value FROM funder_settings fs
                             WHERE fs.funder_id = f.funder_id AND fs.setting_name = \'name\'
                             ORDER BY fs.locale LIMIT 1),
                            (SELECT rs.setting_value FROM rors r
                             JOIN ror_settings rs ON rs.ror_id = r.ror_id AND rs.setting_name = \'name\' AND rs.locale = r.display_locale
                             WHERE r.ror = f.ror)
                        ) AS identity_name,
                        COALESCE(
                            (SELECT fs.setting_value FROM funder_settings fs
                             WHERE fs.funder_id = f.funder_id AND fs.setting_name = \'name\' AND fs.locale = ?),
                            (SELECT fs.setting_value FROM funder_settings fs
                             WHERE fs.funder_id = f.funder_id AND fs.setting_name = \'name\' AND fs.locale = s.locale),
                            (SELECT fs.setting_value FROM funder_settings fs
                             WHERE fs.funder_id = f.funder_id AND fs.setting_name = \'name\'
                             ORDER BY fs.locale LIMIT 1),
                            (SELECT rs.setting_value FROM rors r
                             JOIN ror_settings rs ON rs.ror_id = r.ror_id AND rs.setting_name = \'name\' AND rs.locale = ?
                             WHERE r.ror = f.ror),
                            (SELECT rs.setting_value FROM rors r
                             JOIN ror_settings rs ON rs.ror_id = r.ror_id AND rs.setting_name = \'name\' AND rs.locale = r.display_locale
                             WHERE r.ror = f.ror)
                        ) AS label
                    FROM funders f
                    JOIN submissions s ON s.submission_id = f.submission_id
                    WHERE s.context_id = ?
                ) x
                WHERE x.ror IS NOT NULL OR x.identity_name IS NOT NULL
                GROUP BY COALESCE(x.ror, LOWER(TRIM(x.identity_name)))
                ORDER BY label
            ', [
                $locale,
                $rorLocale,
                $contextId,
            ]);

            return collect($rows)->map(fn ($row) => (array) $row);
        });
    }

    /**
     * Invalidate the cached funder facet list (see getUniqueFunderNames()) for a context, in all
     * of its supported locales.
     */
    public function forgetFunderFacetCache(int $contextId): void
    {
        /** @var Context|null $context */
        $context = Application::getContextDAO()->getById($contextId);
        if (!$context) {
            return;
        }

        // A cache key's locale is the viewer's current UI locale, not necessarily a locale the
        // funder's name data exists in — so enumerate supportedLocales as well as
        // supportedSubmissionMetadataLocales, not just the latter.
        $locales = array_unique(array_merge(
            [$context->getPrimaryLocale()],
            $context->getSupportedLocales() ?? [],
            $context->getSupportedSubmissionMetadataLocales() ?? []
        ));

        foreach ($locales as $locale) {
            Cache::forget($this->getFacetCacheKey($contextId, $locale));
        }
    }

    protected function getFacetCacheKey(int $contextId, string $locale): string
    {
        return self::class . "::getUniqueFunderNames:{$contextId}:{$locale}";
    }

    /**
     * Invalidate the funder facet cache for every context that has funder data — for triggers
     * that can affect any context and can't cheaply say which, e.g. a ROR dataset refresh.
     */
    public function forgetAllFunderFacetCaches(): void
    {
        $contextIds = DB::table('funders')
            ->join('submissions', 'submissions.submission_id', '=', 'funders.submission_id')
            ->distinct()
            ->pluck('submissions.context_id');

        foreach ($contextIds as $contextId) {
            $this->forgetFunderFacetCache((int) $contextId);
        }
    }
}

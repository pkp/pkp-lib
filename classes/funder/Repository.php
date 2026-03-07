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
use PKP\context\Context;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\validation\ValidatorFactory;

class Repository
{
    /** @var string $schemaMap The name of the class to map this entity to its schema */
    public string $schemaMap = maps\Schema::class;

    protected Request $request;

    /** @var PKPSchemaService<Funder> $schemaService */
    protected PKPSchemaService $schemaService;

    // Funders with awards that can be validated via the Zenodo API, and their corresponding ROR IDs.
    const AWARD_FUNDERS = [
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
        $funderGrantValidationSetting = (bool) $context->getData('funderGrantValidation');

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

                            $awardResponse = $httpClient->request(
                                'GET',
                                "https://zenodo.org/api/awards?funders={$ror}&q=" . urlencode($grantNumber)
                            );

                            $body = json_decode($awardResponse->getBody(), true);

                            $success = array_reduce(
                                $body['hits']['hits'] ?? [],
                                fn($carry, $item) => $carry || $item['number'] == $grantNumber,
                                false
                            );

                            if (!$success) {
                                $validator->errors()->add("grants.{$key}.grantNumber", __('submission.funders.grantNumberInvalid'));
                            }
                        }
                    });
                }
            }
        }

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
}

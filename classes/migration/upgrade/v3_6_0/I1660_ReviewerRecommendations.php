<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I1660_ReviewerRecommendations.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I1660_ReviewerRecommendations
 *
 * @brief 
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Support\Facades\DB;
use PKP\install\Installer;

use Throwable;

use stdClass;

use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\facades\Locale;
use APP\migration\install\ReviewerRecommendationsMigration;

abstract class I1660_ReviewerRecommendations extends \PKP\migration\Migration
{
    abstract protected function systemDefineNonRemovableRecommendations(): array;

    protected ReviewerRecommendationsMigration $recommendationInstallMigration;

    /**
     * Constructor
     */
    public function __construct(Installer $installer, array $attributes)
    {
        $this->recommendationInstallMigration = new ReviewerRecommendationsMigration(
            $installer,
            $attributes
        );

        parent::__construct($installer, $attributes);
    }
    
    /**
     * Run the migration.
     */
    public function up(): void
    {
        $this->recommendationInstallMigration->up();
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $this->recommendationInstallMigration->down();
    }

    // TODO : Optimize the process if possible 
    protected function seedNonRemovableRecommendations(): void
    {
        $nonRemovablerecommendations = $this->systemDefineNonRemovableRecommendations();

        if (empty($nonRemovablerecommendations)) {
            return;
        }

        $currentLocale = Locale::getLocale();
        $contextSupportedLocales = DB::table($this->recommendationInstallMigration->contextTable())
            ->select($this->recommendationInstallMigration->contextPrimaryKey())
            ->addSelect([
                "supportedLocales" => DB::table($this->recommendationInstallMigration->settingTable())
                    ->select("setting_value")
                    ->whereColumn(
                        $this->recommendationInstallMigration->contextPrimaryKey(),
                        "{$this->recommendationInstallMigration->contextTable()}.{$this->recommendationInstallMigration->contextPrimaryKey()}"
                    )
                    ->where("setting_name", "supportedLocales")
                    ->limit(1)
            ])
            ->get()
            ->pluck("supportedLocales", $this->recommendationInstallMigration->contextPrimaryKey())
            ->filter()
            ->map(fn($locales) => json_decode($locales));

        try {

            $recommendations = [];

            foreach ($nonRemovablerecommendations as $recommendationValue => $translatableKey) {
                $recommendations[$recommendationValue] = [
                    'contextId' => null,
                    'value' => $recommendationValue,
                    'removable' => 0,
                    'status' => 1,
                    'title' => [],
                ];
            }
            
            $allContextSupportLocales = $contextSupportedLocales
                ->values()
                ->flatten()
                ->unique()
                ->toArray();

            ReviewerRecommendation::unguard();

            DB::beginTransaction();

            foreach ($allContextSupportLocales as $locale) {
                
                Locale::setLocale($locale);

                foreach ($nonRemovablerecommendations as $recommendationValue => $translatableKey) {
                    $recommendations[$recommendationValue]['title'][$locale] = __($translatableKey);
                }
            }
            
            Locale::setLocale($currentLocale);

            $contextSupportedLocales->each(
                fn (array $supportedLocales, int $contextId) => collect($recommendations)->each(
                    fn (array $recommendation) => 
                        ReviewerRecommendation::create(
                            array_merge($recommendation, [
                                'contextId' => $contextId,
                                'title' => array_intersect_key(
                                    $recommendation['title'],
                                    array_flip($supportedLocales)
                                )
                            ])
                        )
                )
            );

            DB::commit();

            ReviewerRecommendation::reguard();

        } catch (Throwable $exception) {

            DB::rollBack();
            Locale::setLocale($currentLocale);
            ReviewerRecommendation::reguard();

            ray($exception);
            throw $exception;
        }
    }
}

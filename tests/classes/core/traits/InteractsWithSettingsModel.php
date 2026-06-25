<?php

/**
 * @file tests/classes/core/traits/InteractsWithSettingsModel.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InteractsWithSettingsModel
 *
 * @see \PKP\core\traits\ModelWithSettings
 * @see \PKP\core\SettingsBuilder
 *
 * @brief Shared test build-up for ModelWithSettings / SettingsBuilder test cases.
 */

namespace PKP\tests\classes\core\traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\core\traits\ModelWithSettings;
use PKP\plugins\Hook;

trait InteractsWithSettingsModel
{
    /**
     * Create the schema-based and pure-Eloquent temp tables. Call from setUpBeforeClass().
     */
    protected static function createSettingsModelTables(): void
    {
        // Schema-based test tables
        Schema::create('test_settings_schema_entity', function (Blueprint $table) {
            $table->bigInteger('test_id')->autoIncrement();
            $table->bigInteger('parent_id')->nullable();
        });
        Schema::create('test_settings_schema_entity_settings', function (Blueprint $table) {
            $table->bigIncrements('test_settings_schema_entity_setting_id');
            $table->bigInteger('test_id');
            // No ON DELETE CASCADE here so the delete tests genuinely exercise
            // SettingsBuilder's own settings-table cleanup rather than relying
            // on the database to do it.
            $table->foreign('test_id')->references('test_id')->on('test_settings_schema_entity');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->index(['test_id'], 'test_settings_schema_entity_settings_test_id');
            $table->unique(['test_id', 'locale', 'setting_name'], 'test_settings_schema_entity_settings_unique');
        });

        // Pure-Eloquent test tables
        Schema::create('test_settings_pure_entity', function (Blueprint $table) {
            $table->bigInteger('test_id')->autoIncrement();
            $table->bigInteger('parent_id')->nullable();
        });
        Schema::create('test_settings_pure_entity_settings', function (Blueprint $table) {
            $table->bigIncrements('test_settings_pure_entity_setting_id');
            $table->bigInteger('test_id');
            // No ON DELETE CASCADE — see comment above on the schema settings table.
            $table->foreign('test_id')->references('test_id')->on('test_settings_pure_entity');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->index(['test_id'], 'test_settings_pure_entity_settings_test_id');
            $table->unique(['test_id', 'locale', 'setting_name'], 'test_settings_pure_entity_settings_unique');
        });
    }

    /**
     * Register the JSON schema for the schema-based fixture model. Call from setUpBeforeClass().
     *
     * The props are a UNION superset spanning both test classes:
     *  - `parentId`      : primary column (cast must stay snake_case → parent_id)
     *  - `title`         : single-word multilingual baseline
     *  - `subtitle`      : second multilingual prop (multi-prop insert/update cases)
     *  - `coverImage`    : multi-word multilingual (cast key stays snake_case so the
     *                      inbound MultilingualSettingAttribute set-cast keeps firing)
     *  - `nonlocSetting` : non-multilingual string setting
     *  - `jsonSetting`   : non-multilingual array setting ([] is a value, not a clear)
     *  - `authors`       : single-word non-multilingual array (regression guard)
     *  - `authorsList`   : multi-word camelCase array (the #12712 bug trigger)
     *  - `recordCount`   : multi-word camelCase integer (scalar cast)
     *  - `dataReadme`    : multi-word camelCase object cast
     *  - `isActive`      : multi-word camelCase boolean cast
     *  - `secretToken`   : multi-word camelCase encrypted string (encrypt:true → 'encrypted')
     *  - `secretList`    : encrypt:true array → 'encrypted:array' branch of convertSchemaToCasts()
     *  - `secretObject`  : encrypt:true object → 'encrypted:object' branch
     * The `origin` field is required by PKPSchemaService::groupPropsByOrigin().
     */
    protected static function registerSettingsModelSchema(): void
    {
        Hook::add('Schema::get::test_settings_schema', function ($hookName, $args) {
            $schema = & $args[0];
            $schema = json_decode('{
                "title": "Test Settings Schema",
                "description": "Shared schema for ModelWithSettings / SettingsBuilder tests",
                "properties": {
                    "id": {
                        "type": "integer",
                        "origin": "primary",
                        "readOnly": true
                    },
                    "parentId": {
                        "type": "integer",
                        "origin": "primary"
                    },
                    "title": {
                        "type": "string",
                        "origin": "setting",
                        "multilingual": true
                    },
                    "subtitle": {
                        "type": "string",
                        "origin": "setting",
                        "multilingual": true
                    },
                    "coverImage": {
                        "type": "string",
                        "origin": "setting",
                        "multilingual": true
                    },
                    "nonlocSetting": {
                        "type": "string",
                        "origin": "setting"
                    },
                    "jsonSetting": {
                        "type": "array",
                        "origin": "setting",
                        "items": { "type": "string" }
                    },
                    "authors": {
                        "type": "array",
                        "origin": "setting",
                        "items": { "type": "string" }
                    },
                    "authorsList": {
                        "type": "array",
                        "origin": "setting",
                        "items": { "type": "string" }
                    },
                    "recordCount": {
                        "type": "integer",
                        "origin": "setting"
                    },
                    "dataReadme": {
                        "type": "object",
                        "origin": "setting"
                    },
                    "isActive": {
                        "type": "boolean",
                        "origin": "setting"
                    },
                    "secretToken": {
                        "type": "string",
                        "encrypt": true,
                        "origin": "setting"
                    },
                    "secretList": {
                        "type": "array",
                        "encrypt": true,
                        "origin": "setting",
                        "items": { "type": "string" }
                    },
                    "secretObject": {
                        "type": "object",
                        "encrypt": true,
                        "origin": "setting"
                    }
                }
            }');
            return true;
        });
    }

    /**
     * Drop all four temp tables. Call from tearDownAfterClass().
     */
    protected static function dropSettingsModelTables(): void
    {
        Schema::dropIfExists('test_settings_schema_entity_settings');
        Schema::dropIfExists('test_settings_schema_entity');
        Schema::dropIfExists('test_settings_pure_entity_settings');
        Schema::dropIfExists('test_settings_pure_entity');
    }

    /**
     * Clear all rows between tests. Call from setUp().
     */
    protected function truncateSettingsModelTables(): void
    {
        DB::table('test_settings_schema_entity_settings')->delete();
        DB::table('test_settings_schema_entity')->delete();
        DB::table('test_settings_pure_entity_settings')->delete();
        DB::table('test_settings_pure_entity')->delete();
    }

    //
    // Seeding / assertion helpers
    //

    /**
     * Seed a schema-based model with a multilingual `title` (one row per locale).
     */
    protected function seedSchemaModel(array $titleByLocale): int
    {
        $modelId = DB::table('test_settings_schema_entity')->insertGetId(['parent_id' => 1], 'test_id');
        foreach ($titleByLocale as $locale => $value) {
            DB::table('test_settings_schema_entity_settings')->insert([
                'test_id' => $modelId,
                'locale' => $locale,
                'setting_name' => 'title',
                'setting_value' => $value,
            ]);
        }
        return $modelId;
    }

    /**
     * Seed a pure-Eloquent model with a multilingual `familyName` (one row per locale).
     */
    protected function seedPureModel(array $familyNameByLocale): int
    {
        $modelId = DB::table('test_settings_pure_entity')->insertGetId(['parent_id' => 1], 'test_id');
        foreach ($familyNameByLocale as $locale => $value) {
            DB::table('test_settings_pure_entity_settings')->insert([
                'test_id' => $modelId,
                'locale' => $locale,
                'setting_name' => 'familyName',
                'setting_value' => $value,
            ]);
        }
        return $modelId;
    }

    /**
     * Seed a fresh schema-based model with a single setting row, returning its id.
     */
    protected function seedSchemaSetting(string $settingName, ?string $settingValue, string $locale = ''): int
    {
        $modelId = DB::table('test_settings_schema_entity')->insertGetId(['parent_id' => 1], 'test_id');
        DB::table('test_settings_schema_entity_settings')->insert([
            'test_id' => $modelId,
            'locale' => $locale,
            'setting_name' => $settingName,
            'setting_value' => $settingValue,
        ]);
        return $modelId;
    }

    /**
     * Seed a fresh pure-Eloquent model with a single setting row, returning its id.
     */
    protected function seedPureSetting(string $settingName, ?string $settingValue, string $locale = ''): int
    {
        $modelId = DB::table('test_settings_pure_entity')->insertGetId(['parent_id' => 1], 'test_id');
        DB::table('test_settings_pure_entity_settings')->insert([
            'test_id' => $modelId,
            'locale' => $locale,
            'setting_name' => $settingName,
            'setting_value' => $settingValue,
        ]);
        return $modelId;
    }

    protected function assertSettingRowCount(string $table, int $modelId, string $settingName, int $expected): void
    {
        $count = DB::table($table)
            ->where('test_id', $modelId)
            ->where('setting_name', $settingName)
            ->count();
        $this->assertSame($expected, $count, "Expected {$expected} rows for {$settingName} on id {$modelId}, got {$count}");
    }

    /**
     * @return \stdClass[]
     */
    protected function getSettingRows(string $table, int $modelId, string $settingName): array
    {
        return DB::table($table)
            ->where('test_id', $modelId)
            ->where('setting_name', $settingName)
            ->orderBy('locale')
            ->get()
            ->all();
    }
}

/**
 * Schema-based test model (getSchemaName() returns a real schema name).
 */
class TestSettingsSchemaModel extends Model
{
    use ModelWithSettings;

    protected $table = 'test_settings_schema_entity';
    protected $primaryKey = 'test_id';
    public $timestamps = false;
    protected $guarded = [];

    public static function getSchemaName(): ?string
    {
        return 'test_settings_schema';
    }

    public function getSettingsTable(): string
    {
        return 'test_settings_schema_entity_settings';
    }
}

/**
 * Non-schema-based test model (getSchemaName() returns null). Mirrors the shape of
 * ReviewerSuggestion: hardcoded getSettings()/getMultilingualProps() and casts()
 * declared in snake_case. getSettings()/casts() are a UNION superset across both
 * test classes, including a multi-word non-multilingual setting (affiliateRoles) to
 * exercise the #12712 snake-registered → camelCase re-key path.
 */
class TestSettingsPureModel extends Model
{
    use ModelWithSettings;

    protected $table = 'test_settings_pure_entity';
    protected $primaryKey = 'test_id';
    public $timestamps = false;
    protected $guarded = [];

    public static function getSchemaName(): ?string
    {
        return null;
    }

    public function getSettingsTable(): string
    {
        return 'test_settings_pure_entity_settings';
    }

    public function getSettings(): array
    {
        return [
            'familyName',
            'metadata',
            'affiliateRoles',
            'recordCount',
            'dataReadme',
            'isActive',
            'ratioValue',
            'profileTags',
            'secretToken',
            'eventAt',
            'dueDate',
            'releaseDate',
            'plainNote',
            'displayName'
        ];
    }

    public function getMultilingualProps(): array
    {
        return [
            'familyName',
            'displayName'
        ];
    }

    protected function casts(): array
    {
        return [
            // Primary column cast (snake_case DB column). Lets the read tests assert
            // that the #12712 getCasts() override leaves primary casts snake-keyed.
            'parent_id' => 'integer',
            'family_name' => 'string',
            // Non-multilingual array setting — mirrors a real-world prop like
            // Funder `grants` (pkp/pkp-lib#12658). Used to assert that `[]` on a
            // non-multilingual setting is written as a value, not a clear signal.
            'metadata' => 'array',
            // Multi-word non-multilingual settings registered snake_case (the real
            // convention): the #12712 fix re-keys these to camelCase on read.
            'affiliate_roles' => 'array',
            'record_count' => 'integer',
            'data_readme' => 'object',
            'is_active' => 'boolean',
            'ratio_value' => 'float',
            // Deliberately keyed camelCase (not snake) to prove getCasts() also
            // handles a pure model whose cast key is already camelCase — the
            // Str::camel() idempotent branch of the #12712 override.
            'profileTags' => 'array',
            // Laravel's built-in encrypted cast declared snake_case: exercises the
            // #12712 snake→camel re-key on an encrypted (set+get) cast.
            'secret_token' => 'encrypted',
            // Carbon cast family (datetime/date), incl. the parametric datetime:Y-m-d
            // form (a colon-bearing cast value the override must pass through). Mirrors
            // real usage (EditorialTask.dateDue, Announcement.dateExpire).
            'event_at' => 'datetime',
            'due_date' => 'datetime:Y-m-d',
            'release_date' => 'date',
            // Plain string cast (identity) on a non-multilingual setting.
            'plain_note' => 'string',
        ];
    }
}

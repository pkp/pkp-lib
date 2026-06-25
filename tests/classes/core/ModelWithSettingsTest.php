<?php

/**
 * @file tests/classes/core/ModelWithSettingsTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ModelWithSettingsTest
 *
 * @see \PKP\core\traits\ModelWithSettings
 *
 * @brief Unit tests for the \PKP\core\traits\ModelWithSettings trait.
 */

namespace PKP\tests\classes\core;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversTrait;
use PKP\core\SettingsBuilder;
use PKP\core\traits\ModelWithSettings;
use PKP\tests\classes\core\traits\InteractsWithSettingsModel;
use PKP\tests\classes\core\traits\TestSettingsPureModel;
use PKP\tests\classes\core\traits\TestSettingsSchemaModel;
use PKP\tests\PKPTestCase;
use Throwable;

#[CoversTrait(ModelWithSettings::class)]
class ModelWithSettingsTest extends PKPTestCase
{
    use InteractsWithSettingsModel;

    private const SCHEMA_TABLE = 'test_settings_schema_entity';
    private const SCHEMA_SETTINGS_TABLE = 'test_settings_schema_entity_settings';
    private const PURE_TABLE = 'test_settings_pure_entity';
    private const PURE_SETTINGS_TABLE = 'test_settings_pure_entity_settings';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::createSettingsModelTables();
        static::registerSettingsModelSchema();
    }

    public static function tearDownAfterClass(): void
    {
        static::dropSettingsModelTables();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->truncateSettingsModelTables();
    }

    // =====================================================================
    // Schema-based model
    // =====================================================================

    //
    // getCasts() key mapping (the fix, asserted directly)
    //

    public function testSchemaGetCastsMapsNonMultilingualSettingsToCamelCase(): void
    {
        $casts = (new TestSettingsSchemaModel())->getCasts();

        // Non-multilingual setting props: keyed camelCase (matches hydration key),
        // across every type/casing the schema declares.
        $this->assertSame('array', $casts['authorsList'] ?? null);
        $this->assertArrayNotHasKey('authors_list', $casts);
        $this->assertSame('integer', $casts['recordCount'] ?? null);
        $this->assertArrayNotHasKey('record_count', $casts);
        $this->assertSame('object', $casts['dataReadme'] ?? null);
        $this->assertArrayNotHasKey('data_readme', $casts);
        $this->assertSame('boolean', $casts['isActive'] ?? null);
        $this->assertArrayNotHasKey('is_active', $casts);
        $this->assertSame('encrypted', $casts['secretToken'] ?? null);
        $this->assertArrayNotHasKey('secret_token', $casts);
        // Typed-encrypted branches of convertSchemaToCasts() (colon-bearing values).
        $this->assertSame('encrypted:array', $casts['secretList'] ?? null);
        $this->assertSame('encrypted:object', $casts['secretObject'] ?? null);
        // Plain string cast on a non-multilingual setting.
        $this->assertSame('string', $casts['nonlocSetting'] ?? null);
        $this->assertArrayNotHasKey('nonloc_setting', $casts);

        // Single-word setting: unchanged (snake === camel).
        $this->assertArrayHasKey('authors', $casts);

        // Primary column: stays snake_case to match its DB column.
        $this->assertArrayHasKey('parent_id', $casts);
        $this->assertArrayNotHasKey('parentId', $casts);

        // Multilingual props: stay snake_case so the inbound MultilingualSettingAttribute
        // set-cast keeps firing on the snake-cased set path. (title/subtitle are
        // single-word so snake === camel; coverImage is the multi-word proof.)
        $this->assertArrayHasKey('cover_image', $casts);
        $this->assertArrayNotHasKey('coverImage', $casts);
        $this->assertArrayHasKey('title', $casts);
    }

    //
    // Read-side casting on hydrated models
    //

    public function testSchemaMultiWordCamelCaseArraySettingIsCastOnRead(): void
    {
        $value = ['Ada Lovelace', 'Alan Turing'];
        $modelId = $this->seedSchemaSetting('authorsList', json_encode($value));

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertIsArray($model->authorsList, 'authorsList should be cast from JSON to an array');
        $this->assertSame($value, $model->authorsList);
    }

    public function testSchemaMultiWordCamelCaseIntegerSettingIsCastOnRead(): void
    {
        $modelId = $this->seedSchemaSetting('recordCount', '42');

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertIsInt($model->recordCount);
        $this->assertSame(42, $model->recordCount);
    }

    public function testSchemaMultiWordCamelCaseObjectSettingIsCastOnRead(): void
    {
        $modelId = $this->seedSchemaSetting('dataReadme', json_encode(['license' => 'CC-BY', 'version' => 2]));

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertInstanceOf(\stdClass::class, $model->dataReadme);
        $this->assertSame('CC-BY', $model->dataReadme->license);
        $this->assertSame(2, $model->dataReadme->version);
    }

    public function testSchemaMultiWordCamelCaseBooleanSettingIsCastOnRead(): void
    {
        $modelId = $this->seedSchemaSetting('isActive', '1');

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertIsBool($model->isActive);
        $this->assertTrue($model->isActive);
    }

    public function testSchemaSingleWordSettingStillCastOnRead(): void
    {
        // Regression guard: single-word names worked before the fix and must keep working.
        $value = ['Grace Hopper'];
        $modelId = $this->seedSchemaSetting('authors', json_encode($value));

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertIsArray($model->authors);
        $this->assertSame($value, $model->authors);
    }

    public function testSchemaPrimaryColumnCastStillResolvesOnRead(): void
    {
        // Guards against the getCasts() override breaking primary-table casts.
        $modelId = DB::table(self::SCHEMA_TABLE)->insertGetId(['parent_id' => 7], 'test_id');

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertIsInt($model->parentId);
        $this->assertSame(7, $model->parentId);
    }

    public function testSchemaSingleWordMultilingualPropResolvesOnRead(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'Title EN', 'fr_CA' => 'Titre FR']);

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertSame('Title EN', $model->getLocalizedData('title', 'en', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
        $this->assertSame('Titre FR', $model->getLocalizedData('title', 'fr_CA', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
    }

    public function testSchemaMultiWordMultilingualPropResolvesOnRead(): void
    {
        // coverImage is multi-word multilingual: its cast key stays snake_case, but
        // it is hydrated as a per-locale array and read back directly. This guards
        // that the override did not regress multilingual reads.
        $modelId = $this->seedSchemaMultilingual('coverImage', ['en' => 'Cover EN', 'fr_CA' => 'Couverture FR'], self::SCHEMA_SETTINGS_TABLE, self::SCHEMA_TABLE);

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertSame('Cover EN', $model->getLocalizedData('coverImage', 'en', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
        $this->assertSame('Couverture FR', $model->getLocalizedData('coverImage', 'fr_CA', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
    }

    public function testSchemaAbsentSettingReadsAsNull(): void
    {
        // A cast setting with no row must read back as null, not raise a cast error.
        $modelId = DB::table(self::SCHEMA_TABLE)->insertGetId(['parent_id' => 1], 'test_id');

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertNull($model->recordCount);
        $this->assertNull($model->authorsList);
    }

    //
    // Write/store: value is cast-encoded and stored under the camelCase setting_name
    //

    public function testSchemaWriteStoresMultiWordSettingAsCamelCaseJson(): void
    {
        $value = ['Marie Curie', 'Rosalind Franklin'];
        $model = TestSettingsSchemaModel::create(['parentId' => 3, 'authorsList' => $value]);

        // Stored under camelCase setting_name, with the cast-encoded JSON value.
        $this->assertStoredAsCamelCase(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'authorsList', 'authors_list', json_encode($value));

        // And it round-trips back to an array on read.
        $reloaded = TestSettingsSchemaModel::find($model->getKey());
        $this->assertIsArray($reloaded->authorsList);
        $this->assertSame($value, $reloaded->authorsList);
    }

    public function testSchemaWriteStoresMultilingualMultiWordAsCamelCase(): void
    {
        // Guards that the inbound MultilingualSettingAttribute set-cast still fires
        // for a multi-word multilingual prop and stores per-locale rows camelCase.
        $model = TestSettingsSchemaModel::create([
            'parentId' => 4,
            'coverImage' => ['en' => 'Written EN', 'fr_CA' => 'Écrit FR'],
        ]);

        $rows = $this->getSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'coverImage');
        $this->assertCount(2, $rows);
        $this->assertSame(0, $this->countSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'cover_image'));

        $reloaded = TestSettingsSchemaModel::find($model->getKey());
        $this->assertSame('Written EN', $reloaded->getLocalizedData('coverImage', 'en', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
        $this->assertSame('Écrit FR', $reloaded->getLocalizedData('coverImage', 'fr_CA', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
    }

    public function testSchemaWriteWithSnakeCaseKeyStoresCamelCase(): void
    {
        // A caller may set a snake_case key directly (bypassing camelCase mass-assignment
        // fillability): setAttribute() must Str::camel() it so the cast fires and the row
        // persists under the camelCase setting_name.
        $value = ['Snake', 'Case'];
        $model = new TestSettingsSchemaModel();
        $model->parentId = 5;
        $model->authors_list = $value;
        $model->save();

        $this->assertStoredAsCamelCase(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'authorsList', 'authors_list', json_encode($value));

        $reloaded = TestSettingsSchemaModel::find($model->getKey());
        $this->assertSame($value, $reloaded->authorsList);
    }

    public function testSchemaEncryptedSettingRoundTrips(): void
    {
        if (!$this->encryptionAvailable()) {
            $this->markTestSkipped('No application encryption key configured (config(app.key)).');
        }

        $model = TestSettingsSchemaModel::create(['parentId' => 6, 'secretToken' => 'plain-secret']);

        // Stored encrypted (not the plaintext) under the camelCase setting_name.
        $rows = $this->getSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'secretToken');
        $this->assertCount(1, $rows);
        $this->assertNotSame('plain-secret', $rows[0]->setting_value, 'value must be stored encrypted, not as plaintext');
        $this->assertSame(0, $this->countSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'secret_token'));

        // Decrypts on read.
        $reloaded = TestSettingsSchemaModel::find($model->getKey());
        $this->assertSame('plain-secret', $reloaded->secretToken);
    }

    public function testSchemaStringSettingIsCastOnRead(): void
    {
        $modelId = $this->seedSchemaSetting('nonlocSetting', 'hello');

        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertIsString($model->nonlocSetting);
        $this->assertSame('hello', $model->nonlocSetting);
    }

    public function testSchemaEncryptedArraySettingRoundTrips(): void
    {
        // encrypt:true on an array prop → 'encrypted:array' (a colon-bearing cast the
        // override passes through verbatim while re-keying to camelCase).
        if (!$this->encryptionAvailable()) {
            $this->markTestSkipped('No application encryption key configured (config(app.key)).');
        }

        $value = ['a', 'b'];
        $model = TestSettingsSchemaModel::create(['parentId' => 6, 'secretList' => $value]);

        $rows = $this->getSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'secretList');
        $this->assertCount(1, $rows);
        $this->assertNotSame(json_encode($value), $rows[0]->setting_value, 'value must be stored encrypted, not as plaintext JSON');
        $this->assertSame(0, $this->countSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'secret_list'));

        $reloaded = TestSettingsSchemaModel::find($model->getKey());
        $this->assertIsArray($reloaded->secretList);
        $this->assertSame($value, $reloaded->secretList);
    }

    public function testSchemaEncryptedObjectSettingRoundTrips(): void
    {
        if (!$this->encryptionAvailable()) {
            $this->markTestSkipped('No application encryption key configured (config(app.key)).');
        }

        $model = TestSettingsSchemaModel::create(['parentId' => 6, 'secretObject' => ['k' => 'v', 'n' => 1]]);

        $rows = $this->getSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'secretObject');
        $this->assertCount(1, $rows);
        $this->assertSame(0, $this->countSettingRows(self::SCHEMA_SETTINGS_TABLE, $model->getKey(), 'secret_object'));

        $reloaded = TestSettingsSchemaModel::find($model->getKey());
        $this->assertInstanceOf(\stdClass::class, $reloaded->secretObject);
        $this->assertSame('v', $reloaded->secretObject->k);
        $this->assertSame(1, $reloaded->secretObject->n);
    }

    // =====================================================================
    // Pure-Eloquent model (casts() declared snake_case)
    // =====================================================================

    //
    // getCasts() key mapping
    //

    public function testPureGetCastsMapsSnakeRegisteredSettingsToCamelCase(): void
    {
        $casts = (new TestSettingsPureModel())->getCasts();

        // Casts are declared snake_case in casts(), but each non-multilingual setting
        // is hydrated/read under its camelCase name — the override re-keys them.
        $this->assertSame('array', $casts['affiliateRoles'] ?? null);
        $this->assertArrayNotHasKey('affiliate_roles', $casts);
        $this->assertSame('integer', $casts['recordCount'] ?? null);
        $this->assertArrayNotHasKey('record_count', $casts);
        $this->assertSame('object', $casts['dataReadme'] ?? null);
        $this->assertArrayNotHasKey('data_readme', $casts);
        $this->assertSame('boolean', $casts['isActive'] ?? null);
        $this->assertArrayNotHasKey('is_active', $casts);
        $this->assertSame('float', $casts['ratioValue'] ?? null);
        $this->assertArrayNotHasKey('ratio_value', $casts);
        $this->assertSame('encrypted', $casts['secretToken'] ?? null);
        $this->assertArrayNotHasKey('secret_token', $casts);
        // Carbon cast family, incl. the parametric datetime:Y-m-d (colon-bearing value).
        $this->assertSame('datetime', $casts['eventAt'] ?? null);
        $this->assertArrayNotHasKey('event_at', $casts);
        $this->assertSame('datetime:Y-m-d', $casts['dueDate'] ?? null);
        $this->assertArrayNotHasKey('due_date', $casts);
        $this->assertSame('date', $casts['releaseDate'] ?? null);
        $this->assertArrayNotHasKey('release_date', $casts);
        // Plain string cast.
        $this->assertSame('string', $casts['plainNote'] ?? null);
        $this->assertArrayNotHasKey('plain_note', $casts);

        // Single-word setting: unchanged (snake === camel).
        $this->assertArrayHasKey('metadata', $casts);

        // Primary column stays snake_case to match its DB column.
        $this->assertArrayHasKey('parent_id', $casts);
        $this->assertArrayNotHasKey('parentId', $casts);

        // Multilingual props stay snake_case.
        $this->assertArrayHasKey('family_name', $casts);
        $this->assertArrayHasKey('display_name', $casts);
        $this->assertArrayNotHasKey('displayName', $casts);
    }

    public function testPureCamelCaseDeclaredCastStaysCamelCaseInGetCasts(): void
    {
        // A pure model may key casts() in camelCase rather than snake_case. The
        // override's Str::camel() normalisation is idempotent on such keys, so the
        // cast stays camelCase and still matches the camelCase hydration key.
        $casts = (new TestSettingsPureModel())->getCasts();

        $this->assertSame('array', $casts['profileTags'] ?? null);
        $this->assertArrayNotHasKey('profile_tags', $casts);
    }

    //
    // Read-side casting on hydrated models
    //

    public function testPureMultiWordCamelCaseArraySettingIsCastOnRead(): void
    {
        // cast is declared snake_case (affiliate_roles), setting is hydrated camelCase (affiliateRoles).
        $value = ['reviewer', 'author'];
        $modelId = $this->seedPureSetting('affiliateRoles', json_encode($value));

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsArray($model->affiliateRoles);
        $this->assertSame($value, $model->affiliateRoles);
    }

    public function testPureMultiWordCamelCaseIntegerSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('recordCount', '99');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsInt($model->recordCount);
        $this->assertSame(99, $model->recordCount);
    }

    public function testPureMultiWordCamelCaseObjectSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('dataReadme', json_encode(['license' => 'MIT', 'version' => 3]));

        $model = TestSettingsPureModel::find($modelId);

        $this->assertInstanceOf(\stdClass::class, $model->dataReadme);
        $this->assertSame('MIT', $model->dataReadme->license);
        $this->assertSame(3, $model->dataReadme->version);
    }

    public function testPureMultiWordCamelCaseBooleanSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('isActive', '1');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsBool($model->isActive);
        $this->assertTrue($model->isActive);
    }

    public function testPureMultiWordCamelCaseFloatSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('ratioValue', '3.14');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsFloat($model->ratioValue);
        $this->assertSame(3.14, $model->ratioValue);
    }

    public function testPureCamelCaseDeclaredCastAppliesOnRead(): void
    {
        // Counterpart to the snake-declared case: cast is declared camelCase
        // (profileTags) and the setting is hydrated camelCase (profileTags) too.
        $value = ['php', 'eloquent'];
        $modelId = $this->seedPureSetting('profileTags', json_encode($value));

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsArray($model->profileTags);
        $this->assertSame($value, $model->profileTags);
    }

    public function testPureSingleWordSettingIsCastOnRead(): void
    {
        $value = ['existing' => 'data'];
        $modelId = $this->seedPureSetting('metadata', json_encode($value));

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsArray($model->metadata);
        $this->assertSame($value, $model->metadata);
    }

    public function testPurePrimaryColumnCastResolvesOnRead(): void
    {
        $modelId = DB::table(self::PURE_TABLE)->insertGetId(['parent_id' => 7], 'test_id');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsInt($model->parentId);
        $this->assertSame(7, $model->parentId);
    }

    public function testPureSingleWordMultilingualPropResolvesOnRead(): void
    {
        $modelId = $this->seedPureModel(['en' => 'Family EN', 'fr_CA' => 'Famille FR']);

        $model = TestSettingsPureModel::find($modelId);

        $this->assertSame('Family EN', $model->getLocalizedData('familyName', 'en', TestSettingsPureModel::LOCALE_MATCH_STRICT));
        $this->assertSame('Famille FR', $model->getLocalizedData('familyName', 'fr_CA', TestSettingsPureModel::LOCALE_MATCH_STRICT));
    }

    public function testPureMultiWordMultilingualPropResolvesOnRead(): void
    {
        $modelId = $this->seedPureMultilingual('displayName', ['en' => 'Display EN', 'fr_CA' => 'Affichage FR'], self::PURE_SETTINGS_TABLE, self::PURE_TABLE);

        $model = TestSettingsPureModel::find($modelId);

        $this->assertSame('Display EN', $model->getLocalizedData('displayName', 'en', TestSettingsPureModel::LOCALE_MATCH_STRICT));
        $this->assertSame('Affichage FR', $model->getLocalizedData('displayName', 'fr_CA', TestSettingsPureModel::LOCALE_MATCH_STRICT));
    }

    public function testPureAbsentSettingReadsAsNull(): void
    {
        $modelId = DB::table(self::PURE_TABLE)->insertGetId(['parent_id' => 1], 'test_id');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertNull($model->recordCount);
        $this->assertNull($model->affiliateRoles);
    }

    //
    // Write/store
    //

    public function testPureWriteStoresMultiWordSettingAsCamelCaseJson(): void
    {
        $value = ['editor', 'reader'];
        $model = TestSettingsPureModel::create(['parent_id' => 3, 'affiliateRoles' => $value]);

        $this->assertStoredAsCamelCase(self::PURE_SETTINGS_TABLE, $model->getKey(), 'affiliateRoles', 'affiliate_roles', json_encode($value));

        $reloaded = TestSettingsPureModel::find($model->getKey());
        $this->assertIsArray($reloaded->affiliateRoles);
        $this->assertSame($value, $reloaded->affiliateRoles);
    }

    public function testPureWriteStoresMultilingualMultiWordAsCamelCase(): void
    {
        $model = TestSettingsPureModel::create([
            'parent_id' => 4,
            'displayName' => ['en' => 'Display EN', 'fr_CA' => 'Affichage FR'],
        ]);

        $rows = $this->getSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'displayName');
        $this->assertCount(2, $rows);
        $this->assertSame(0, $this->countSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'display_name'));

        $reloaded = TestSettingsPureModel::find($model->getKey());
        $this->assertSame('Display EN', $reloaded->getLocalizedData('displayName', 'en', TestSettingsPureModel::LOCALE_MATCH_STRICT));
        $this->assertSame('Affichage FR', $reloaded->getLocalizedData('displayName', 'fr_CA', TestSettingsPureModel::LOCALE_MATCH_STRICT));
    }

    public function testPureWriteWithSnakeCaseKeyStoresCamelCase(): void
    {
        $value = ['snake', 'input'];
        $model = TestSettingsPureModel::create(['parent_id' => 5, 'affiliate_roles' => $value]);

        $this->assertStoredAsCamelCase(self::PURE_SETTINGS_TABLE, $model->getKey(), 'affiliateRoles', 'affiliate_roles', json_encode($value));

        $reloaded = TestSettingsPureModel::find($model->getKey());
        $this->assertSame($value, $reloaded->affiliateRoles);
    }

    public function testPureEncryptedSettingRoundTrips(): void
    {
        // Laravel's built-in encrypted cast on a pure model, declared snake_case
        if (!$this->encryptionAvailable()) {
            $this->markTestSkipped('No application encryption key configured (config(app.key)).');
        }

        $model = TestSettingsPureModel::create(['parent_id' => 6, 'secretToken' => 'plain-secret']);

        // Stored encrypted (not plaintext, non-deterministic) under the camelCase setting_name.
        $rows = $this->getSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'secretToken');
        $this->assertCount(1, $rows);
        $this->assertNotSame('plain-secret', $rows[0]->setting_value, 'value must be stored encrypted, not as plaintext');
        $this->assertSame(0, $this->countSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'secret_token'));

        // Decrypts on read.
        $reloaded = TestSettingsPureModel::find($model->getKey());
        $this->assertSame('plain-secret', $reloaded->secretToken);
    }

    public function testPureDatetimeSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('eventAt', '2024-01-15 10:30:00');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertInstanceOf(Carbon::class, $model->eventAt);
        $this->assertSame('2024-01-15 10:30:00', $model->eventAt->format('Y-m-d H:i:s'));
    }

    public function testPureParametricDatetimeSettingIsCastOnRead(): void
    {
        // due_date is declared 'datetime:Y-m-d' — a colon-bearing cast value the
        // override must pass through verbatim while re-keying the key to camelCase.
        $modelId = $this->seedPureSetting('dueDate', '2024-03-20');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertInstanceOf(Carbon::class, $model->dueDate);
        $this->assertSame('2024-03-20', $model->dueDate->format('Y-m-d'));
    }

    public function testPureDateSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('releaseDate', '2024-06-01');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertInstanceOf(Carbon::class, $model->releaseDate);
        $this->assertSame('2024-06-01', $model->releaseDate->format('Y-m-d'));
    }

    public function testPureStringSettingIsCastOnRead(): void
    {
        $modelId = $this->seedPureSetting('plainNote', 'note');

        $model = TestSettingsPureModel::find($modelId);

        $this->assertIsString($model->plainNote);
        $this->assertSame('note', $model->plainNote);
    }

    public function testPureDatetimeSettingRoundTrips(): void
    {
        // The datetime cast normalises the stored format, so assert placement +
        // camelCase setting_name rather than the exact raw value.
        $model = TestSettingsPureModel::create(['parent_id' => 7, 'eventAt' => '2024-01-15 10:30:00']);

        $rows = $this->getSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'eventAt');
        $this->assertCount(1, $rows);
        $this->assertSame(0, $this->countSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'event_at'));

        $reloaded = TestSettingsPureModel::find($model->getKey());
        $this->assertInstanceOf(Carbon::class, $reloaded->eventAt);
        $this->assertSame('2024-01-15 10:30:00', $reloaded->eventAt->format('Y-m-d H:i:s'));
    }

    // =====================================================================
    // Trait behaviour beyond casts
    // =====================================================================

    //
    // getLocalizedData()
    //

    public function testGetLocalizedDataThrowsForNonMultilingualProp(): void
    {
        $model = new TestSettingsSchemaModel();

        // authorsList is a setting but not multilingual → must throw.
        $this->expectException(Exception::class);
        $model->getLocalizedData('authorsList');
    }

    public function testGetLocalizedDataStrictReturnsNullForMissingLocale(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'Only EN']);
        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertSame('Only EN', $model->getLocalizedData('title', 'en', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
        $this->assertNull($model->getLocalizedData('title', 'de', TestSettingsSchemaModel::LOCALE_MATCH_STRICT));
    }

    public function testGetLocalizedDataBestMatchSelectsByPrecedenceAndReportsLocale(): void
    {
        // FixedPrecedencePureModel pins precedence to [preferred, 'de', 'en'] so the
        // best-match path is deterministic without an Application request/site.
        $modelId = $this->seedPureMultilingual('familyName', ['de' => 'Deutsch', 'en' => 'English'], self::PURE_SETTINGS_TABLE, self::PURE_TABLE);
        $model = FixedPrecedencePureModel::find($modelId);

        // Preferred locale present → returned, and reported via the &$selectedLocale out-param.
        $selected = null;
        $value = $model->getLocalizedData('familyName', 'de', !TestSettingsPureModel::LOCALE_MATCH_STRICT, $selected);
        $this->assertSame('Deutsch', $value);
        $this->assertSame('de', $selected);

        // Preferred locale absent → falls back along precedence ('de' before 'en').
        $selected = null;
        $value = $model->getLocalizedData('familyName', 'fr_CA', !TestSettingsPureModel::LOCALE_MATCH_STRICT, $selected);
        $this->assertSame('Deutsch', $value);
        $this->assertSame('de', $selected);
    }

    public function testGetLocalizedDataBestMatchReturnsNullWhenEmpty(): void
    {
        // No familyName rows → multilingual prop hydrates to [] → best-match returns null.
        $modelId = DB::table(self::PURE_TABLE)->insertGetId(['parent_id' => 1], 'test_id');
        $model = FixedPrecedencePureModel::find($modelId);

        $this->assertNull($model->getLocalizedData('familyName', 'en', !TestSettingsPureModel::LOCALE_MATCH_STRICT));
    }

    //
    // id() attribute ↔ primaryKey
    //

    public function testIdAttributeReturnsPrimaryKeyValue(): void
    {
        $modelId = DB::table(self::SCHEMA_TABLE)->insertGetId(['parent_id' => 1], 'test_id');
        $model = TestSettingsSchemaModel::find($modelId);

        $this->assertEquals($modelId, $model->id);
        $this->assertSame($model->getKey(), $model->id);
    }

    public function testSettingIdAttributeAssignsPrimaryKey(): void
    {
        $model = new TestSettingsSchemaModel();
        $model->id = 123;

        // The id() set-mutator writes the primaryKey column.
        $this->assertSame(123, $model->getKey());
        $this->assertSame(123, $model->test_id);
    }

    //
    // setSchemaData() derivation
    //

    public function testSchemaModelDerivesSettingsAndMultilingualProps(): void
    {
        $model = new TestSettingsSchemaModel();

        $settings = $model->getSettings();
        // origin:setting props (incl. multilingual ones) are settings...
        $this->assertContains('authorsList', $settings);
        $this->assertContains('recordCount', $settings);
        $this->assertContains('coverImage', $settings);
        $this->assertContains('title', $settings);
        // ...primary props are not.
        $this->assertNotContains('parentId', $settings);
        $this->assertNotContains('id', $settings);

        $mult = $model->getMultilingualProps();
        $this->assertContains('title', $mult);
        $this->assertContains('subtitle', $mult);
        $this->assertContains('coverImage', $mult);
        $this->assertNotContains('authorsList', $mult);
    }

    public function testSchemaModelFillableExcludesReadOnlyAndIncludesWritable(): void
    {
        $fillable = (new TestSettingsSchemaModel())->getFillable();

        $this->assertContains('authorsList', $fillable); // writable setting
        $this->assertContains('parentId', $fillable);    // writable main prop
        $this->assertNotContains('id', $fillable);        // readOnly → excluded
    }

    //
    // getAttribute() access contract
    //

    public function testGetAttributeReadsSettingCamelCaseAndPrimarySnake(): void
    {
        $value = ['x', 'y'];
        $modelId = $this->seedSchemaSetting('authorsList', json_encode($value));
        DB::table(self::SCHEMA_TABLE)->where('test_id', $modelId)->update(['parent_id' => 9]);

        $model = TestSettingsSchemaModel::find($modelId);

        // Setting addressed by its camelCase key → cast applied.
        $this->assertSame($value, $model->getAttribute('authorsList'));
        // Primary column addressed by camelCase key → mapped to the snake_case DB column.
        $this->assertSame(9, $model->getAttribute('parentId'));
        // Access contract: a setting's snake_case form is not a setting key and is not
        // re-mapped to the camelCase attribute (returns null, not the value).
        $this->assertNull($model->getAttribute('authors_list'));
    }

    //
    // newEloquentBuilder() + mass-assignment / guarding
    //

    public function testQueryUsesSettingsBuilder(): void
    {
        $this->assertInstanceOf(SettingsBuilder::class, TestSettingsSchemaModel::query());
    }

    public function testSettingIsMassAssignable(): void
    {
        // A setting (not a real DB column) is mass-assignable via create().
        $model = TestSettingsPureModel::create(['parent_id' => 1, 'affiliateRoles' => ['a']]);

        $this->assertSame(1, $this->countSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'affiliateRoles'));
    }

    public function testGuardedSettingIsNotMassAssigned(): void
    {
        // A $guarded setting must be dropped by mass assignment.
        $model = GuardedSettingPureModel::create(['parent_id' => 1, 'lockedNote' => 'should-not-persist']);

        $this->assertSame(0, $this->countSettingRows(self::PURE_SETTINGS_TABLE, $model->getKey(), 'lockedNote'));
    }

    //
    // Helpers
    //

    /**
     * Seed a fresh model with one multilingual setting (one row per locale), returning its id.
     */
    private function seedSchemaMultilingual(string $settingName, array $byLocale, string $settingsTable, string $table): int
    {
        $modelId = DB::table($table)->insertGetId(['parent_id' => 1], 'test_id');
        foreach ($byLocale as $locale => $value) {
            DB::table($settingsTable)->insert([
                'test_id' => $modelId,
                'locale' => $locale,
                'setting_name' => $settingName,
                'setting_value' => $value,
            ]);
        }
        return $modelId;
    }

    /**
     * Pure-model alias of seedSchemaMultilingual (kept distinct for readability at call sites).
     */
    private function seedPureMultilingual(string $settingName, array $byLocale, string $settingsTable, string $table): int
    {
        return $this->seedSchemaMultilingual($settingName, $byLocale, $settingsTable, $table);
    }

    /**
     * Assert a non-multilingual setting was persisted under its camelCase name (and
     * NOT under the snake_case name), with the given cast-encoded raw setting_value.
     */
    private function assertStoredAsCamelCase(string $table, int $id, string $camelName, string $snakeName, ?string $expectedRawValue): void
    {
        $rows = $this->getSettingRows($table, $id, $camelName);
        $this->assertCount(1, $rows, "expected one row under camelCase setting_name {$camelName}");
        $this->assertSame($expectedRawValue, $rows[0]->setting_value, 'stored value should be the cast-encoded form');
        $this->assertSame(0, $this->countSettingRows($table, $id, $snakeName), "no row should be stored under snake_case {$snakeName}");
    }

    private function countSettingRows(string $table, int $id, string $settingName): int
    {
        return DB::table($table)
            ->where('test_id', $id)
            ->where('setting_name', $settingName)
            ->count();
    }

    private function encryptionAvailable(): bool
    {
        if (empty(config('app.key'))) {
            return false;
        }
        try {
            encrypt('probe');
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

/**
 * Pure model with a deterministic locale precedence, so the best-match getLocalizedData
 * path can be asserted without an Application request/site. Reuses the pure tables.
 */
class FixedPrecedencePureModel extends TestSettingsPureModel
{
    public function getLocalePrecedence(?string $preferredLocale = null): array
    {
        return array_values(array_unique(array_filter([$preferredLocale, 'de', 'en'])));
    }
}

/**
 * Pure model with a guarded setting, to assert that mass assignment drops it.
 * Reuses the pure tables.
 */
class GuardedSettingPureModel extends TestSettingsPureModel
{
    protected $guarded = ['lockedNote'];

    public function getSettings(): array
    {
        return array_merge(parent::getSettings(), ['lockedNote']);
    }
}

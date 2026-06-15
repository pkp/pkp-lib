<?php

/**
 * @file tests/classes/core/SettingsBuilderTest.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsBuilderTest
 *
 * @see \PKP\core\SettingsBuilder
 * @see \PKP\core\traits\EntityUpdate
 *
 * @brief Unit tests for the public surface of \PKP\core\SettingsBuilder — the
 * Eloquent query builder used by every ModelWithSettings consumer.
 */

namespace PKP\tests\classes\core;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\core\SettingsBuilder;
use PKP\tests\classes\core\traits\InteractsWithSettingsModel;
use PKP\tests\classes\core\traits\TestSettingsPureModel;
use PKP\tests\classes\core\traits\TestSettingsSchemaModel;
use PKP\tests\PKPTestCase;

#[CoversClass(SettingsBuilder::class)]
class SettingsBuilderTest extends PKPTestCase
{
    use InteractsWithSettingsModel;

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

    //
    // Schema-based model tests
    //

    public function testSchemaBasedClearMultilingualWithEmptyArray(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);
        $this->assertSettingRowCount('test_settings_schema_entity_settings', $modelId, 'title', 2);

        TestSettingsSchemaModel::find($modelId)->update(['title' => []]);

        $this->assertSettingRowCount('test_settings_schema_entity_settings', $modelId, 'title', 0);
    }

    public function testSchemaBasedClearMultilingualWithNull(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsSchemaModel::find($modelId)->update(['title' => null]);

        $this->assertSettingRowCount('test_settings_schema_entity_settings', $modelId, 'title', 0);
    }

    public function testSchemaBasedPartialMultilingualPreservesOtherLocales(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsSchemaModel::find($modelId)->update(['title' => ['en' => 'Updated']]);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'title');
        $this->assertCount(2, $rows);
        $byLocale = collect($rows)->keyBy('locale');
        $this->assertSame('Updated', $byLocale['en']->setting_value);
        $this->assertSame('Français', $byLocale['fr_CA']->setting_value);
    }

    public function testSchemaBasedNullLocaleDeletesSingleLocale(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsSchemaModel::find($modelId)->update(['title' => ['fr_CA' => null]]);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'title');
        $this->assertCount(1, $rows);
        $this->assertSame('en', $rows[0]->locale);
        $this->assertSame('English', $rows[0]->setting_value);
    }

    public function testSchemaBasedUnmentionedSettingPreserved(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        // Update only a non-settings column; settings rows must be untouched.
        TestSettingsSchemaModel::find($modelId)->update(['parentId' => 99]);

        $this->assertSettingRowCount('test_settings_schema_entity_settings', $modelId, 'title', 2);
    }

    public function testSchemaBasedNonMultilingualEmptyArrayUpdatesNotDeletes(): void
    {
        $modelId = DB::table('test_settings_schema_entity')->insertGetId(['parent_id' => 1], 'test_id');
        DB::table('test_settings_schema_entity_settings')->insert([
            'test_id' => $modelId,
            'locale' => '',
            'setting_name' => 'jsonSetting',
            'setting_value' => json_encode(['existing' => 'data']),
        ]);

        TestSettingsSchemaModel::find($modelId)->update(['jsonSetting' => []]);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'jsonSetting');
        $this->assertCount(1, $rows, 'Non-multilingual array setting must be updated, not deleted, when given []');
        $this->assertSame('[]', $rows[0]->setting_value);
    }

    public function testSchemaBasedMixedLocaleUpdateAndDelete(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        // One call: update `en`, delete `fr_CA`. Both must take effect.
        TestSettingsSchemaModel::find($modelId)->update([
            'title' => [
                'en' => 'Updated',
                'fr_CA' => null,
            ],
        ]);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'title');
        $this->assertCount(1, $rows, 'fr_CA row must be deleted while en remains');
        $this->assertSame('en', $rows[0]->locale);
        $this->assertSame('Updated', $rows[0]->setting_value);
    }

    public function testUpdateReturnsAffectedPrimaryRowCount(): void
    {
        $idA = $this->seedSchemaModel(['en' => 'A']);
        $idB = $this->seedSchemaModel(['en' => 'B']);

        // Query-builder update on a primary column returns the int count from
        // parent::update(). Use the query builder (not Model::update) to get
        // the int contract — Model::update() wraps the result as bool.
        $count = TestSettingsSchemaModel::query()
            ->whereKey([$idA, $idB])
            ->update(['parent_id' => 7]);
        $this->assertSame(2, $count);

        // Hydrated-model update returns bool (Eloquent's Model::update contract).
        // Document the bool path here so a regression to int from Model::update
        // would also trip — both contracts matter to callers.
        $bool = TestSettingsSchemaModel::find($idA)->update(['parentId' => 8]);
        $this->assertTrue($bool);
    }

    public function testUpdateMixedPrimaryAndSetting(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English']);

        TestSettingsSchemaModel::find($modelId)->update([
            'parentId' => 42,
            'title' => ['en' => 'Mixed Update'],
        ]);

        $primary = DB::table('test_settings_schema_entity')->where('test_id', $modelId)->first();
        $this->assertSame(42, (int) $primary->parent_id);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'title');
        $this->assertCount(1, $rows);
        $this->assertSame('Mixed Update', $rows[0]->setting_value);
    }

    public function testUpdatePrimaryOnlyLeavesSettingsUntouched(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsSchemaModel::find($modelId)->update(['parentId' => 13]);

        $primary = DB::table('test_settings_schema_entity')->where('test_id', $modelId)->first();
        $this->assertSame(13, (int) $primary->parent_id);

        // Settings rows must be untouched (parent::update() path).
        $this->assertSettingRowCount('test_settings_schema_entity_settings', $modelId, 'title', 2);
    }

    public function testUpdateAcceptsSnakeCaseKeys(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);

        // snake_case key on the update should produce the same DB state as familyName.
        TestSettingsPureModel::find($modelId)->update(['family_name' => ['en' => 'Snake']]);

        $rows = $this->getSettingRows('test_settings_pure_entity_settings', $modelId, 'familyName');
        $this->assertCount(2, $rows);
        $byLocale = collect($rows)->keyBy('locale');
        $this->assertSame('Snake', $byLocale['en']->setting_value);
        $this->assertSame('Français', $byLocale['fr_CA']->setting_value);
    }

    public function testUpdateNonMultilingualSettingUpsertAndEmptyString(): void
    {
        $modelId = DB::table('test_settings_schema_entity')->insertGetId(['parent_id' => 1], 'test_id');

        // First, upsert a non-multilingual string setting.
        TestSettingsSchemaModel::find($modelId)->update(['nonlocSetting' => 'foo']);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'nonlocSetting');
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]->locale);
        $this->assertSame('foo', $rows[0]->setting_value);

        // Then update to empty string — must overwrite the value, NOT delete the row.
        TestSettingsSchemaModel::find($modelId)->update(['nonlocSetting' => '']);

        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $modelId, 'nonlocSetting');
        $this->assertCount(1, $rows, 'Empty string is a value, not a clear signal');
        $this->assertSame('', $rows[0]->setting_value);
    }

    //
    // Pure-Eloquent model tests
    //

    public function testNonSchemaClearMultilingualWithEmptyArray(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);
        $this->assertSettingRowCount('test_settings_pure_entity_settings', $modelId, 'familyName', 2);

        TestSettingsPureModel::find($modelId)->update(['familyName' => []]);

        $this->assertSettingRowCount('test_settings_pure_entity_settings', $modelId, 'familyName', 0);
    }

    public function testNonSchemaClearMultilingualWithNull(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsPureModel::find($modelId)->update(['familyName' => null]);

        $this->assertSettingRowCount('test_settings_pure_entity_settings', $modelId, 'familyName', 0);
    }

    public function testNonSchemaPartialMultilingualPreservesOtherLocales(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsPureModel::find($modelId)->update(['familyName' => ['en' => 'Updated']]);

        $rows = $this->getSettingRows('test_settings_pure_entity_settings', $modelId, 'familyName');
        $this->assertCount(2, $rows);
        $byLocale = collect($rows)->keyBy('locale');
        $this->assertSame('Updated', $byLocale['en']->setting_value);
        $this->assertSame('Français', $byLocale['fr_CA']->setting_value);
    }

    public function testNonSchemaSingleLocaleNullDeletesOnlyThatLocale(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);

        TestSettingsPureModel::find($modelId)->update(['familyName' => ['fr_CA' => null]]);

        $rows = $this->getSettingRows('test_settings_pure_entity_settings', $modelId, 'familyName');
        $this->assertCount(1, $rows, 'Only the fr_CA row should be deleted; en must be preserved');
        $this->assertSame('en', $rows[0]->locale);
        $this->assertSame('English', $rows[0]->setting_value);
    }

    public function testNonSchemaMixedLocaleUpdateAndDelete(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);

        // One call: update `en`, delete `fr_CA`. Both must take effect.
        TestSettingsPureModel::find($modelId)->update([
            'familyName' => [
                'en' => 'Updated',
                'fr_CA' => null,
            ],
        ]);

        $rows = $this->getSettingRows('test_settings_pure_entity_settings', $modelId, 'familyName');
        $this->assertCount(1, $rows, 'fr_CA row must be deleted while en remains');
        $this->assertSame('en', $rows[0]->locale);
        $this->assertSame('Updated', $rows[0]->setting_value);
    }

    public function testNonSchemaUnmentionedSettingPreserved(): void
    {
        $modelId = $this->seedPureModel(['en' => 'English', 'fr_CA' => 'Français']);

        // Update only a non-settings column; settings rows must be untouched.
        TestSettingsPureModel::find($modelId)->update(['parent_id' => 99]);

        $this->assertSettingRowCount('test_settings_pure_entity_settings', $modelId, 'familyName', 2);
    }

    public function testNonSchemaNonMultilingualEmptyArrayUpdatesNotDeletes(): void
    {
        // Direct pure-path analogue of the Funder `grants` scenario (pkp/pkp-lib#12658):
        // passing [] for a non-multilingual setting must write the value, not wipe the row.
        $modelId = DB::table('test_settings_pure_entity')->insertGetId(['parent_id' => 1], 'test_id');
        DB::table('test_settings_pure_entity_settings')->insert([
            'test_id' => $modelId,
            'locale' => '',
            'setting_name' => 'metadata',
            'setting_value' => json_encode(['existing' => 'data']),
        ]);

        TestSettingsPureModel::find($modelId)->update(['metadata' => []]);

        $rows = $this->getSettingRows('test_settings_pure_entity_settings', $modelId, 'metadata');
        $this->assertCount(1, $rows, 'Non-multilingual array setting must be updated, not deleted, when given []');
        $this->assertSame('[]', $rows[0]->setting_value);
    }

    //
    // insertGetId() tests
    //

    public function testInsertGetIdWritesPrimaryAndSettingsCorrectly(): void
    {
        // 1. Primary-only insert: one main row, zero settings rows.
        $idA = TestSettingsSchemaModel::query()->insertGetId(['parent_id' => 5]);
        $this->assertGreaterThan(0, $idA);
        $this->assertSame(1, DB::table('test_settings_schema_entity')->where('test_id', $idA)->count());
        $this->assertSame(0, DB::table('test_settings_schema_entity_settings')->where('test_id', $idA)->count());

        // 2. Primary + multilingual setting: one main row + N locale rows.
        $idB = TestSettingsSchemaModel::query()->insertGetId([
            'parent_id' => 5,
            'title' => ['en' => 'English', 'fr_CA' => 'Français'],
        ]);
        $this->assertSame(1, DB::table('test_settings_schema_entity')->where('test_id', $idB)->count());
        $titleRows = $this->getSettingRows('test_settings_schema_entity_settings', $idB, 'title');
        $this->assertCount(2, $titleRows);
        $byLocale = collect($titleRows)->keyBy('locale');
        $this->assertSame('English', $byLocale['en']->setting_value);
        $this->assertSame('Français', $byLocale['fr_CA']->setting_value);

        // 3. Primary + non-multilingual setting: one main row + one settings row at locale=''.
        $idC = TestSettingsSchemaModel::query()->insertGetId([
            'parent_id' => 5,
            'nonlocSetting' => 'unilocale',
        ]);
        $rows = $this->getSettingRows('test_settings_schema_entity_settings', $idC, 'nonlocSetting');
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]->locale);
        $this->assertSame('unilocale', $rows[0]->setting_value);
    }

    public function testInsertGetIdConvertsSnakeCaseKeys(): void
    {
        // snake_case key in $values is converted to camelCase setting_name by getSettingRows().
        $id = TestSettingsPureModel::query()->insertGetId([
            'parent_id' => 5,
            'family_name' => ['en' => 'Snake'],
        ]);

        $rows = DB::table('test_settings_pure_entity_settings')->where('test_id', $id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('familyName', $rows[0]->setting_name, 'snake_case input should normalize to camelCase setting_name');
    }

    public function testInsertGetIdMultilingualEdgeCases(): void
    {
        // Two multilingual settings in one insert each get their own locale rows.
        $id = TestSettingsSchemaModel::query()->insertGetId([
            'parent_id' => 5,
            'title' => ['en' => 'T-en', 'fr_CA' => 'T-fr'],
            'subtitle' => ['en' => 'S-en'],
        ]);
        $this->assertCount(2, $this->getSettingRows('test_settings_schema_entity_settings', $id, 'title'));
        $this->assertCount(1, $this->getSettingRows('test_settings_schema_entity_settings', $id, 'subtitle'));

        // Empty multilingual array on insert produces zero rows for that prop.
        $id2 = TestSettingsSchemaModel::query()->insertGetId([
            'parent_id' => 5,
            'title' => [],
        ]);
        $this->assertCount(
            0,
            $this->getSettingRows('test_settings_schema_entity_settings', $id2, 'title'),
            'Empty multilingual array on insert should not produce settings rows'
        );
    }

    //
    // delete() tests
    //

    public function testDeleteHydratedModelRemovesPrimaryAndSettings(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);

        $deleted = TestSettingsSchemaModel::find($modelId)->delete();
        $this->assertTrue((bool) $deleted);

        $this->assertSame(0, DB::table('test_settings_schema_entity')->where('test_id', $modelId)->count());
        $this->assertSame(0, DB::table('test_settings_schema_entity_settings')->where('test_id', $modelId)->count());
    }

    public function testDeleteRemovesAllLocaleRowsForOneId(): void
    {
        $modelId = $this->seedSchemaModel([
            'en' => 'English',
            'fr_CA' => 'Français',
            'de' => 'Deutsch',
        ]);
        $this->assertSettingRowCount('test_settings_schema_entity_settings', $modelId, 'title', 3);

        TestSettingsSchemaModel::find($modelId)->delete();

        $this->assertSame(0, DB::table('test_settings_schema_entity_settings')->where('test_id', $modelId)->count());
    }

    public function testDeleteNonExistentIdIsNoOp(): void
    {
        // Pre-existing row that must NOT be touched.
        $survivorId = $this->seedSchemaModel(['en' => 'Survivor']);

        $unmatchedId = 999_999;
        $count = TestSettingsSchemaModel::query()->whereKey($unmatchedId)->delete();

        // Pure no-op on the primary table; survivor row + its settings still present.
        $this->assertSame(0, $count);
        $this->assertSame(1, DB::table('test_settings_schema_entity')->where('test_id', $survivorId)->count());
        $this->assertSettingRowCount('test_settings_schema_entity_settings', $survivorId, 'title', 1);
    }

    //
    // where() / whereIn() / whereNotIn() tests
    //

    public function testWhereSettingColumnRoutesToSettingsTable(): void
    {
        $idEn = $this->seedSchemaModel(['en' => 'English']);
        $idFr = $this->seedSchemaModel(['fr_CA' => 'Français']);

        $matched = TestSettingsSchemaModel::where('title', 'English')->get();

        $this->assertCount(1, $matched);
        $this->assertSame($idEn, (int) $matched->first()->getKey());
        // Sanity: ensure we didn't accidentally match the second model.
        $this->assertNotSame($idFr, (int) $matched->first()->getKey());
    }

    public function testWhereChainsPrimaryAndSetting(): void
    {
        $idMatch = $this->seedSchemaModel(['en' => 'Target']);
        DB::table('test_settings_schema_entity')->where('test_id', $idMatch)->update(['parent_id' => 42]);

        // Same title, different parentId → must not match.
        $this->seedSchemaModel(['en' => 'Target']);

        // Same parentId, different title → must not match.
        $idParentOnly = $this->seedSchemaModel(['en' => 'Other']);
        DB::table('test_settings_schema_entity')->where('test_id', $idParentOnly)->update(['parent_id' => 42]);

        // Primary column name is the snake_case DB column. SettingsBuilder::where()
        // forwards non-setting columns straight to the underlying query (no
        // camel→snake conversion), so callers pass DB column names.
        $matched = TestSettingsSchemaModel::where('parent_id', 42)->where('title', 'Target')->get();

        $this->assertCount(1, $matched);
        $this->assertSame($idMatch, (int) $matched->first()->getKey());
    }

    public function testWhereInSettingColumn(): void
    {
        $idA = $this->seedSchemaModel(['en' => 'Apple']);
        $idB = $this->seedSchemaModel(['en' => 'Banana']);
        $idC = $this->seedSchemaModel(['en' => 'Cherry']);

        $matched = TestSettingsSchemaModel::whereIn('title', ['Apple', 'Banana'])
            ->orderBy('test_id')
            ->get();

        // On stable-3_5_0, SettingsBuilder::getModelWithSettings() keys the returned
        // rows by primary key, so the hydrated collection is PK-keyed. Normalise with
        // array_values() before asserting on order. see pkp/pkp-lib#12197 where diverse between
        // main and stable-3.5.0
        $matchedIds = array_values($matched->map(fn ($m) => (int) $m->getKey())->all());
        $this->assertSame([$idA, $idB], $matchedIds);
        $this->assertNotContains($idC, $matchedIds);
    }

    public function testWhereNotInDelegatesToWhereIn(): void
    {
        $idA = $this->seedSchemaModel(['en' => 'Apple']);
        $idB = $this->seedSchemaModel(['en' => 'Banana']);
        $idC = $this->seedSchemaModel(['en' => 'Cherry']);

        $matched = TestSettingsSchemaModel::whereNotIn('title', ['Apple'])
            ->orderBy('test_id')
            ->get();

        // assertContains/assertNotContains are key-insensitive, so the PK-keyed
        // collection (stable-3_5_0) needs no array_values() normalisation here.
        $matchedIds = $matched->map(fn ($m) => (int) $m->getKey())->all();
        $this->assertNotContains($idA, $matchedIds);
        $this->assertContains($idB, $matchedIds);
        $this->assertContains($idC, $matchedIds);
    }

    //
    // getModels() tests
    //

    public function testGetModelsHydratesMultilingualSettingsAsLocaleArray(): void
    {
        $modelId = $this->seedSchemaModel(['en' => 'English', 'fr_CA' => 'Français']);
        DB::table('test_settings_schema_entity_settings')->insert([
            'test_id' => $modelId,
            'locale' => '',
            'setting_name' => 'nonlocSetting',
            'setting_value' => 'scalar',
        ]);

        $model = TestSettingsSchemaModel::find($modelId);

        // Multilingual setting hydrates as locale-keyed array.
        $title = $model->getAttribute('title');
        $this->assertIsArray($title);
        $this->assertSame('English', $title['en']);
        $this->assertSame('Français', $title['fr_CA']);

        // Non-multilingual setting hydrates as scalar string.
        $this->assertSame('scalar', $model->getAttribute('nonlocSetting'));
    }

    public function testGetModelsInitializesMissingMultilingualSettingToEmptyArray(): void
    {
        // Insert a primary row with NO settings rows at all.
        $modelId = DB::table('test_settings_schema_entity')->insertGetId(['parent_id' => 1], 'test_id');

        $model = TestSettingsSchemaModel::find($modelId);

        // Multilingual settings without rows should be initialized to [] by
        // SettingsBuilder::getModelWithSettings().
        $this->assertSame([], $model->getAttribute('title'));
        $this->assertSame([], $model->getAttribute('subtitle'));
    }

    //
    // Simple delegators
    //

    public function testDelegators(): void
    {
        $schemaQuery = TestSettingsSchemaModel::query();
        $this->assertSame('test_settings_schema', $schemaQuery->getSchemaName());
        $this->assertSame('test_settings_schema_entity_settings', $schemaQuery->getSettingsTable());
        $this->assertSame('test_id', $schemaQuery->getPrimaryKeyName());
        $this->assertTrue($schemaQuery->isSetting('title'));
        $this->assertFalse($schemaQuery->isSetting('parentId'));

        $pureQuery = TestSettingsPureModel::query();
        $this->assertNull($pureQuery->getSchemaName());
        $this->assertSame('test_settings_pure_entity_settings', $pureQuery->getSettingsTable());
        $this->assertSame('test_id', $pureQuery->getPrimaryKeyName());
        $this->assertTrue($pureQuery->isSetting('familyName'));
        $this->assertFalse($pureQuery->isSetting('parent_id'));
    }
}

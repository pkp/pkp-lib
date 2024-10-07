<?php

/**
 * @file tests/classes/validation/ValidatorControlledVocabTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ValidatorControlledVocabTest
 *
 * @brief Test class for ValidatorControlledVocab.
 */

namespace PKP\tests\classes\validation;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\controlledVocab\ControlledVocabEntry;
use PKP\controlledVocab\ControlledVocab;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\tests\PKPTestCase;
use PKP\validation\ValidatorControlledVocab;

#[CoversClass(ValidatorControlledVocab::class)]
class ValidatorControlledVocabTest extends PKPTestCase
{
    public function testValidatorControlledVocab()
    {
        $assocId = (DB::table("publications")
            ->select("publication_id as id")
            ->orderBy("publication_id", "desc")
            ->first()
            ->id ?? 0) + 100;
            
        $testControlledVocab = Repo::controlledVocab()->build(
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            Application::ASSOC_TYPE_CITATION,
            $assocId
        );

        // TODO : Investigate if possible to insert dummy symbolic in `controlled_vocab_entry_settings` table
        $controlledVocabEntryId1 = ControlledVocabEntry::create([
            'controlledVocabId' => $testControlledVocab->id,
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD => [
                'en' => 'testEntry',
            ],
        ])->id;

        $controlledVocabEntryId2 = ControlledVocabEntry::create([
            'controlledVocabId' => $testControlledVocab->id,
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD => [
                'en' => 'testEntry',
            ],
        ])->id;

        $validator = new ValidatorControlledVocab(
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            Application::ASSOC_TYPE_CITATION,
            $assocId
        );
        self::assertTrue($validator->isValid($controlledVocabEntryId1));
        self::assertTrue($validator->isValid($controlledVocabEntryId2));
        self::assertFalse($validator->isValid(3));

        // Delete the test entried
        ControlledVocab::find($testControlledVocab->id)->delete();
    }
}

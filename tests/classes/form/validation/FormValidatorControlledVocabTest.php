<?php

/**
 * @file tests/classes/form/validation/FormValidatorControlledVocabTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormValidatorControlledVocabTest
 *
 * @brief Test class for FormValidatorControlledVocab.
 */

namespace PKP\tests\classes\form\validation;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Facades\DB;
use PKP\form\validation\FormValidatorControlledVocab;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\tests\PKPTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\controlledVocab\ControlledVocab;
use PKP\controlledVocab\ControlledVocabEntry;

#[CoversClass(FormValidatorControlledVocab::class)]
class FormValidatorControlledVocabTest extends PKPTestCase
{
    public function testIsValid()
    {
        // Test form
        $form = new Form('some template');

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

        // Instantiate validator
        $validator = new FormValidatorControlledVocab(
            $form,
            'testData',
            FormValidator::FORM_VALIDATOR_REQUIRED_VALUE,
            'some.message.key',
            ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
            Application::ASSOC_TYPE_CITATION,
            $assocId
        );

        $form->setData('testData', $controlledVocabEntryId1);
        self::assertTrue($validator->isValid());

        $form->setData('testData', $controlledVocabEntryId2);
        self::assertTrue($validator->isValid());

        $form->setData('testData', 3);
        self::assertFalse($validator->isValid());

        // Delete the test vocab along with entries
        ControlledVocab::find($testControlledVocab->id)->delete();
    }
}

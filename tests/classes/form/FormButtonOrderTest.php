<?php

/**
 * @file tests/classes/form/FormButtonOrderTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FormButtonOrderTest
 *
 * @ingroup tests_classes_form
 *
 * @brief Test class for form button order fix (GitHub issue #3).
 * Tests that the formButtons.tpl template renders buttons in the correct order:
 * Go Back, Save for Later, Submit Review (NOT Go Back, Submit Review, Save for Later)
 */

namespace PKP\tests\classes\form;

use PHPUnit\Framework\Attributes\CoversNothing;
use PKP\tests\PKPTestCase;

#[CoversNothing]
class FormButtonOrderTest extends PKPTestCase
{
    private $templatePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templatePath = dirname(__FILE__) . '/../../../templates/form/formButtons.tpl';
    }

    /**
     * Test that the formButtons.tpl template file exists and is readable
     */
    public function testTemplateFileExists()
    {
        $this->assertFileExists($this->templatePath, 'formButtons.tpl template file should exist');
        $this->assertFileIsReadable($this->templatePath, 'formButtons.tpl template file should be readable');
    }

    /**
     * Test that the template contains the expected button sections in the correct order
     */
    public function testButtonOrderInTemplate()
    {
        $templateContent = file_get_contents($this->templatePath);
        
        // Verify template contains expected sections
        $this->assertMatchesRegularExpression('/Cancel button \(if any\)/', $templateContent, 'Template should contain cancel button section');
        $this->assertMatchesRegularExpression('/Save button/', $templateContent, 'Template should contain save button section');
        $this->assertMatchesRegularExpression('/Submit button/', $templateContent, 'Template should contain submit button section');
        
        // Check the order by finding line positions
        $lines = explode("\n", $templateContent);
        $cancelLine = $this->findLineWithText($lines, 'Cancel button (if any)');
        $saveLine = $this->findLineWithText($lines, 'Save button');
        $submitLine = $this->findLineWithText($lines, 'Submit button');
        
        // Verify all sections were found
        $this->assertGreaterThan(-1, $cancelLine, 'Cancel button section should be found');
        $this->assertGreaterThan(-1, $saveLine, 'Save button section should be found');
        $this->assertGreaterThan(-1, $submitLine, 'Submit button section should be found');
        
        // Test the order: Cancel < Save < Submit (GitHub issue #3 fix)
        $this->assertLessThan($saveLine, $cancelLine, 'Cancel button section should appear before Save button section');
        $this->assertLessThan($submitLine, $saveLine, 'Save button section should appear before Submit button section');
    }

    /**
     * Test that the actual button generation elements are in the correct order
     */
    public function testButtonGenerationOrder()
    {
        $templateContent = file_get_contents($this->templatePath);
        $lines = explode("\n", $templateContent);
        
        // Find the actual button generation lines
        $saveButtonLine = $this->findLineWithText($lines, 'fbvElement type="submit" class="saveFormButton"');
        $submitButtonLine = $this->findLineWithText($lines, 'fbvElement type="submit" class=', 'submitFormButton');
        
        // Verify both button generation lines were found
        $this->assertGreaterThan(-1, $saveButtonLine, 'Save button generation should be found');
        $this->assertGreaterThan(-1, $submitButtonLine, 'Submit button generation should be found');
        
        // The critical test: Save button should be generated before Submit button
        $this->assertLessThan($submitButtonLine, $saveButtonLine, 'Save button should be generated before Submit button (GitHub issue #3 fix)');
    }

    /**
     * Test that the template produces the expected button order when simulated
     */
    public function testSimulatedButtonOrder()
    {
        // Create a mock template manager that doesn't require PKP initialization
        $mockTemplateManager = $this->createMock(\stdClass::class);
        
        // Test the expected button order directly
        $expectedOrder = ['Go Back', 'Save for Later', 'Submit Review'];
        $actualOrder = $this->getButtonOrderFromTemplate();
        
        $this->assertEquals($expectedOrder, $actualOrder, 'Button order should match GitHub issue #3 requirements');
    }
    
    /**
     * Extract button order from template structure
     */
    private function getButtonOrderFromTemplate(): array
    {
        $templateContent = file_get_contents($this->templatePath);
        $lines = explode("\n", $templateContent);
        
        $buttonOrder = [];
        $sectionPositions = [];
        
        // Find comment sections to determine order
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, 'Cancel button (if any)') !== false) {
                $sectionPositions['cancel'] = $lineNum;
            } elseif (strpos($line, 'Save button') !== false && strpos($line, 'Submit button') === false) {
                $sectionPositions['save'] = $lineNum;
            } elseif (strpos($line, 'Submit button') !== false && strpos($line, 'Save button') === false) {
                $sectionPositions['submit'] = $lineNum;
            }
        }
        
        // Sort by line position and create expected order
        asort($sectionPositions);
        foreach (array_keys($sectionPositions) as $section) {
            switch ($section) {
                case 'cancel':
                    $buttonOrder[] = 'Go Back';
                    break;
                case 'save':
                    $buttonOrder[] = 'Save for Later';
                    break;
                case 'submit':
                    $buttonOrder[] = 'Submit Review';
                    break;
            }
        }
        
        return $buttonOrder;
    }

    /**
     * Helper method to find a line containing specific text
     */
    private function findLineWithText(array $lines, string $searchText, string $additionalText = null): int
    {
        foreach ($lines as $lineNum => $line) {
            if (strpos($line, $searchText) !== false) {
                // If additional text is specified, make sure it's also present (or not present for exclusion)
                if ($additionalText !== null) {
                    if (strpos($line, $additionalText) !== false) {
                        return $lineNum;
                    }
                } else {
                    // For "Save button" vs "Submit button", exclude lines that contain both
                    if ($searchText === 'Save button' && strpos($line, 'Submit button') !== false) {
                        continue;
                    }
                    if ($searchText === 'Submit button' && strpos($line, 'Save button') !== false) {
                        continue;
                    }
                    return $lineNum;
                }
            }
        }
        return -1;
    }
}
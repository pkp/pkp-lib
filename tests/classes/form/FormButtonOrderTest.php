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
 * @brief Test that validates the button order in form templates, specifically
 * ensuring that Save and Submit buttons appear in the correct order.
 */

namespace PKP\tests\classes\form;

use PKP\tests\PKPTestCase;
use PKP\template\PKPTemplateManager;
use PKP\core\Registry;

class FormButtonOrderTest extends PKPTestCase
{
    private $templateManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateManager = new PKPTemplateManager();
    }

    /**
     * Test that the formButtons.tpl template renders buttons in the correct order:
     * 1. Cancel button (if present)
     * 2. Save button (if present)
     * 3. Submit button
     */
    public function testFormButtonOrder()
    {
        // Set up template variables for a typical review form
        $this->templateManager->assign([
            'FBV_hideCancel' => false,
            'FBV_cancelText' => 'common.goBack',
            'FBV_saveText' => 'common.saveForLater',
            'FBV_submitText' => 'reviewer.article.submitReview',
            'FBV_translate' => true
        ]);

        // Render the template
        $output = $this->templateManager->fetch('form/formButtons.tpl');

        // Verify the output contains the expected buttons
        $this->assertStringContainsString('cancelButton', $output);
        $this->assertStringContainsString('saveFormButton', $output);
        $this->assertStringContainsString('submitFormButton', $output);

        // Test button order by checking positions in the rendered output
        $cancelPos = strpos($output, 'cancelButton');
        $savePos = strpos($output, 'saveFormButton');
        $submitPos = strpos($output, 'submitFormButton');

        // Assert that positions are in the correct order
        $this->assertNotFalse($cancelPos, 'Cancel button should be present');
        $this->assertNotFalse($savePos, 'Save button should be present');
        $this->assertNotFalse($submitPos, 'Submit button should be present');

        // Verify order: Cancel < Save < Submit
        $this->assertLessThan($savePos, $cancelPos, 'Cancel button should appear before Save button');
        $this->assertLessThan($submitPos, $savePos, 'Save button should appear before Submit button');

        // Additional verification: Extract button elements and verify their sequence
        $this->verifyButtonSequence($output);
    }

    /**
     * Test button order when only Save and Submit buttons are present (no Cancel)
     */
    public function testFormButtonOrderWithoutCancel()
    {
        // Set up template variables without cancel button
        $this->templateManager->assign([
            'FBV_hideCancel' => true,
            'FBV_saveText' => 'common.saveForLater',
            'FBV_submitText' => 'reviewer.article.submitReview',
            'FBV_translate' => true
        ]);

        // Render the template
        $output = $this->templateManager->fetch('form/formButtons.tpl');

        // Verify the output contains the expected buttons
        $this->assertStringNotContainsString('cancelButton', $output);
        $this->assertStringContainsString('saveFormButton', $output);
        $this->assertStringContainsString('submitFormButton', $output);

        // Test button order
        $savePos = strpos($output, 'saveFormButton');
        $submitPos = strpos($output, 'submitFormButton');

        // Assert that positions are in the correct order
        $this->assertNotFalse($savePos, 'Save button should be present');
        $this->assertNotFalse($submitPos, 'Submit button should be present');

        // Verify order: Save < Submit
        $this->assertLessThan($submitPos, $savePos, 'Save button should appear before Submit button');
    }

    /**
     * Test button order when only Submit button is present (no Save or Cancel)
     */
    public function testFormButtonOrderSubmitOnly()
    {
        // Set up template variables with only submit button
        $this->templateManager->assign([
            'FBV_hideCancel' => true,
            'FBV_submitText' => 'common.submit',
            'FBV_translate' => true
        ]);

        // Render the template
        $output = $this->templateManager->fetch('form/formButtons.tpl');

        // Verify the output contains only the submit button
        $this->assertStringNotContainsString('cancelButton', $output);
        $this->assertStringNotContainsString('saveFormButton', $output);
        $this->assertStringContainsString('submitFormButton', $output);
    }

    /**
     * Verify the button sequence in the HTML output using DOM parsing
     * 
     * @param string $output The rendered template output
     */
    private function verifyButtonSequence($output)
    {
        // Use DOMDocument to parse the HTML and verify button order
        $dom = new \DOMDocument();
        @$dom->loadHTML($output);
        
        $buttons = [];
        
        // Find all buttons and links with relevant classes
        $elements = $dom->getElementsByTagName('*');
        foreach ($elements as $element) {
            $class = $element->getAttribute('class');
            if (strpos($class, 'cancelButton') !== false) {
                $buttons[] = 'cancel';
            } elseif (strpos($class, 'saveFormButton') !== false) {
                $buttons[] = 'save';
            } elseif (strpos($class, 'submitFormButton') !== false) {
                $buttons[] = 'submit';
            }
        }
        
        // Expected order: cancel, save, submit
        $expectedOrder = ['cancel', 'save', 'submit'];
        
        // Verify that the buttons appear in the expected order
        $this->assertEquals($expectedOrder, $buttons, 'Buttons should appear in the order: Cancel, Save, Submit');
    }

    /**
     * Test that the fix for issue #3 is working correctly
     * This test specifically validates that the Save button comes before Submit button
     * as requested in the GitHub issue
     */
    public function testGitHubIssue3ButtonOrderFix()
    {
        // Set up template variables matching the review workflow scenario
        $this->templateManager->assign([
            'FBV_hideCancel' => false,
            'FBV_cancelText' => 'common.goBack',
            'FBV_saveText' => 'reviewer.article.saveForLater',
            'FBV_submitText' => 'reviewer.article.submitReview',
            'FBV_translate' => true
        ]);

        // Render the template
        $output = $this->templateManager->fetch('form/formButtons.tpl');

        // Extract the button order from the rendered output
        $buttonOrder = $this->extractButtonOrder($output);
        
        // Assert the correct order as specified in the GitHub issue
        // Expected: Go Back, Save for Later, Submit Review
        $this->assertSame(['cancel', 'save', 'submit'], $buttonOrder, 
            'Button order should be: Go Back, Save for Later, Submit Review (GitHub issue #3)');
    }

    /**
     * Extract button order from rendered HTML
     * 
     * @param string $output The rendered template output
     * @return array Array of button types in order of appearance
     */
    private function extractButtonOrder($output)
    {
        $order = [];
        
        // Find positions of each button type
        $positions = [];
        
        if (strpos($output, 'cancelButton') !== false) {
            $positions[strpos($output, 'cancelButton')] = 'cancel';
        }
        
        if (strpos($output, 'saveFormButton') !== false) {
            $positions[strpos($output, 'saveFormButton')] = 'save';
        }
        
        if (strpos($output, 'submitFormButton') !== false) {
            $positions[strpos($output, 'submitFormButton')] = 'submit';
        }
        
        // Sort by position and return the ordered array
        ksort($positions);
        return array_values($positions);
    }
}
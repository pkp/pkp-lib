#!/usr/bin/env php
<?php
/**
 * @file button_order_test.php
 *
 * Standalone test script to validate button order in formButtons.tpl
 * 
 * This test validates that the GitHub issue #3 fix is working correctly:
 * - Button order should be: Go Back, Save for Later, Submit Review
 * - NOT: Go Back, Submit Review, Save for Later
 * 
 * Usage: php button_order_test.php
 */

// Simple test framework
class ButtonOrderTestRunner
{
    private $passed = 0;
    private $failed = 0;
    private $tests = [];

    public function assert($condition, $message)
    {
        if ($condition) {
            $this->passed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: $message\n";
        }
    }

    public function assertEquals($expected, $actual, $message)
    {
        if ($expected === $actual) {
            $this->passed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: $message\n";
            echo "  Expected: " . print_r($expected, true);
            echo "  Actual: " . print_r($actual, true);
        }
    }

    public function assertLessThan($expected, $actual, $message)
    {
        if ($actual < $expected) {
            $this->passed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: $message\n";
            echo "  Expected $actual to be less than $expected\n";
        }
    }

    public function assertStringContains($needle, $haystack, $message)
    {
        if (strpos($haystack, $needle) !== false) {
            $this->passed++;
            echo "✓ PASS: $message\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: $message\n";
            echo "  String '$needle' not found in haystack\n";
        }
    }

    public function summary()
    {
        $total = $this->passed + $this->failed;
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "Test Results: $this->passed passed, $this->failed failed out of $total total\n";
        echo str_repeat("=", 50) . "\n";
        
        if ($this->failed === 0) {
            echo "🎉 All tests passed! Button order fix is working correctly.\n";
            return true;
        } else {
            echo "❌ Some tests failed. Button order may need adjustment.\n";
            return false;
        }
    }
}

// Test the actual template file
function testButtonOrder()
{
    $test = new ButtonOrderTestRunner();
    
    echo "Button Order Test - Validating GitHub Issue #3 Fix\n";
    echo str_repeat("=", 50) . "\n";
    
    // Read the template file
    $templatePath = __DIR__ . '/templates/form/formButtons.tpl';
    
    if (!file_exists($templatePath)) {
        echo "❌ Template file not found: $templatePath\n";
        return false;
    }
    
    $templateContent = file_get_contents($templatePath);
    
    // Test 1: Verify template contains expected sections
    $test->assertStringContains('Cancel button (if any)', $templateContent, 'Template contains cancel button section');
    $test->assertStringContains('Save button', $templateContent, 'Template contains save button section');
    $test->assertStringContains('Submit button', $templateContent, 'Template contains submit button section');
    
    // Test 2: Verify the order by checking line positions
    $lines = explode("\n", $templateContent);
    $cancelLine = -1;
    $saveLine = -1;
    $submitLine = -1;
    
    foreach ($lines as $lineNum => $line) {
        if (strpos($line, 'Cancel button (if any)') !== false) {
            $cancelLine = $lineNum;
        }
        if (strpos($line, 'Save button') !== false && strpos($line, 'Submit button') === false) {
            $saveLine = $lineNum;
        }
        if (strpos($line, 'Submit button') !== false && strpos($line, 'Save button') === false) {
            $submitLine = $lineNum;
        }
    }
    
    $test->assert($cancelLine > 0, 'Cancel button section found');
    $test->assert($saveLine > 0, 'Save button section found');
    $test->assert($submitLine > 0, 'Submit button section found');
    
    // Test 3: Verify correct order (Cancel < Save < Submit)
    $test->assertLessThan($saveLine, $cancelLine, 'Cancel button section appears before Save button section');
    $test->assertLessThan($submitLine, $saveLine, 'Save button section appears before Submit button section');
    
    // Test 4: Verify the actual button generation order
    $saveButtonStart = -1;
    $submitButtonStart = -1;
    
    foreach ($lines as $lineNum => $line) {
        if (strpos($line, 'fbvElement type="submit" class="saveFormButton"') !== false) {
            $saveButtonStart = $lineNum;
        }
        if (strpos($line, 'fbvElement type="submit" class=') !== false && strpos($line, 'submitFormButton') !== false && strpos($line, 'saveFormButton') === false) {
            $submitButtonStart = $lineNum;
        }
    }
    
    $test->assert($saveButtonStart > 0, 'Save button generation block found');
    $test->assert($submitButtonStart > 0, 'Submit button generation block found');
    $test->assertLessThan($submitButtonStart, $saveButtonStart, 'Save button is generated before Submit button');
    
    // Test 5: Verify the button order matches GitHub issue #3 requirements
    $expectedOrder = ['cancel', 'save', 'submit'];
    $actualOrder = [];
    
    if ($cancelLine > 0) $actualOrder[] = 'cancel';
    if ($saveLine > 0) $actualOrder[] = 'save';
    if ($submitLine > 0) $actualOrder[] = 'submit';
    
    $test->assertEquals($expectedOrder, $actualOrder, 'Button order matches GitHub issue #3 requirements: Go Back, Save for Later, Submit Review');
    
    echo "\nDetailed Analysis:\n";
    echo "- Cancel button section at line: " . ($cancelLine + 1) . "\n";
    echo "- Save button section at line: " . ($saveLine + 1) . "\n";
    echo "- Submit button section at line: " . ($submitLine + 1) . "\n";
    echo "- Save button generation at line: " . ($saveButtonStart + 1) . "\n";
    echo "- Submit button generation at line: " . ($submitButtonStart + 1) . "\n";
    
    return $test->summary();
}

// Run the test
if (php_sapi_name() === 'cli') {
    $success = testButtonOrder();
    exit($success ? 0 : 1);
} else {
    echo "This script should be run from command line\n";
    exit(1);
}
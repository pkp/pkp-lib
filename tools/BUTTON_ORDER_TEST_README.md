# Button Order Test

This directory contains tests to validate the button order fix for GitHub issue #11580.

## Issue Description

The Review workflow Step 3 form buttons were displayed in the wrong order:
- **Before (incorrect):** Go Back, Submit Review, Save for Later  
- **After (correct):** Go Back, Save for Later, Submit Review

## Test Files

### 1. `button_order_test.php`
A standalone test script that validates the button order in the `formButtons.tpl` template.

**Usage:**
```bash
php button_order_test.php
```

**What it tests:**
- Verifies the template contains all expected button sections
- Checks that button sections appear in the correct order in the template
- Validates that button generation code follows the correct sequence
- Confirms the fix addresses GitHub issue #3 requirements

### 2. PHPUnit Testing Note
PHPUnit tests for this can be run with standard lib/pkp/tools/runAllTest.sh.  The standalone test provides comprehensive validation without framework dependencies.

## Running the Tests

### Quick Test (Standalone)
```bash
cd /path/to/pkp-lib/tools
php button_order_test.php
```

This standalone test provides comprehensive validation of the button order fix without requiring the full PKP framework.

## Expected Output

When the fix is working correctly, you should see:
```
Button Order Test - Validating GitHub Issue #3 Fix
==================================================
✓ PASS: Template contains cancel button section
✓ PASS: Template contains save button section  
✓ PASS: Template contains submit button section
✓ PASS: Cancel button section found
✓ PASS: Save button section found
✓ PASS: Submit button section found
✓ PASS: Cancel button section appears before Save button section
✓ PASS: Save button section appears before Submit button section
✓ PASS: Save button generation block found
✓ PASS: Submit button generation block found
✓ PASS: Save button is generated before Submit button
✓ PASS: Button order matches GitHub issue #3 requirements

🎉 All tests passed! Button order fix is working correctly.
```

## Technical Details

The test validates the fix by:
1. Reading the `templates/form/formButtons.tpl` file
2. Parsing the template structure to find button sections
3. Verifying the order of button generation code
4. Confirming the sequence matches the expected order

The fix ensures that the template renders buttons in this order:
1. Cancel button (Go Back)
2. Save button (Save for Later) 
3. Submit button (Submit Review)

This addresses the user experience issue where the primary "Submit Review" button was appearing in the middle instead of at the end.
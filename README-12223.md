# Fix: Add Authors to CSV Report from Statistics > Articles

## Issue
**GitHub Issue:** #12223  
**Title:** OJS 3.5 - Add authors to the CSV report downloaded from Statistics > Articles

### Problem
Journal Editors and Journal Managers use the Statistics > Articles section in OJS 3.5 to download CSV reports for article usage data. However, the CSV report generated from this section did not include author information, requiring editors to manually cross-reference article titles with author metadata.

### Current Limitation
- The report includes article level usage data (e.g. downloads by galley, titles)
- Author names were not included in the export
- Editors had to manually cross reference article titles with author metadata

## Solution
Added author information to the CSV report generated from Statistics > Articles. The authors are displayed as a comma-separated list (e.g., "Smith, J., Jones, M., et al.") in a new column positioned after the article title.

## Files Changed
- `api/v1/stats/publications/PKPStatsPublicationController.php`
  - Modified `_getSubmissionReportColumnNames()` to add authors column header
  - Modified `getItemForCSV()` to retrieve and include author information

## Changes Made

### 1. Added Authors Column Header
**File:** `api/v1/stats/publications/PKPStatsPublicationController.php`  
**Method:** `_getSubmissionReportColumnNames()`

Added `__('submission.authors')` as the third column in the CSV report (after ID and Title).

### 2. Included Author Data in CSV Rows
**File:** `api/v1/stats/publications/PKPStatsPublicationController.php`  
**Method:** `getItemForCSV()`

- Retrieves the current publication from the submission
- Gets the authors string using `getShortAuthorString()` method
- Includes the authors string in the CSV row data

## CSV Export Structure

The CSV export now includes the following columns in order:

1. **ID** - Article ID
2. **Title** - Article title
3. **Authors** - Comma-separated list of author names (NEW)
4. **Total** - Total views/downloads
5. **Abstract Views** - Number of abstract views
6. **File Views** - Number of file views
7. **PDF** - Number of PDF views
8. **HTML** - Number of HTML views
9. **Other** - Number of other file type views

## Technical Details

- **Author Format:** Uses `Publication::getShortAuthorString()` which returns a comma-separated list format (e.g., "Smith, J., et al.")
- **Column Position:** Authors column is placed after Title for better readability
- **Backward Compatibility:** All existing columns remain in the same order, maintaining compatibility with existing workflows

## Testing Instructions

1. **Access Statistics Page:**
   - Log in as Journal Manager or Editor
   - Navigate to Statistics > Articles

2. **Download CSV Report:**
   - Apply any desired filters (date range, sections, issues, etc.)
   - Click "Download Report" button
   - Select "Download Submissions" option
   - Download the CSV file

3. **Verify Authors Column:**
   - Open the downloaded CSV file in Excel or a text editor
   - Verify that the "Authors" column appears as the third column
   - Verify that author names are displayed in comma-separated format
   - Verify that articles with multiple authors show all authors (or "et al." format for many authors)
   - Verify that articles without authors show an empty cell

4. **Test Edge Cases:**
   - Articles with single author
   - Articles with multiple authors
   - Articles with no authors
   - Articles with many authors (should show "et al." format)

## Expected Behavior

- The CSV export should include author information for each article
- Authors should be displayed in a clear, consistent format
- The export should maintain all existing functionality
- No errors should occur when generating the CSV report

## Use Cases Enabled

This fix enables editors to:
- Generate consolidated usage reports with author information
- Alphabetize or group data by author
- Calculate total PDF downloads across a defined period by author
- Prepare internal reports without manual copy and paste or data reconciliation

## Related Files

- `classes/publication/PKPPublication.php` - Contains `getShortAuthorString()` method used for author formatting
- `classes/submission/maps/Schema.php` - Contains `mapToStats()` method that also uses author formatting

## Notes

- The author string format matches the format used elsewhere in OJS (e.g., in the statistics table display)
- The implementation uses the existing `getShortAuthorString()` method to ensure consistency across the application
- No database changes are required for this fix

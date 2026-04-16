# Changelog

All notable changes to the SC AI Content Generator plugin will be documented in this file.

## [1.0.1] - 2026-04-16

### Major Changes
- Removed draft queue system for simplified workflow
- Implemented direct generation (draft + final in single optimized call)
- Removed DraftQueue and DraftGenerator services
- Removed draft queue cron hook

### Dashboard Improvements
- Simplified stats to Total Questions, Generated, Pending
- Updated filters to All/Pending/Complete (removed Draft/Final)
- Added checkbox system for batch generation (max 20 items)
- Added WordPress default pagination styling
- Added page number input for direct pagination navigation
- Changed question title links to public URLs
- Fixed Generated Time column display
- Reduced Recent Activity limit to 20 items
- Added mobile responsive styles

### Settings Page Changes
- Removed Manual Stats section
- Removed Manual Batch section
- Added manual cron trigger button for testing
- Removed draft queue settings (batch size, cron interval)
- Retained final batch size and cron time settings

### AJAX & Backend
- Updated handleGenerateDraft to use generateDirect
- Updated handleDraftBatchManual to use generateDirect
- Added handleManualCron for manual cron trigger
- Added handleDeleteQuestion with progress cleanup
- Added service_provider dependency to AjaxController

### Security
- All AJAX endpoints include nonce verification
- All endpoints check manage_options capability
- Input sanitization on all parameters

### Performance
- Optimized database queries for dashboard stats
- Added composite indexes to progress table
- Improved pagination performance

### Maintenance
- Removed debug console.log statements from production code
- Removed empty directories (includes/templates, tests/)
- Removed one-time migration script (migrate-drafts-to-final.php)
- Updated README.md with latest changes
- Updated plugin version to 1.0.1

## [1.0.0] - Initial Release
- Initial release of SC AI Content Generator plugin
- Dual content pipeline (draft + final)
- Draft queue system for async processing
- Dashboard with stats and activity timeline
- Manual batch processing
- API configuration (Groq, OpenRouter)
- Progress tracking database
- Shortcodes for content display

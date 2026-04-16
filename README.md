# SC AI Content Generator

A WordPress plugin that generates SEO-optimized content for questions using free AI APIs (Groq and OpenRouter).

## Features

- **Direct Generation**: Generates content in a single optimized call (draft + final combined)
- **SEO-Friendly Headings**: Descriptions include H2 headings for better structure and SEO
- **Long-Form Content**: Generates 600-800 words with detailed explanations
- **Automated Batch Processing**: Cron jobs for async batch processing
- **Dashboard**: Visual overview of content generation progress with activity timeline
- **Manual Cron Trigger**: Manually trigger cron jobs for testing
- **Batch Generation**: Select up to 20 questions for batch generation
- **Mobile Responsive**: Fully responsive dashboard and settings interface
- **Rate Limit Handling**: Circuit breaker pattern with automatic failover between providers
- **Progress Tracking**: Database table to track generation status per question
- **Lock Management**: Reset stuck questions and clear batch locks
- **Service-Based Architecture**: Modern PHP architecture with dependency injection

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Skill Certify Pro plugin (must be active)
- Composer (for autoloading - run `composer install` after installation)

## Installation

1. Upload the `sc-ai-content-generator` folder to the `/wp-content/plugins/` directory
2. Run `composer install` in the plugin directory to generate autoloader
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure API keys in the AI Settings menu
5. The plugin will automatically start generating content via cron jobs

## Menu Structure

- **AI Dashboard** (top-level menu)
  - Dashboard - Overview of content generation status with stats and timeline
  - AI Settings - Configure API keys, queue settings, and batch sizes

## API Configuration

The plugin uses two AI providers with automatic failover:

1. **Groq API** (primary for draft generation)
   - Ultra-fast, free API for initial content generation
   - Get API key at: https://console.groq.com/
   - Rate limit: 30 requests/minute

2. **OpenRouter API** (fallback and multi-model support)
   - Multi-model API with access to various AI models
   - Get API key at: https://openrouter.ai/
   - Rate limit: Varies by model

**Note**: Gemini provider has been removed. Use Groq (fast) or OpenRouter (multi-model) instead.

## Queue Settings

- **Final Batch Size**: Number of questions per batch (default: 20)
- **Cron Time**: Time to run the batch cron job (24h format, default: 04:00)
- **Enable Cron Jobs**: Toggle to enable/disable automatic content generation via cron
- **Manual Cron Trigger**: Button to manually trigger the final content generation cron job for testing

## Cron Jobs

- **Final Batch**: Runs at configured time daily, processes configured batch size
- **Retry Queue**: Processes failed items for retry

## Content Stages

1. **None**: Question has no AI content
2. **Final**: Content generated directly (600-800 words with SEO headings)

## Content Structure

Each generated description includes:
- **Understanding [Topic]**: 200-250 words explaining the concept and correct answer
- **Why Other Options Are Incorrect**: 200-250 words explaining wrong options
- **Real-World Application in [Exam]**: 200-250 words on practical application
- **3 FAQs**: Question and answer pairs for common queries
- **Exam Tip**: Strategic advice for the specific question

## Database Tables

The plugin creates a custom table `wp_sc_ai_progress` to track:
- Question ID
- Content stage (none/draft/final)
- Generation status (pending/processing/done/failed)
- Number of attempts
- Generation timestamp
- Error messages
- Composite indexes for performance

## Architecture

The plugin uses a service-oriented architecture:

- **Core**: Plugin bootstrap, service provider, dependency injection
- **Services**: API providers, Queue managers, Generators, Prompt builders, Parsers, Storage
- **Repositories**: Data access layer for questions and progress
- **Models**: Type-safe data structures
- **Admin**: Dashboard, settings, and AJAX controllers
- **Frontend**: Template functions and shortcodes

## Security

All AJAX handlers include:
- Nonce verification (CSRF protection)
- Capability checks (manage_options only)
- Input sanitization
- Circuit breaker pattern for API failure handling

## Shortcodes

- `[sc_ai_description]` - Display AI-generated description
- `[sc_ai_faqs]` - Display AI-generated FAQs
- `[sc_ai_exam_tip]` - Display exam tip

Use `stage="draft"`, `stage="final"`, or `stage="auto"` to control content stage.

## Support

For issues or questions, please refer to the Skill Certify Pro documentation.

## License

This plugin is part of the Skill Certify Pro ecosystem.

## Changelog

### Version 1.0.1 (April 2026)

**Major Changes:**
- Removed draft queue system for simplified workflow
- Implemented direct generation (draft + final in single optimized call)
- Removed DraftQueue and DraftGenerator services
- Removed draft queue cron hook

**Dashboard Improvements:**
- Simplified stats to Total Questions, Generated, Pending
- Updated filters to All/Pending/Complete (removed Draft/Final)
- Added checkbox system for batch generation (max 20 items)
- Added WordPress default pagination styling
- Added page number input for direct pagination navigation
- Changed question title links to public URLs
- Fixed Generated Time column display
- Reduced Recent Activity limit to 20 items
- Added mobile responsive styles

**Settings Page Changes:**
- Removed Manual Stats section
- Removed Manual Batch section
- Added manual cron trigger button for testing
- Removed draft queue settings (batch size, cron interval)
- Retained final batch size and cron time settings

**AJAX & Backend:**
- Updated handleGenerateDraft to use generateDirect
- Updated handleDraftBatchManual to use generateDirect
- Added handleManualCron for manual cron trigger
- Added handleDeleteQuestion with progress cleanup
- Added service_provider dependency to AjaxController

**Security:**
- All AJAX endpoints include nonce verification
- All endpoints check manage_options capability
- Input sanitization on all parameters

**Performance:**
- Optimized database queries for dashboard stats
- Added composite indexes to progress table
- Improved pagination performance


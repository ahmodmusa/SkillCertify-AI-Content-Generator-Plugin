# AI Content Generator Integration Guide

## Overview
This plugin generates SEO-optimized descriptions and FAQs for Skill Certify Pro questions using free AI APIs (Groq and OpenRouter) with automatic failover and circuit breaker pattern.

## Architecture Updates

### New Service-Based Architecture
The plugin has been refactored with a modern service-oriented architecture:
- **Dependency Injection**: Service container manages all dependencies
- **API Providers**: Pluggable API provider interface with Groq and OpenRouter
- **Circuit Breaker**: Automatic failover between providers on failure
- **Queue System**: Action Scheduler for async batch processing
- **Draft Queue Toggle**: Option to skip draft stage and generate directly

### Removed Gemini
- Gemini provider has been removed
- Use Groq (ultra-fast) or OpenRouter (multi-model) instead

### New File Structure
```
sc-ai-content-generator/
├── sc-ai-content-generator.php          # Main plugin file (bootstrap)
├── composer.json                         # Autoloader config
├── config/
│   ├── constants.php                     # Plugin constants
│   └── defaults.php                     # Default settings
├── src/
│   ├── Core/
│   │   ├── Plugin.php                   # Main plugin class
│   │   ├── ServiceProvider.php          # Dependency injection
│   │   └── Bootstrap.php                # Autoloader setup
│   ├── Services/
│   │   ├── API/
│   │   │   ├── ApiProviderInterface.php
│   │   │   ├── GroqProvider.php
│   │   │   ├── OpenRouterProvider.php
│   │   │   ├── ProviderPool.php
│   │   │   └── CircuitBreaker.php
│   │   ├── Queue/
│   │   │   ├── QueueManager.php
│   │   │   ├── DraftQueue.php
│   │   │   ├── FinalQueue.php
│   │   │   └── RetryQueue.php
│   │   ├── Generator/
│   │   │   ├── GeneratorService.php
│   │   │   ├── DraftGenerator.php
│   │   │   └── FinalGenerator.php
│   │   ├── Prompt/
│   │   │   ├── DraftPromptBuilder.php
│   │   │   └── FinalPromptBuilder.php
│   │   ├── Parser/
│   │   │   └── StructuredParser.php
│   │   └── Storage/
│   │       ├── ContentStorage.php
│   │       └── ProgressTracker.php
│   ├── Repositories/
│   │   ├── QuestionRepository.php
│   │   └── ProgressRepository.php
│   ├── Models/
│   │   ├── Question.php
│   │   ├── GenerationResult.php
│   │   └── ApiConfig.php
│   ├── Admin/
│   │   ├── DashboardController.php
│   │   ├── SettingsController.php
│   │   └── AjaxController.php
│   └── Frontend/
│       ├── TemplateFunctions.php
│       └── Shortcodes.php
├── views/
│   ├── dashboard.php                    # Dashboard template
│   └── settings.php                     # Settings template
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
└── database/
    ├── migrations/
    │   └── 001_create_progress_table.php
    └── seeds/
        └── default_settings.php
```

---

## Step-by-Step Integration

### Step 1: Install Dependencies
```bash
cd wp-content/plugins/sc-ai-content-generator
composer install
```

### Step 2: Configure API Keys
1. Go to **AI Dashboard → ⚙️ AI Settings**
2. Get free Groq key: https://console.groq.com
3. Get free OpenRouter key: https://openrouter.ai
4. Save both keys (Groq is primary, OpenRouter is fallback)
5. Configure queue settings (draft queue toggle, batch sizes, cron intervals)

### Step 3: Run Initial Batch
1. In AI Settings page
2. Click **"📝 Generate Draft"** or **"✨ Generate Final"**
3. Wait for processing (max 10 questions per manual batch)
4. Check progress bar and stats on Dashboard

### Step 4: Integrate with Single Question Template

Add this to `single-question.php` in Skill Certify Pro:

```php
// After get_header(), include AI functions if plugin is active
if ( function_exists( 'scp_has_unified_content' ) && scp_has_unified_content( get_the_ID() ) ) {
    
    // Output AI-generated exam tip (optional, after CTA section)
    scp_output_ai_exam_tip( get_the_ID() );
    
    // Replace or enhance existing FAQ section with AI FAQs
    $ai_faqs = scp_get_unified_faqs( get_the_ID() );
    if ( ! empty( $ai_faqs ) ) {
        // Use AI FAQs instead of generated FAQs
        $faqs = array_map( function( $f ) {
            return [
                'question' => $f['q'] ?? $f['question'] ?? '',
                'answer' => $f['a'] ?? $f['answer'] ?? ''
            ];
        }, $ai_faqs );
    }
}

// At the very end of the template, before closing tags
if ( function_exists( 'scp_output_ai_faq_schema' ) ) {
    scp_output_ai_faq_schema( get_the_ID() );
}
```

### Step 5: Use Shortcodes (Optional)

You can also use shortcodes to display AI content:

```php
echo do_shortcode( '[sc_ai_description stage="auto"]' );
echo do_shortcode( '[sc_ai_faqs stage="auto"]' );
echo do_shortcode( '[sc_ai_exam_tip]' );
```

---

## How It Works

### Content Generation Flow
1. **Queue Manager** enqueues actions via Action Scheduler
2. **Draft/Final Generator** picks pending questions from DB
3. **Prompt Builder** creates structured AI prompt with:
   - Question title, correct answer, explanation
   - All options (correct + wrong)
   - Category and exam name
   - Existing content (for polishing stage)
4. **Provider Pool** calls API with automatic failover:
   - Tries Groq first (ultra-fast)
   - Falls back to OpenRouter on failure
   - Circuit breaker prevents cascading failures
5. **Parser** extracts structured output:
   - `[DESCRIPTION]` - 3 paragraphs (200-250 words each)
   - `[FAQ_1_QUESTION/ANSWER]` - Question + answer
   - `[FAQ_2_QUESTION/ANSWER]` - Question + answer
   - `[FAQ_3_QUESTION/ANSWER]` - Question + answer
   - `[EXAM_TIP]` - Exam strategy
6. **Content Storage** saves:
   - Draft content to `_scp_description_draft`, `_scp_faqs_draft`
   - Final content to `_scp_description_final`, `_scp_faqs_final`
   - Exam tip to `_scp_ai_exam_tip`
   - Clears cache on save
7. **Progress Tracker** updates status in custom DB table

### Draft Queue Mode
- **ON**: Two-stage pipeline (draft → final)
- **OFF**: Single-stage (final only, skips draft)

### Cron Jobs
- **Draft Batch**: Runs at configured interval (hourly, twice daily, daily)
- **Final Batch**: Runs at configured time daily (default: 04:00)
- Uses Action Scheduler for reliable async processing

---

## Next Steps

### 1. Test the Integration
1. Activate the AI Content Generator plugin
2. Run `composer install` in plugin directory
3. Configure API keys
4. Run a small manual batch (5 questions)
5. Check Dashboard for stats and timeline
6. Check a question page to see AI content

### 2. Customize Prompt (Optional)
Edit `src/Services/Prompt/DraftPromptBuilder.php` or `FinalPromptBuilder.php` to:
- Change word counts
- Add more sections
- Adjust tone/style
- Add category-specific instructions

### 3. Monitor & Optimize
- Check Dashboard stats regularly
- Review generated content quality
- Adjust queue settings if needed
- Monitor API usage (provider dashboards)

---

## Troubleshooting

### "No question data found"
- Check that question has all options (A, B, C, D)
- Verify correct answer is set
- Ensure explanation exists

### "AI API call failed"
- Verify API keys are correct
- Check Groq/OpenRouter service status
- Check rate limits (Groq: 30/min, OpenRouter: varies)
- Check circuit breaker status in logs

### "Failed to parse AI output"
- AI returned malformed output
- Check prompt format
- May need to adjust parsing regex in StructuredParser

### Content not showing on frontend
- Verify functions are included
- Check `scp_has_unified_content()` returns true
- Ensure template integration is correct
- Clear transients if caching issues

### Draft queue not working
- Check "Enable Draft Queue" setting in AI Settings
- Verify cron jobs are scheduled
- Check Action Scheduler logs

---

## API Limits

| Service | Free Tier | Rate Limit |
|---------|-----------|------------|
| Groq | Unlimited | 30 requests/minute |
| OpenRouter | Free tier available | Varies by model |

Current batch processing: 2-6 seconds delay between requests (stays within rate limits).

---

## Support

For issues or questions:
1. Check WordPress debug log
2. Review progress table: `wp_sc_ai_progress`
3. Verify API keys in AI Settings
4. Test with manual batch first
5. Check Dashboard timeline for activity history

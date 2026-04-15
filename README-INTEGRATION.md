# AI Content Generator Integration Guide

## Overview
This plugin generates SEO-optimized descriptions and FAQs for Skill Certify Pro questions using free AI APIs (Gemini + Groq fallback).

## Updates Made

### 1. Fixed Post Type & Taxonomy
- **Before**: `sc_question`, `sc_category`
- **After**: `scp_question`, `scp_category` (matches Skill Certify Pro)

### 2. Updated Data Retrieval
- **Before**: Read from `_sc_question_data` meta
- **After**: Reads from Skill Certify Pro meta keys:
  - `_scp_option_a`, `_scp_option_b`, `_scp_option_c`, `_scp_option_d`
  - `_scp_correct_answer`
  - `post_content` or `_scp_explanation`

### 3. Fixed Admin Menu
- **Before**: Parent slug `skillcertify`
- **After**: Parent slug `scp-dashboard` (matches Skill Certify Pro menu)

### 4. Template Integration Functions
Added in `class-content-saver.php`:
- `scp_output_ai_faq_schema()` - Outputs FAQ schema JSON-LD
- `scp_get_ai_exam_tip()` - Gets exam tip string
- `scp_has_ai_content()` - Checks if AI content exists
- `scp_get_ai_faqs()` - Gets AI-generated FAQs array
- `scp_output_ai_exam_tip()` - Outputs styled exam tip

### 5. Manual Generation
Added `generate_single()` method in `class-batch-processor.php` for on-demand generation.

---

## Step-by-Step Integration

### Step 1: Configure API Keys
1. Go to **Skill Certify Pro → 🤖 AI Generator**
2. Get free Gemini key: https://aistudio.google.com/app/apikey
3. Get free Groq key: https://console.groq.com
4. Save both keys (Gemini is primary, Groq is fallback)

### Step 2: Run Initial Batch
1. In AI Generator admin page
2. Click **"▶ Run 100 Now"** or **"⚡ Run 500 Now"**
3. Wait for processing (4 seconds per question due to rate limits)
4. Check progress bar and stats

### Step 3: Integrate with Single Question Template

Add this to `single-question.php` in Skill Certify Pro:

```php
// After get_header(), include AI functions if plugin is active
if ( function_exists( 'scp_has_ai_content' ) && scp_has_ai_content( get_the_ID() ) ) {
    
    // Output AI-generated exam tip (optional, after CTA section)
    scp_output_ai_exam_tip( get_the_ID() );
    
    // Replace or enhance existing FAQ section with AI FAQs
    $ai_faqs = scp_get_ai_faqs( get_the_ID() );
    if ( ! empty( $ai_faqs ) ) {
        // Use AI FAQs instead of generated FAQs
        $faqs = array_map( function( $f ) {
            return [
                'question' => $f['q'],
                'answer' => $f['a']
            ];
        }, $ai_faqs );
    }
}

// At the very end of the template, before closing tags
if ( function_exists( 'scp_output_ai_faq_schema' ) ) {
    scp_output_ai_faq_schema( get_the_ID() );
}
```

### Step 4: Add Manual Generation Button (Optional)

Add to question edit screen in `meta-fields.php` or create a new metabox:

```php
// Add meta box for AI generation
add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'sc_ai_generator',
        '🤖 AI Content Generator',
        'sc_ai_meta_box_callback',
        'scp_question',
        'side',
        'high'
    );
});

function sc_ai_meta_box_callback( $post ) {
    $has_ai = function_exists( 'scp_has_ai_content' ) && scp_has_ai_content( $post->ID );
    $generated_at = get_post_meta( $post->ID, '_sc_ai_generated_at', true );
    
    ?>
    <div id="sc-ai-status">
        <?php if ( $has_ai ) : ?>
            <p style="color:green">✅ AI content generated</p>
            <p style="font-size:12px;color:#666">Generated: <?php echo esc_html( $generated_at ); ?></p>
            <button type="button" id="sc-regenerate-btn" class="button button-secondary">
                🔄 Regenerate
            </button>
        <?php else : ?>
            <p style="color:#666">No AI content yet</p>
            <button type="button" id="sc-generate-btn" class="button button-primary">
                🚀 Generate Now
            </button>
        <?php endif; ?>
        <p id="sc-ai-message" style="margin-top:10px;font-size:12px;"></p>
    </div>
    
    <script>
    document.getElementById('sc-generate-btn')?.addEventListener('click', function() {
        const btn = this;
        const msg = document.getElementById('sc-ai-message');
        btn.disabled = true;
        msg.textContent = 'Generating... please wait (10-15 seconds)';
        
        fetch('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'sc_ai_generate_single',
                post_id: <?php echo $post->ID; ?>,
                nonce: '<?php echo wp_create_nonce( 'sc_ai_single' ); ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if ( data.success ) {
                msg.textContent = '✅ Generated successfully! Refresh page to see changes.';
                msg.style.color = 'green';
            } else {
                msg.textContent = '❌ Error: ' + (data.data || 'Unknown error');
                msg.style.color = 'red';
                btn.disabled = false;
            }
        })
        .catch(() => {
            msg.textContent = '❌ Network error';
            msg.style.color = 'red';
            btn.disabled = false;
        });
    });
    
    document.getElementById('sc-regenerate-btn')?.addEventListener('click', function() {
        // Same as generate but with regenerate flag
        // Implementation similar to above
    });
    </script>
    <?php
}

// AJAX handler for single question generation
add_action( 'wp_ajax_sc_ai_generate_single', function() {
    check_ajax_referer( 'sc_ai_single', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_die();
    
    $post_id = intval( $_POST['post_id'] );
    
    if ( ! class_exists( 'SC_Batch_Processor' ) ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-batch-processor.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-ai-client.php';
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-prompt-builder.php';
    }
    
    $processor = new SC_Batch_Processor();
    $result = $processor->generate_single( $post_id );
    
    if ( $result['success'] ) {
        wp_send_json_success();
    } else {
        wp_send_json_error( $result['error'] );
    }
});
```

---

## How It Works

### Content Generation Flow
1. **Batch Processor** picks pending questions from DB
2. **Prompt Builder** creates structured AI prompt with:
   - Question title, correct answer, explanation
   - All options (correct + wrong)
   - Category and exam name
   - Existing content (for improvement)
3. **AI Client** calls Gemini API (free: 1500/day)
   - Falls back to Groq if Gemini fails
   - Rate limit: 4 seconds between requests
4. **Parser** extracts structured output:
   - `[DESCRIPTION]` - 3 paragraphs (150-200 words)
   - `[FAQ_1_QUESTION/ANSWER]` - Question + answer
   - `[FAQ_2_QUESTION/ANSWER]` - Why correct answer
   - `[FAQ_3_QUESTION/ANSWER]` - Memory tip/mistake
   - `[EXAM_TIP]` - Exam strategy
5. **Content Saver** saves:
   - Description to `post_content`
   - FAQs to `_sc_ai_faqs` meta
   - Exam tip to `_sc_ai_exam_tip` meta
   - Timestamps for tracking

### Daily Cron Job
- Runs every day at 2:00 AM
- Processes 100 questions per day (configurable)
- Retries failed questions up to 3 times
- Tracks progress in custom DB table

---

## Next Steps

### 1. Test the Integration
1. Activate the AI Content Generator plugin
2. Configure API keys
3. Run a small batch (10 questions)
4. Check a question page to see AI content

### 2. Customize Prompt (Optional)
Edit `class-prompt-builder.php` to:
- Change word counts
- Add more sections
- Adjust tone/style
- Add category-specific instructions

### 3. Add Regeneration Feature
Implement the AJAX handler shown in Step 4 to allow:
- Manual regeneration per question
- Force refresh of outdated content
- Batch regeneration by category

### 4. Monitor & Optimize
- Check progress stats regularly
- Review generated content quality
- Adjust prompt if needed
- Monitor API usage (Gemini dashboard)

---

## Troubleshooting

### "No question data found"
- Check that question has all options (A, B, C, D)
- Verify correct answer is set
- Ensure explanation exists

### "AI API call failed"
- Verify API keys are correct
- Check Gemini/Groq service status
- Check rate limits (Gemini: 15/min)

### "Failed to parse AI output"
- AI returned malformed output
- Check prompt format
- May need to adjust parsing regex

### Content not showing on frontend
- Verify functions are included
- Check `scp_has_ai_content()` returns true
- Ensure template integration is correct

---

## API Limits

| Service | Free Tier | Rate Limit |
|---------|-----------|------------|
| Gemini | 1500/day | 15 requests/minute |
| Groq | Unlimited | 30 requests/minute |

Current batch processing: 4 seconds delay between requests (stays within Gemini limits).

---

## File Structure

```
sc-ai-content-generator/
├── sc-ai-content-generator.php          # Main plugin file
├── includes/
│   ├── class-ai-client.php               # API calls (Gemini/Groq)
│   ├── class-batch-processor.php         # Batch processing
│   ├── class-content-saver.php          # Template functions
│   └── class-prompt-builder.php         # Prompt generation
└── admin/
    └── settings-page.php                 # Admin interface
```

---

## Support

For issues or questions:
1. Check WordPress debug log
2. Review progress table: `wp_sc_ai_progress`
3. Verify API keys in settings
4. Test with single question generation first

<?php
// admin/settings-page.php

defined( 'ABSPATH' ) || exit;

// Settings save
if ( isset( $_POST['sc_ai_save'] ) && check_admin_referer( 'sc_ai_settings' ) ) {
    update_option( 'sc_ai_primary_model', sanitize_text_field( $_POST['primary_model'] ?? 'groq' ) );
    update_option( 'sc_ai_gemini_key', sanitize_text_field( $_POST['gemini_key'] ?? '' ) );
    update_option( 'sc_ai_groq_key',   sanitize_text_field( $_POST['groq_key']   ?? '' ) );
    // Generation settings
    update_option( 'sc_ai_draft_batch_size', absint( $_POST['draft_batch_size'] ?? 25 ) );
    update_option( 'sc_ai_final_batch_size', absint( $_POST['final_batch_size'] ?? 20 ) );
    update_option( 'sc_ai_draft_cron_interval', sanitize_text_field( $_POST['draft_cron_interval'] ?? 'hourly' ) );
    update_option( 'sc_ai_final_cron_time', sanitize_text_field( $_POST['final_cron_time'] ?? '04:00' ) );
    update_option( 'sc_ai_enable_cron', isset( $_POST['enable_cron'] ) ? '1' : '0' );
    echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
}

global $wpdb;
$progress = $wpdb->get_row( "
    SELECT
        COUNT(*) as total,
        SUM( status = 'done'       ) as done,
        SUM( status = 'pending'    ) as pending,
        SUM( status = 'failed'     ) as failed,
        SUM( status = 'processing' ) as processing
    FROM {$wpdb->prefix}sc_ai_progress
" );

$pct = $progress->total > 0
     ? round( $progress->done / $progress->total * 100 )
     : 0;

// Get recent processing timeline with more details
$timeline = $wpdb->get_results( "
    SELECT question_id, status, generated_at, error_msg
    FROM {$wpdb->prefix}sc_ai_progress
    ORDER BY generated_at DESC
    LIMIT 10
" );

// Get recent errors
$recent_errors = $wpdb->get_results( "
    SELECT question_id, error_msg, generated_at
    FROM {$wpdb->prefix}sc_ai_progress
    WHERE status = 'failed' AND error_msg IS NOT NULL
    ORDER BY generated_at DESC
    LIMIT 5
" );
?>
<div class="wrap">
<h1>🤖 AI Content Generator</h1>

<div style="display:flex;gap:20px;flex-wrap:wrap">
  <!-- LEFT COLUMN -->
  <div style="flex:1;min-width:400px">

    <!-- PROGRESS -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>Progress</h2>
      <div style="background:#E5E7EB;border-radius:4px;height:24px;margin:12px 0;overflow:hidden">
        <div id="sc-progress-bar" style="background:#1B8FAF;width:<?= $pct ?>%;height:100%;border-radius:4px;transition:.3s"></div>
      </div>
      <p id="sc-progress-text">
        ✅ Done: <strong><?= number_format( $progress->done ?? 0 ) ?></strong> &nbsp;|&nbsp;
        ⏳ Pending: <strong><?= number_format( $progress->pending ?? 0 ) ?></strong> &nbsp;|&nbsp;
        ❌ Failed: <strong><?= number_format( $progress->failed ?? 0 ) ?></strong> &nbsp;|&nbsp;
        Total: <strong><?= number_format( $progress->total ?? 0 ) ?></strong>
        &nbsp; — <strong><?= $pct ?>% complete</strong>
      </p>
      <?php if ( $progress->processing > 0 ): ?>
        <p style="color:#d63638;font-size:13px">⚡ Currently processing <?= number_format( $progress->processing ) ?> question(s)...</p>
        <p style="font-size:12px;color:#666">Estimated time: ~<?= ceil( $progress->processing * 6 / 60 ) ?> minute(s)</p>
      <?php endif; ?>
    </div>

    <!-- SETTINGS -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>API Keys (Free)</h2>
      <form method="post">
        <?php wp_nonce_field( 'sc_ai_settings' ) ?>
        <table class="form-table">
          <tr>
            <th>Primary Model</th>
            <td>
              <select name="primary_model" class="regular-text">
                <option value="groq" <?= selected( get_option( 'sc_ai_primary_model', 'groq' ), 'groq' ) ?>>Groq (Fast, 30 req/min)</option>
                <option value="gemini" <?= selected( get_option( 'sc_ai_primary_model', 'groq' ), 'gemini' ) ?>>Gemini (1500 req/day)</option>
              </select>
              <p class="description">
                Primary API to use. The other will be used as fallback if primary fails.
              </p>
            </td>
          </tr>
          <tr>
            <th>Gemini API Key <span style="color:green">(Recommended — 1500 free/day)</span></th>
            <td>
              <input type="text" name="gemini_key" class="regular-text"
                     value="<?= esc_attr( get_option( 'sc_ai_gemini_key' ) ) ?>"
                     placeholder="AIzaSy...">
              <p class="description">
                Get free key: <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>
              </p>
            </td>
          </tr>
          <tr>
            <th>Groq API Key <span style="color:blue">(Fallback — ultra fast)</span></th>
            <td>
              <input type="text" name="groq_key" class="regular-text"
                     value="<?= esc_attr( get_option( 'sc_ai_groq_key' ) ) ?>"
                     placeholder="gsk_...">
              <p class="description">
                Get free key: <a href="https://console.groq.com" target="_blank">console.groq.com</a>
              </p>
            </td>
          </tr>
        </table>
        <p>
          <input type="submit" name="sc_ai_save" class="button button-primary" value="Save Settings">
        </p>
      </form>
    </div>

    <!-- GENERATION SETTINGS -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>Generation Settings</h2>
      <form method="post">
        <?php wp_nonce_field( 'sc_ai_settings' ) ?>
        <table class="form-table">
          <tr>
            <th>Draft Batch Size</th>
            <td>
              <input type="number" name="draft_batch_size" class="small-text"
                     value="<?= esc_attr( get_option( 'sc_ai_draft_batch_size', 25 ) ) ?>"
                     min="1" max="100" style="width:80px">
              <p class="description">
                Number of questions to process per draft batch (default: 25)
              </p>
            </td>
          </tr>
          <tr>
            <th>Final Batch Size</th>
            <td>
              <input type="number" name="final_batch_size" class="small-text"
                     value="<?= esc_attr( get_option( 'sc_ai_final_batch_size', 20 ) ) ?>"
                     min="1" max="100" style="width:80px">
              <p class="description">
                Number of questions to process per final batch (default: 20)
              </p>
            </td>
          </tr>
          <tr>
            <th>Draft Cron Interval</th>
            <td>
              <select name="draft_cron_interval" class="regular-text">
                <option value="hourly" <?= selected( get_option( 'sc_ai_draft_cron_interval', 'hourly' ), 'hourly' ) ?>>Every Hour</option>
                <option value="twicedaily" <?= selected( get_option( 'sc_ai_draft_cron_interval', 'hourly' ), 'twicedaily' ) ?>>Twice Daily</option>
                <option value="daily" <?= selected( get_option( 'sc_ai_draft_cron_interval', 'hourly' ), 'daily' ) ?>>Daily</option>
              </select>
              <p class="description">
                How often to run the draft batch cron job
              </p>
            </td>
          </tr>
          <tr>
            <th>Final Cron Time</th>
            <td>
              <input type="time" name="final_cron_time" class="small-text"
                     value="<?= esc_attr( get_option( 'sc_ai_final_cron_time', '04:00' ) ) ?>">
              <p class="description">
                Time to run the final batch cron job (24h format, default: 04:00)
              </p>
            </td>
          </tr>
          <tr>
            <th>Enable Cron Jobs</th>
            <td>
              <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="enable_cron" value="1"
                       <?= checked( get_option( 'sc_ai_enable_cron', '1' ), '1' ) ?>>
                <span>Enable automatic content generation via cron</span>
              </label>
              <p class="description">
                When disabled, only manual generation will work
              </p>
            </td>
          </tr>
        </table>
        <p>
          <input type="submit" name="sc_ai_save" class="button button-primary" value="Save Settings">
        </p>
      </form>
    </div>

    <!-- MANUAL RUN -->
    <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>Manual Run</h2>
      <p>Cron runs 100 questions daily at 2 AM. To run now:</p>

      <div style="display:flex;align-items:center;gap:10px;margin:15px 0">
        <input type="number" id="sc-custom-batch" class="small-text" value="1" min="1" max="1000" style="width:80px">
        <span>questions</span>
        <label style="margin-left:10px;font-size:13px">
          <input type="checkbox" id="sc-skip-generated" checked> Skip already generated
        </label>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button id="sc-run-custom" class="button button-primary">
          ▶ Run Custom
        </button>
        <button id="sc-run-now" class="button button-secondary">
          ▶ Run 100
        </button>
        <button id="sc-run-500" class="button button-secondary">
          ⚡ Run 500
        </button>
        <button id="sc-stop-run" class="button button-secondary" style="background:#dc3545;color:#fff;border-color:#dc3545;display:none">
          ⏹ Stop
        </button>
      </div>

      <div id="sc-run-result" style="margin-top:12px;padding:12px;background:#F0F9FF;border-radius:4px;display:none"></div>
    </div>

    <!-- DUAL PIPELINE CONTROLS -->
    <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>Draft & Final Generation</h2>
      <p>Generate draft or polished final content manually (max 10 questions per batch):</p>

      <div style="display:flex;align-items:center;gap:10px;margin:15px 0">
        <input type="number" id="sc-dual-batch" class="small-text" value="5" min="1" max="10" style="width:80px">
        <span>questions</span>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button id="sc-run-draft-batch" class="button button-primary" style="background:#00a32a;border-color:#00a32a">
          📝 Generate Draft (5)
        </button>
        <button id="sc-run-final-batch" class="button button-primary" style="background:#d63638;border-color:#d63638">
          ✨ Generate Final (5)
        </button>
      </div>

      <div id="sc-dual-result" style="margin-top:12px;padding:12px;background:#F0F9FF;border-radius:4px;display:none"></div>
    </div>

    <!-- RECENT ERRORS -->
    <?php if ( ! empty( $recent_errors ) ): ?>
    <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>❌ Recent Errors</h2>
      <div style="max-height:200px;overflow-y:auto">
        <?php foreach ( $recent_errors as $error ): ?>
          <div style="padding:8px 0;border-bottom:1px solid #eee;font-size:13px">
            <div style="font-weight:bold">Q#<?= $error->question_id ?></div>
            <div style="color:#d63638;font-size:12px;margin-top:4px"><?= esc_html( $error->error_msg ) ?></div>
            <div style="color:#666;font-size:11px">
              <?= $error->generated_at ? human_time_diff( strtotime( $error->generated_at ), current_time( 'timestamp' ) ) . ' ago' : '—' ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- RIGHT COLUMN -->
  <div style="flex:1;min-width:300px">

    <!-- STATUS PANEL -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>📊 Status</h2>
      <table style="width:100%">
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee">✅ Done</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:bold"><?= number_format( $progress->done ?? 0 ) ?></td>
        </tr>
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee">⏳ Pending</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:bold"><?= number_format( $progress->pending ?? 0 ) ?></td>
        </tr>
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee">❌ Failed</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:bold"><?= number_format( $progress->failed ?? 0 ) ?></td>
        </tr>
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #eee">⚡ Processing</td>
          <td style="padding:8px 0;border-bottom:1px solid #eee;text-align:right;font-weight:bold"><?= number_format( $progress->processing ?? 0 ) ?></td>
        </tr>
        <tr>
          <td style="padding:8px 0;font-weight:bold">📦 Total</td>
          <td style="padding:8px 0;text-align:right;font-weight:bold"><?= number_format( $progress->total ?? 0 ) ?></td>
        </tr>
      </table>
    </div>

    <!-- TIMELINE -->
    <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>📅 Timeline (Recent)</h2>
      <?php if ( empty( $timeline ) ): ?>
        <p style="color:#666;font-style:italic">No recent activity</p>
      <?php else: ?>
        <div style="max-height:300px;overflow-y:auto">
          <?php foreach ( $timeline as $item ): ?>
            <div style="padding:8px 0;border-bottom:1px solid #eee;font-size:13px">
              <div style="display:flex;justify-content:space-between;align-items:center">
                <span>
                  <?php if ( $item->status === 'done' ): ?>
                    ✅ Q#<?= $item->question_id ?>
                  <?php elseif ( $item->status === 'failed' ): ?>
                    ❌ Q#<?= $item->question_id ?>
                  <?php elseif ( $item->status === 'processing' ): ?>
                    ⚡ Q#<?= $item->question_id ?>
                  <?php else: ?>
                    ⏳ Q#<?= $item->question_id ?>
                  <?php endif; ?>
                </span>
                <span style="color:#666;font-size:11px">
                  <?= $item->generated_at ? human_time_diff( strtotime( $item->generated_at ), current_time( 'timestamp' ) ) . ' ago' : '—' ?>
                </span>
              </div>
              <?php if ( $item->error_msg ): ?>
                <div style="color:#d63638;font-size:11px;margin-top:4px"><?= esc_html( $item->error_msg ) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- API STATUS -->
    <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>🔌 API Status</h2>
      <div style="font-size:13px">
        <div style="margin-bottom:8px">
          <strong>Gemini:</strong>
          <?= get_option( 'sc_ai_gemini_key' ) ? '<span style="color:green">✓ Configured</span>' : '<span style="color:red">✗ Not configured</span>' ?>
        </div>
        <div style="margin-bottom:8px">
          <strong>Groq:</strong>
          <?= get_option( 'sc_ai_groq_key' ) ? '<span style="color:green">✓ Configured</span>' : '<span style="color:orange">⚠ Optional</span>' ?>
        </div>
      </div>
      <div style="margin-top:15px">
        <button id="sc-simple-test" class="button button-secondary" style="font-size:13px">
          🧪 Simple Test
        </button>
        <button id="sc-reset-stuck" class="button button-secondary" style="font-size:13px;margin-left:10px">
          🔄 Reset Stuck & Clear Locks
        </button>
        <button id="sc-test-api" class="button button-secondary" style="font-size:13px;margin-left:10px">
          🔧 Test API Connection
        </button>
      </div>
      <div id="sc-api-test-result" style="margin-top:12px;padding:12px;background:#F0F9FF;border-radius:4px;display:none;font-size:13px"></div>
      <p style="font-size:12px;color:#666;margin-top:10px">
        Rate limit: ~6 seconds per question (includes 4s sleep for Gemini API)
      </p>
    </div>

  </div>
</div>
</div>

<script>
// Define ajaxurl for admin context
if (typeof ajaxurl === 'undefined') {
    ajaxurl = '<?= admin_url( 'admin-ajax.php' ) ?>';
}

let isRunning = false;
let progressInterval = null;
let abortController = null;

function runBatch(batch, skipGenerated = true) {
    if (isRunning) return;
    isRunning = true;

    // Show stop button
    document.getElementById('sc-stop-run').style.display = 'inline-block';

    const result = document.getElementById('sc-run-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px">
            <span style="font-size:20px">⚡</span>
            <div>
                <strong>Processing ${batch} question(s)...</strong><br>
                <span style="font-size:13px;color:#666">Estimated time: ~${Math.ceil(batch * 6 / 60)} minute(s). Please wait.</span>
            </div>
        </div>
    `;

    // Start progress polling
    startProgressPolling();

    abortController = new AbortController();

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_run_now',
            nonce:  '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
            batch: batch,
            skip_generated: skipGenerated ? '1' : '0',
        }),
        signal: abortController.signal,
    })
    .then( r => r.json() )
    .then( data => {
        isRunning = false;
        stopProgressPolling();
        hideStopButton();

        if ( data.success ) {
            const d = data.data;
            result.style.background = '#D4EDDA';
            result.innerHTML = `
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:20px">✅</span>
                    <div>
                        <strong>Batch Complete!</strong><br>
                        ✅ Done: <strong>${d.success}</strong> &nbsp;|&nbsp;
                        ❌ Failed: <strong>${d.failed}</strong> &nbsp;|&nbsp;
                        ⏭ Skipped: <strong>${d.skipped}</strong>
                    </div>
                </div>
            `;
            // Refresh progress
            updateProgress();
            // Refresh page after 2 seconds to show updated timeline
            setTimeout(() => location.reload(), 2000);
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = `<strong>Error:</strong> ${data.data}`;
        }
    })
    .catch( err => {
        if (err.name === 'AbortError') {
            result.style.background = '#FFF3CD';
            result.innerHTML = `<strong>Stopped:</strong> Batch processing cancelled.`;
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = `<strong>Error:</strong> ${err.message}`;
        }
        isRunning = false;
        stopProgressPolling();
        hideStopButton();
    });
}

function stopBatch() {
    if (abortController) {
        abortController.abort();
    }
}

function hideStopButton() {
    document.getElementById('sc-stop-run').style.display = 'none';
}

function updateProgress() {
    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_get_stats',
            nonce:  '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
        }),
    })
    .then( r => r.json() )
    .then( data => {
        if ( data.success ) {
            const stats = data.data;
            const total = parseInt(stats.total) || 0;
            const done = parseInt(stats.done) || 0;
            const pending = parseInt(stats.pending) || 0;
            const failed = parseInt(stats.failed) || 0;
            const processing = parseInt(stats.processing) || 0;

            const pct = total > 0 ? Math.round(done / total * 100) : 0;

            // Update progress bar
            document.getElementById('sc-progress-bar').style.width = pct + '%';

            // Update progress text
            document.getElementById('sc-progress-text').innerHTML =
                '✅ Done: <strong>' + numberFormat(done) + '</strong> &nbsp;|&nbsp;' +
                '⏳ Pending: <strong>' + numberFormat(pending) + '</strong> &nbsp;|&nbsp;' +
                '❌ Failed: <strong>' + numberFormat(failed) + '</strong> &nbsp;|&nbsp;' +
                'Total: <strong>' + numberFormat(total) + '</strong>' +
                ' &nbsp; — <strong>' + pct + '% complete</strong>';
        }
    });
}

function startProgressPolling() {
    updateProgress();
    progressInterval = setInterval(updateProgress, 3000); // Update every 3 seconds
}

function stopProgressPolling() {
    if (progressInterval) {
        clearInterval(progressInterval);
        progressInterval = null;
    }
}

function numberFormat(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

document.getElementById('sc-run-custom').addEventListener('click', () => {
    const batch = parseInt(document.getElementById('sc-custom-batch').value) || 1;
    const skipGenerated = document.getElementById('sc-skip-generated').checked;
    runBatch(batch, skipGenerated);
});

document.getElementById('sc-run-now').addEventListener('click', () => runBatch(100, true));
document.getElementById('sc-run-500').addEventListener('click', () => runBatch(500, true));
document.getElementById('sc-stop-run').addEventListener('click', stopBatch);

// Draft batch handler
document.getElementById('sc-run-draft-batch').addEventListener('click', () => {
    const batch = parseInt(document.getElementById('sc-dual-batch').value) || 5;
    const result = document.getElementById('sc-dual-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = `<strong>Generating draft for ${batch} question(s)...</strong>`;

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_draft_batch_manual',
            nonce: '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
            batch: batch,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            result.style.background = '#D4EDDA';
            result.innerHTML = `<strong>Draft Batch Complete!</strong> Generated ${data.data.success} drafts.`;
            setTimeout(() => location.reload(), 2000);
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = `<strong>Error:</strong> ${data.data.message || data.data || 'Unknown error'}`;
        }
    })
    .catch(err => {
        result.style.background = '#F8D7DA';
        result.innerHTML = `<strong>Error:</strong> ${err.message || err || 'Unknown error'}`;
    });
});

// Final batch handler
document.getElementById('sc-run-final-batch').addEventListener('click', () => {
    const batch = parseInt(document.getElementById('sc-dual-batch').value) || 5;
    const result = document.getElementById('sc-dual-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = `<strong>Generating final for ${batch} question(s)...</strong>`;

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_final_batch_manual',
            nonce: '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
            batch: batch,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            result.style.background = '#D4EDDA';
            result.innerHTML = `<strong>Final Batch Complete!</strong> Generated ${data.data.success} final contents.`;
            setTimeout(() => location.reload(), 2000);
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = `<strong>Error:</strong> ${data.data.message || data.data || 'Unknown error'}`;
        }
    })
    .catch(err => {
        result.style.background = '#F8D7DA';
        result.innerHTML = `<strong>Error:</strong> ${err.message || err || 'Unknown error'}`;
    });
});

// Simple test - no file includes, just AJAX test
document.getElementById('sc-simple-test').addEventListener('click', () => {
    const result = document.getElementById('sc-api-test-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = '<strong>Simple test (no file includes)...</strong>';

    console.log('Simple test...');
    console.log('AJAX URL:', ajaxurl);

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_simple_test',
            nonce: '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
        }),
    })
    .then( response => {
        console.log('Simple test response received:', response);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then( data => {
        console.log('Simple test data received:', data);
        if ( data.success ) {
            result.style.background = '#D4EDDA';
            result.innerHTML = '<strong>✅ Simple Test Success!</strong><br>Message: ' + data.data.message + '<br>Time: ' + data.data.time;
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = '<strong>Error:</strong> ' + data.data;
        }
    })
    .catch( err => {
        console.error('Simple test fetch error:', err);
        result.style.background = '#F8D7DA';
        result.innerHTML = '<strong>Error:</strong> ' + err.message + '<br><small>Check error log for [AI DEBUG] entries</small>';
    });
});

// Reset stuck questions
document.getElementById('sc-reset-stuck').addEventListener('click', () => {
    const result = document.getElementById('sc-api-test-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = '<strong>Resetting stuck questions...</strong>';

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_reset_stuck',
            nonce: '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
        }),
    })
    .then( r => r.json() )
    .then( data => {
        if ( data.success ) {
            result.style.background = '#D4EDDA';
            let msg = '<strong>Reset Complete!</strong><br>';
            if ( data.data.locks_cleared ) {
                msg += '✅ Cleared batch locks<br>';
            }
            if ( data.data.reset_count > 0 ) {
                msg += '✅ Reset ' + data.data.reset_count + ' stuck questions to pending.';
            } else {
                msg += 'No stuck questions found.';
            }
            result.innerHTML = msg;
            setTimeout(() => location.reload(), 1000);
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = '<strong>Error:</strong> ' + data.data;
        }
    })
    .catch( err => {
        result.style.background = '#F8D7DA';
        result.innerHTML = '<strong>Error:</strong> ' + err.message;
    });
});

// Test API connection
document.getElementById('sc-test-api').addEventListener('click', () => {
    const result = document.getElementById('sc-api-test-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = '<strong>Testing API connection...</strong>';

    console.log('Testing API...');
    console.log('AJAX URL:', ajaxurl);

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_test_api',
            nonce: '<?= wp_create_nonce( "sc_ai_nonce" ) ?>',
        }),
    })
    .then( response => {
        console.log('Response received:', response);
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then( data => {
        console.log('Data received:', data);
        if ( data.success ) {
            const r = data.data;
            let html = '<strong>API Test Results:</strong><br><br>';

            if ( r.gemini.status === 'success' ) {
                html += '✅ <strong>Gemini:</strong> Connected<br>';
            } else if ( r.gemini.status === 'not_configured' ) {
                html += '⚠ <strong>Gemini:</strong> Not configured<br>';
            } else if ( r.gemini.status === 'rate_limited' ) {
                html += '⏳ <strong>Gemini:</strong> Rate limited - ' + r.gemini.error + '<br>';
            } else {
                html += '❌ <strong>Gemini:</strong> Failed - ' + r.gemini.error + '<br>';
            }

            if ( r.groq.status === 'success' ) {
                html += '✅ <strong>Groq:</strong> Connected';
            } else if ( r.groq.status === 'not_configured' ) {
                html += '⚠ <strong>Groq:</strong> Not configured';
            } else if ( r.groq.status === 'rate_limited' ) {
                html += '⏳ <strong>Groq:</strong> Rate limited - ' + r.groq.error;
            } else {
                html += '❌ <strong>Groq:</strong> Failed - ' + r.groq.error;
            }

            result.style.background = '#D4EDDA';
            result.innerHTML = html;
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = '<strong>Error:</strong> ' + data.data;
        }
    })
    .catch( err => {
        console.error('Fetch error:', err);
        result.style.background = '#F8D7DA';
        result.innerHTML = '<strong>Error:</strong> ' + err.message + '<br><small>Check browser console for details (F12)</small>';
    });
});
</script>
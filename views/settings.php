<?php
defined( 'ABSPATH' ) || exit;

$config = \SC_AI\ContentGenerator\Models\ApiConfig::fromOptions();
?>

<div class="wrap">
<h1>🤖 AI Content Generator Settings</h1>

<div style="display:flex;gap:20px;flex-wrap:wrap">
  <!-- LEFT COLUMN -->
  <div style="flex:1;min-width:400px">

    <!-- API SETTINGS -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>API Settings</h2>
      <form method="post">
        <?php wp_nonce_field( 'sc_ai_settings' ) ?>
        <table class="form-table">
          <tr>
            <th>Primary Provider</th>
            <td>
              <select name="primary_provider" class="regular-text">
                <option value="groq" <?= selected( $config->primary_provider, 'groq' ) ?>>Groq (Fast, 30 req/min)</option>
                <option value="openrouter" <?= selected( $config->primary_provider, 'openrouter' ) ?>>OpenRouter (Multi-model)</option>
              </select>
              <p class="description">
                Primary API to use. The other will be used as fallback if primary fails.
              </p>
            </td>
          </tr>
          <tr>
            <th>Fallback Provider</th>
            <td>
              <select name="fallback_provider" class="regular-text">
                <option value="openrouter" <?= selected( $config->fallback_provider, 'openrouter' ) ?>>OpenRouter</option>
                <option value="groq" <?= selected( $config->fallback_provider, 'groq' ) ?>>Groq</option>
              </select>
              <p class="description">
                Fallback API if primary fails.
              </p>
            </td>
          </tr>
          <tr>
            <th>Groq API Key <span style="color:blue">(Ultra-fast)</span></th>
            <td>
              <input type="text" name="groq_key" class="regular-text"
                     value="<?= esc_attr( $config->groq_key ) ?>"
                     placeholder="gsk_...">
              <p class="description">
                Get free key: <a href="https://console.groq.com" target="_blank">console.groq.com</a>
              </p>
            </td>
          </tr>
          <tr>
            <th>OpenRouter API Key <span style="color:green">(Multi-model)</span></th>
            <td>
              <input type="text" name="openrouter_key" class="regular-text"
                     value="<?= esc_attr( $config->openrouter_key ) ?>"
                     placeholder="sk-or-...">
              <p class="description">
                Get free key: <a href="https://openrouter.ai" target="_blank">openrouter.ai</a>
              </p>
            </td>
          </tr>
          <tr>
            <th>Groq Model</th>
            <td>
              <input type="text" name="groq_model" class="regular-text"
                     value="<?= esc_attr( $config->groq_model ) ?>"
                     placeholder="llama-3.1-8b-instant">
            </td>
          </tr>
          <tr>
            <th>OpenRouter Model</th>
            <td>
              <input type="text" name="openrouter_model" class="regular-text"
                     value="<?= esc_attr( $config->openrouter_model ) ?>"
                     placeholder="openai/gpt-3.5-turbo">
            </td>
          </tr>
        </table>
        <p>
          <input type="submit" name="sc_ai_save" class="button button-primary" value="Save Settings">
        </p>
      </form>
    </div>

    <!-- QUEUE SETTINGS -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>Queue Settings</h2>
      <form method="post">
        <?php wp_nonce_field( 'sc_ai_settings' ) ?>
        <table class="form-table">
          <tr>
            <th>Batch Size</th>
            <td>
              <input type="number" name="final_batch_size" class="small-text"
                     value="<?= esc_attr( $config->final_batch_size ) ?>"
                     min="1" max="100" style="width:80px">
              <p class="description">
                Number of questions to process per batch
              </p>
            </td>
          </tr>
          <tr>
            <th>Cron Time</th>
            <td>
              <input type="time" name="final_cron_time" class="small-text"
                     value="<?= esc_attr( $config->final_cron_time ) ?>">
              <p class="description">
                Time to run the batch cron job (24h format)
              </p>
            </td>
          </tr>
          <tr>
            <th>Enable Cron Jobs</th>
            <td>
              <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="enable_cron" value="1"
                       <?= checked( $config->enable_cron, true ) ?>>
                <span>Enable automatic content generation via cron</span>
              </label>
              <p class="description">
                When disabled, only manual generation will work
              </p>
            </td>
          </tr>
          <tr>
            <th>Manual Cron Trigger</th>
            <td>
              <button id="sc-manual-cron" type="button" class="button button-secondary" style="font-size:13px">
                ⏰ Run Final Cron Now
              </button>
              <p class="description">
                Manually trigger the final content generation cron job
              </p>
            </td>
          </tr>
        </table>
        <p>
          <input type="submit" name="sc_ai_save" class="button button-primary" value="Save Settings">
        </p>
      </form>
    </div>

  </div>

  <!-- RIGHT COLUMN -->
  <div style="flex:1;min-width:300px">

    <!-- API STATUS -->
    <div class="card" style="padding:20px;margin-bottom:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>🔌 API Status</h2>
      <div style="font-size:13px">
        <div style="margin-bottom:8px">
          <strong>Groq:</strong>
          <?= $config->groq_key ? '<span style="color:green">✓ Configured</span>' : '<span style="color:orange">⚠ Optional</span>' ?>
        </div>
        <div style="margin-bottom:8px">
          <strong>OpenRouter:</strong>
          <?= $config->openrouter_key ? '<span style="color:green">✓ Configured</span>' : '<span style="color:orange">⚠ Optional</span>' ?>
        </div>
      </div>
      <div style="margin-top:15px">
        <button id="sc-test-api" class="button button-secondary" style="font-size:13px">
          🔧 Test API Connection
        </button>
        <button id="sc-reset-stuck" class="button button-secondary" style="font-size:13px;margin-left:10px">
          🔄 Reset Stuck
        </button>
      </div>
      <div id="sc-api-test-result" style="margin-top:12px;padding:12px;background:#F0F9FF;border-radius:4px;display:none;font-size:13px"></div>
    </div>

    <!-- CRON JOB HISTORY -->
    <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;box-shadow:0 1px 1px rgba(0,0,0,.04)">
      <h2>⏰ Cron Job History</h2>
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <thead>
          <tr style="background:#f9f9f9">
            <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Type</th>
            <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Status</th>
            <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Processed</th>
            <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Success</th>
            <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Failed</th>
            <th style="padding:8px;text-align:left;border-bottom:2px solid #ddd">Time</th>
          </tr>
        </thead>
        <tbody id="sc-cron-history">
          <?php
          $history = get_option( 'sc_ai_cron_history', [] );
          $history = array_slice( $history, 0, 10 ); // Show last 10
          foreach ( $history as $entry ) :
            $status_color = $entry['status'] === 'success' ? 'green' : ($entry['status'] === 'failed' ? 'red' : 'orange');
          ?>
          <tr style="border-bottom:1px solid #eee">
            <td style="padding:8px"><?= esc_html( $entry['type'] ) ?></td>
            <td style="padding:8px;color:<?= $status_color ?>;font-weight:bold"><?= esc_html( ucfirst( $entry['status'] ) ) ?></td>
            <td style="padding:8px"><?= esc_html( $entry['processed'] ) ?></td>
            <td style="padding:8px"><?= esc_html( $entry['success'] ) ?></td>
            <td style="padding:8px"><?= esc_html( $entry['failed'] ) ?></td>
            <td style="padding:8px"><?= esc_html( $entry['time'] ) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if ( empty( $history ) ) : ?>
          <tr>
            <td colspan="6" style="padding:12px;text-align:center;color:#666">No cron runs yet</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>
</div>

<script>
if (typeof ajaxurl === 'undefined') {
    ajaxurl = '<?= admin_url( 'admin-ajax.php' ) ?>';
}

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
            nonce: '<?= wp_create_nonce( 'sc_ai_nonce' ) ?>',
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
            nonce: '<?= wp_create_nonce( 'sc_ai_nonce' ) ?>',
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

// Test API connection
document.getElementById('sc-test-api').addEventListener('click', () => {
    const result = document.getElementById('sc-api-test-result');
    result.style.display = 'block';
    result.style.background = '#FFF3CD';
    result.innerHTML = '<strong>Testing API connection...</strong>';

    fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'sc_ai_test_api',
            nonce: '<?= wp_create_nonce( 'sc_ai_nonce' ); ?>',
        }),
    })
    .then( response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
        }
        return response.json();
    })
    .then( data => {
        if ( data.success ) {
            const r = data.data;
            let html = '<strong>API Test Results:</strong><br><br>';

            if ( r.groq ) {
                if ( r.groq.status === 'success' ) {
                    html += '✅ <strong>Groq:</strong> Connected<br>';
                } else if ( r.groq.status === 'not_configured' ) {
                    html += '⚠ <strong>Groq:</strong> Not configured<br>';
                } else if ( r.groq.status === 'rate_limited' ) {
                    html += '⏳ <strong>Groq:</strong> Rate limited - ' + r.groq.error + '<br>';
                } else {
                    html += '❌ <strong>Groq:</strong> Failed - ' + r.groq.error + '<br>';
                }
            }

            if ( r.openrouter ) {
                if ( r.openrouter.status === 'success' ) {
                    html += '✅ <strong>OpenRouter:</strong> Connected';
                } else if ( r.openrouter.status === 'not_configured' ) {
                    html += '⚠ <strong>OpenRouter:</strong> Not configured';
                } else if ( r.openrouter.status === 'rate_limited' ) {
                    html += '⏳ <strong>OpenRouter:</strong> Rate limited - ' + r.openrouter.error;
                } else {
                    html += '❌ <strong>OpenRouter:</strong> Failed - ' + r.openrouter.error;
                }
            }

            result.style.background = '#D4EDDA';
            result.innerHTML = html;
        } else {
            result.style.background = '#F8D7DA';
            result.innerHTML = '<strong>Error:</strong> ' + data.data;
        }
    })
    .catch( err => {
        result.style.background = '#F8D7DA';
        result.innerHTML = '<strong>Error:</strong> ' + err.message + '<br><small>Check browser console for details (F12)</small>';
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
            nonce: '<?= wp_create_nonce( 'sc_ai_nonce' ); ?>',
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

// Manual cron trigger
document.addEventListener('DOMContentLoaded', function() {
    const cronBtn = document.getElementById('sc-manual-cron');
    if (cronBtn) {
        cronBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const result = document.getElementById('sc-api-test-result');
            if (!result) {
                alert('Result element not found');
                return;
            }
            result.style.display = 'block';
            result.style.background = '#FFF3CD';
            result.innerHTML = '<strong>Running final cron job...</strong>';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'sc_ai_manual_cron',
                    nonce: '<?= wp_create_nonce( 'sc_ai_nonce' ); ?>',
                }),
            })
            .then( response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.json();
            })
            .then( data => {
                if ( data.success ) {
                    result.style.background = '#D4EDDA';
                    let msg = '<strong>Cron Job Complete!</strong><br>';
                    msg += '✅ Processed: ' + data.data.processed + '<br>';
                    msg += '✅ Success: ' + data.data.success + '<br>';
                    if ( data.data.failed > 0 ) {
                        msg += '❌ Failed: ' + data.data.failed;
                    }
                    result.innerHTML = msg;
                } else {
                    result.style.background = '#F8D7DA';
                    result.innerHTML = '<strong>Error:</strong> ' + (data.data || 'Unknown error');
                }
            })
            .catch( err => {
                result.style.background = '#F8D7DA';
                result.innerHTML = '<strong>Error:</strong> ' + err.message + '<br><small>Check browser console for details (F12)</small>';
            });
        });
    }
});
</script>

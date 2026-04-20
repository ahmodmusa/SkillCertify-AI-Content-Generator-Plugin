<?php
defined( 'ABSPATH' ) || exit;

$config = [
    'primary_provider' => get_option( 'sc_ai_primary_provider', 'groq' ),
    'fallback_provider' => get_option( 'sc_ai_fallback_provider', 'openrouter' ),
    'batch_provider' => get_option( 'sc_ai_batch_provider', 'groq' ),
    'groq_key' => get_option( 'sc_ai_groq_key', '' ),
    'openrouter_key' => get_option( 'sc_ai_openrouter_key', '' ),
    'groq_model' => get_option( 'sc_ai_groq_model', 'llama-3.1-8b-instant' ),
    'groq_max_tokens' => get_option( 'sc_ai_groq_max_tokens', 4000 ),
    'groq_batch_model' => get_option( 'sc_ai_groq_batch_model', 'llama-3.1-8b-instant' ),
    'openrouter_model' => get_option( 'sc_ai_openrouter_model', 'openai/gpt-3.5-turbo' ),
    'openrouter_max_tokens' => get_option( 'sc_ai_openrouter_max_tokens', 500 ),
    'final_batch_size' => get_option( 'sc_ai_final_batch_size', 20 ),
    'manual_batch_size' => get_option( 'sc_ai_manual_batch_size', 5 ),
    'final_cron_time' => get_option( 'sc_ai_final_cron_time', '04:00' ),
    'enable_cron' => get_option( 'sc_ai_enable_cron', '1' ),
];
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
                <option value="groq" <?= selected( $config['primary_provider'], 'groq' ) ?>>Groq (Fast, 30 req/min)</option>
                <option value="openrouter" <?= selected( $config['primary_provider'], 'openrouter' ) ?>>OpenRouter (Multi-model)</option>
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
                <option value="openrouter" <?= selected( $config['fallback_provider'], 'openrouter' ) ?>>OpenRouter</option>
                <option value="groq" <?= selected( $config['fallback_provider'], 'groq' ) ?>>Groq</option>
              </select>
              <p class="description">
                Fallback API if primary fails.
              </p>
            </td>
          </tr>
          <tr>
            <th>Batch Provider</th>
            <td>
              <select name="batch_provider" class="regular-text">
                <option value="groq" <?= selected( $config['batch_provider'], 'groq' ) ?>>Groq (Fast, 30 req/min)</option>
                <option value="openrouter" <?= selected( $config['batch_provider'], 'openrouter' ) ?>>OpenRouter (Multi-model)</option>
              </select>
              <p class="description">
                API to use for batch/cron jobs. Use Groq for speed, OpenRouter for quality.
              </p>
            </td>
          </tr>
          <tr>
            <th>Groq API Key <span style="color:blue">(Ultra-fast)</span></th>
            <td>
              <input type="text" name="groq_key" class="regular-text"
                     value="<?= esc_attr( $config['groq_key'] ) ?>"
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
                     value="<?= esc_attr( $config['openrouter_key'] ) ?>"
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
                     value="<?= esc_attr( $config['groq_model'] ) ?>"
                     placeholder="llama-3.1-8b-instant">
            </td>
          </tr>
          <tr>
            <th>Groq Max Tokens (Single Generate)</th>
            <td>
              <input type="number"
                name="groq_max_tokens"
                value="<?= esc_attr( $config['groq_max_tokens'] ) ?>"
                min="1000" max="8000" step="500"
                style="width:100px" />
              <p class="description">
                Tokens for Groq manual/single generation.
                Higher = longer content but slower.
                Recommended: 4000.
                Free tier max: 6000.
              </p>
            </td>
          </tr>
          <tr>
            <th>Groq Batch Model</th>
            <td>
              <select name="groq_batch_model">
                <option value="llama-3.1-8b-instant"
                  <?= selected( $config['groq_batch_model'], 'llama-3.1-8b-instant' ); ?>>
                  llama-3.1-8b-instant (Fast, free tier friendly)
                </option>
                <option value="llama-3.3-70b-versatile"
                  <?= selected( $config['groq_batch_model'], 'llama-3.3-70b-versatile' ); ?>>
                  llama-3.3-70b-versatile (Better quality)
                </option>
              </select>
              <p class="description">
                Model used for cron/batch processing.
                Use fast model for free tier to avoid rate limits.
              </p>
            </td>
          </tr>
          <tr>
            <th>OpenRouter Model</th>
            <td>
              <select name="openrouter_model" class="regular-text">
                <optgroup label="Free Models">
                  <option value="google/gemma-4-31b-it:free"
                    <?= selected( $config['openrouter_model'], 'google/gemma-4-31b-it:free' ); ?>>
                    google/gemma-4-31b-it:free (Good quality, free)
                  </option>
                  <option value="meta-llama/llama-3-8b-instruct:free"
                    <?= selected( $config['openrouter_model'], 'meta-llama/llama-3-8b-instruct:free' ); ?>>
                    meta-llama/llama-3-8b-instruct:free (Fast, free)
                  </option>
                  <option value="microsoft/wizardlm-2-8x22b:free"
                    <?= selected( $config['openrouter_model'], 'microsoft/wizardlm-2-8x22b:free' ); ?>>
                    microsoft/wizardlm-2-8x22b:free (Good quality, free)
                  </option>
                </optgroup>
                <optgroup label="Paid Models (with $5 credit)">
                  <option value="llama-3.3-70b-versatile"
                    <?= selected( $config['openrouter_model'], 'llama-3.3-70b-versatile' ); ?>>
                    llama-3.3-70b-versatile (Excellent quality)
                  </option>
                  <option value="openai/gpt-3.5-turbo"
                    <?= selected( $config['openrouter_model'], 'openai/gpt-3.5-turbo' ); ?>>
                    openai/gpt-3.5-turbo (Fast, good quality)
                  </option>
                  <option value="openai/gpt-4o-mini"
                    <?= selected( $config['openrouter_model'], 'openai/gpt-4o-mini' ); ?>>
                    openai/gpt-4o-mini (Very fast, cost efficient)
                  </option>
                </optgroup>
                <optgroup label="Custom">
                  <option value="openai/gpt-3.5-turbo"
                    <?= selected( $config['openrouter_model'], 'openai/gpt-3.5-turbo' ); ?>>
                    Default: openai/gpt-3.5-turbo
                  </option>
                </optgroup>
              </select>
              <p class="description">Choose a model. Free models require no credits but may have limits.</p>
            </td>
          </tr>
          <tr>
            <th>OpenRouter Max Tokens</th>
            <td>
              <input type="number" name="openrouter_max_tokens" class="regular-text"
                     value="<?= esc_attr( $config['openrouter_max_tokens'] ) ?>"
                     min="250" max="8000" step="50">
              <p class="description">Free models may have limits (e.g., 299 tokens). Set to 250-500 for free models.</p>
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
            <th>Batch Size (Cron)</th>
            <td>
              <input type="number" name="final_batch_size" class="small-text"
                     value="<?= esc_attr( $config['final_batch_size'] ) ?>"
                     min="1" max="100" style="width:80px">
              <p class="description">
                Number of questions to process per cron batch
              </p>
            </td>
          </tr>
          <tr>
            <th>Manual Batch Size</th>
            <td>
              <input type="number" name="manual_batch_size" class="small-text"
                     value="<?= esc_attr( $config['manual_batch_size'] ?? 5 ) ?>"
                     min="1" max="100" style="width:80px">
              <p class="description">
                Safe batch size for manual runs (editable, OpenRouter can handle higher batches)
              </p>
            </td>
          </tr>
          <tr>
            <th>Cron Time</th>
            <td>
              <input type="time" name="final_cron_time" class="small-text"
                     value="<?= esc_attr( $config['final_cron_time'] ) ?>">
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
                       <?= checked( $config['enable_cron'], true ) ?>>
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
                ⏰ Run Batch (<?= esc_attr( $config['manual_batch_size'] ?? 5 ) ?> questions)
              </button>
              <p class="description">
                Manually trigger batch processing with safe batch size
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
          <?= $config['groq_key'] ? '<span style="color:green">✓ Configured</span>' : '<span style="color:orange">⚠ Optional</span>' ?>
        </div>
        <div style="margin-bottom:8px">
          <strong>OpenRouter:</strong>
          <?= $config['openrouter_key'] ? '<span style="color:green">✓ Configured</span>' : '<span style="color:orange">⚠ Optional</span>' ?>
        </div>
      </div>
      <div style="margin-top:15px">
        <button id="sc-test-api" class="button button-secondary" style="font-size:13px">
          🔧 Test API Connection
        </button>
        <button id="sc-refresh-usage" class="button button-secondary" style="font-size:13px;margin-left:10px">
          📊 Refresh Usage
        </button>
        <button id="sc-reset-stuck" class="button button-secondary" style="font-size:13px;margin-left:10px">
          🔄 Reset Stuck
        </button>
      </div>
      <div id="sc-api-test-result" style="margin-top:12px;padding:12px;background:#F0F9FF;border-radius:4px;display:none;font-size:13px"></div>
      <div id="sc-usage-data" style="margin-top:12px;padding:12px;background:#F9F9F9;border-radius:4px;font-size:13px"></div>
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

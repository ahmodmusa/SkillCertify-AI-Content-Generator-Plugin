<?php

defined( 'ABSPATH' ) || exit;

// Plugin Version
define( 'SC_AI_VERSION', '1.0.2' );

// Plugin Paths - calculate from this file's location (config/ directory)
$plugin_dir = dirname( __DIR__ ) . '/';
define( 'SC_AI_PLUGIN_DIR', $plugin_dir );
define( 'SC_AI_PLUGIN_URL', plugin_dir_url( $plugin_dir . 'sc-ai-content-generator.php' ) );
define( 'SC_AI_PLUGIN_BASENAME', plugin_basename( $plugin_dir . 'sc-ai-content-generator.php' ) );

// Table Names
define( 'SC_AI_PROGRESS_TABLE', 'sc_ai_progress' );

// Content stages
define( 'SC_AI_STAGE_NONE', 'none' );

// Generation Status
define( 'SC_AI_STATUS_PENDING', 'pending' );
define( 'SC_AI_STATUS_PROCESSING', 'processing' );
define( 'SC_AI_STATUS_DONE', 'done' );
define( 'SC_AI_STATUS_FAILED', 'failed' );

// Queue Hooks
define( 'SC_AI_FINAL_QUEUE_HOOK', 'sc_ai_final_queue' );
define( 'SC_AI_RETRY_QUEUE_HOOK', 'sc_ai_retry_queue' );

// Cache Keys
define( 'SC_AI_CACHE_STATS', 'sc_ai_dashboard_stats' );
define( 'SC_AI_CACHE_ACTIVITIES', 'sc_ai_activities' );
define( 'SC_AI_CACHE_UNIFIED_CONTENT', 'scp_unified_content_' );

// Time Constants
define( 'SC_AI_CACHE_TIME_STATS', 5 * MINUTE_IN_SECONDS );
define( 'SC_AI_CACHE_TIME_ACTIVITIES', 2 * MINUTE_IN_SECONDS );
define( 'SC_AI_CACHE_TIME_CONTENT', DAY_IN_SECONDS );

// Rate Limits (seconds between requests)
define( 'SC_AI_RATE_LIMIT_GROQ', 2 );
define( 'SC_AI_RATE_LIMIT_OPENROUTER', 4 );

// Circuit Breaker Settings
define( 'SC_AI_CIRCUIT_FAILURE_THRESHOLD', 3 );
define( 'SC_AI_CIRCUIT_TIMEOUT', 300 ); // 5 minutes

// Queue Settings
define( 'SC_AI_DEFAULT_BATCH_SIZE', 25 );
define( 'SC_AI_MAX_BATCH_SIZE', 100 );

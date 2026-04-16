<?php

namespace SC_AI\ContentGenerator\Database\Seeds;

defined( 'ABSPATH' ) || exit;

class Seeder {
    public function seed(): void {
        $defaults = require SC_AI_PLUGIN_DIR . 'config/defaults.php';

        foreach ( $defaults as $key => $value ) {
            $option_key = 'sc_ai_' . $key;
            if ( get_option( $option_key ) === false ) {
                update_option( $option_key, $value );
            }
        }

        error_log( '[SC AI] Default settings seeded' );
    }
}

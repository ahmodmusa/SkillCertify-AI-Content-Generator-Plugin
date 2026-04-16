<?php

namespace SC_AI\ContentGenerator\Core;

defined( 'ABSPATH' ) || exit;

class Bootstrap {
    public static function run( string $plugin_file ): void {
        // Autoload classes (constants already loaded in main plugin file)
        spl_autoload_register( function( $class ) {
            $prefix = 'SC_AI\\ContentGenerator\\';
            $base_dir = SC_AI_PLUGIN_DIR . 'src/';
            
            $len = strlen( $prefix );
            if ( strncmp( $prefix, $class, $len ) !== 0 ) {
                return;
            }
            
            $relative_class = substr( $class, $len );
            $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
            
            if ( file_exists( $file ) ) {
                require $file;
            }
        } );
        
        // Initialize plugin
        $plugin = Plugin::getInstance( $plugin_file );
        $plugin->run();
    }
}

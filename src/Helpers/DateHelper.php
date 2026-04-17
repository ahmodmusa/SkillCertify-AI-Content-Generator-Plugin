<?php

namespace SC_AI\ContentGenerator\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * DateHelper - Utility class for date/time formatting
 */
class DateHelper {
    /**
     * Format a datetime string as a "time ago" string (e.g., "5 minutes ago")
     *
     * @param string $datetime MySQL datetime string
     * @return string Formatted time ago string
     */
    public static function formatTimeAgo( string $datetime ): string {
        $time = strtotime( $datetime );
        $now = current_time( 'timestamp' );
        $diff = $now - $time;

        if ( $diff < MINUTE_IN_SECONDS ) {
            return 'Just now';
        } elseif ( $diff < HOUR_IN_SECONDS ) {
            $minutes = floor( $diff / MINUTE_IN_SECONDS );
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        } elseif ( $diff < DAY_IN_SECONDS ) {
            $hours = floor( $diff / HOUR_IN_SECONDS );
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        } else {
            $days = floor( $diff / DAY_IN_SECONDS );
            return $days === 1 ? '1 day ago' : $days . ' days ago';
        }
    }
}

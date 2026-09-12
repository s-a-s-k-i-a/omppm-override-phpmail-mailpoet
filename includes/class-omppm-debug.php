<?php
/**
 * Bounded, private diagnostic events. Never accepts mail data.
 *
 * @package OMPPM
 */
namespace OMPPM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Debug {
    private const EVENTS = array(
        'constructor', 'mailer_built', 'alias_setup', 'mailpoet_missing',
        'alias_active', 'alias_failed', 'email_types_discovered', 'send_start',
        'recursion_fallback', 'type_supported', 'type_pattern', 'type_unsupported',
        'wp_mail_start', 'wp_mail_success', 'wp_mail_failure', 'send_exception',
        'reflection_exception',
    );

    public static function log( string $event ): void {
        if ( ! defined( 'OMPPM_DEBUG' ) || ! OMPPM_DEBUG || ! in_array( $event, self::EVENTS, true ) ) {
            return;
        }
        $events = get_option( 'omppm_debug_events', array() );
        $events = is_array( $events ) ? $events : array();
        $events[] = array( 'time' => gmdate( 'Y-m-d H:i:s' ), 'event' => $event );
        update_option( 'omppm_debug_events', array_slice( $events, -100 ), false );
    }
}

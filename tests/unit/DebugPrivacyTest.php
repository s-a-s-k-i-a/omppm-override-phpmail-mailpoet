<?php
/** @package OMPPM */
use PHPUnit\Framework\TestCase;

class DebugPrivacyTest extends TestCase {
	public function test_disabled_debug_does_not_write(): void {
		$GLOBALS['omppm_test_options']['omppm_debug_events'] = array();
		\OMPPM\Debug::log( 'wp_mail_failure' );
		$this->assertSame( array(), get_option( 'omppm_debug_events' ) );
	}

	public function test_enabled_debug_accepts_only_known_events_and_is_bounded(): void {
		// OMPPM_DEBUG is fixed at plugin load; use a separate PHP process for true.
		$script = 'define("ABSPATH", "/"); define("OMPPM_DEBUG", true); '
			. '$stored = array(); function get_option($name, $default) { global $stored; return $stored; } '
			. 'function update_option($name, $value, $autoload) { global $stored; if ($autoload !== false) { exit(2); } $stored = $value; } '
			. 'require ' . var_export( dirname( __DIR__, 2 ) . '/includes/class-omppm-debug.php', true ) . '; '
			. '\OMPPM\Debug::log("recipient@example.test: sensitive body"); '
			. 'if ($stored) { exit(3); } '
			. 'for ($i=0; $i<120; ++$i) { \OMPPM\Debug::log("wp_mail_failure"); } '
			. 'echo json_encode($stored);';
		$command = escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $script );
		exec( $command, $output, $status );
		$this->assertSame( 0, $status );
		$events = json_decode( implode( "\n", $output ), true );
		$this->assertCount( 100, $events );
		foreach ( $events as $event ) {
			$this->assertSame( array( 'time', 'event' ), array_keys( $event ) );
			$this->assertSame( 'wp_mail_failure', $event['event'] );
		}
	}
}

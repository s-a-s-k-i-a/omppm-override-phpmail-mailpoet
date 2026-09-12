<?php
/**
 * Minimal WordPress function stubs for the unit contract.
 *
 * @package OMPPM
 */

$GLOBALS['omppm_test_options']     = array( 'omppm_debug_enabled' => false );
$GLOBALS['omppm_test_mail_log']    = array();
$GLOBALS['omppm_test_mail_result'] = true;
$GLOBALS['omppm_test_actions']     = array();

/**
 * Return a test option value.
 *
 * @param string $name    Option name.
 * @param mixed  $default Default value.
 * @return mixed
 */
function get_option( $name, $default = false ) {
	if ( array_key_exists( $name, $GLOBALS['omppm_test_options'] ) ) {
		return $GLOBALS['omppm_test_options'][ $name ];
	}
	return $default;
}

/**
 * Record registered hooks without executing them.
 *
 * @param string   $hook     Hook name.
 * @param callable $callback Callback.
 * @param int      $priority Priority.
 * @param int      $args     Accepted arguments.
 * @return bool
 */
function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	$GLOBALS['omppm_test_actions'][] = array( $hook, $callback, $priority, $args );
	return true;
}

/**
 * The unit contract never runs in admin context.
 *
 * @return bool
 */
function is_admin() {
	return false;
}

/**
 * Sanitize scalar input for the unit contract.
 *
 * @param mixed $value Input value.
 * @return string
 */
function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

/**
 * Spy replacement for wp_mail().
 *
 * @param string|array $to          Recipient.
 * @param string       $subject     Subject.
 * @param string       $message     Body.
 * @param array        $headers     Headers.
 * @param array        $attachments Attachments.
 * @return bool
 */
function wp_mail( $to, $subject, $message, $headers = array(), $attachments = array() ) {
	$GLOBALS['omppm_test_mail_log'][] = array(
		'to'      => $to,
		'subject' => $subject,
		'message' => $message,
		'headers' => $headers,
	);

	$result = $GLOBALS['omppm_test_mail_result'];
	if ( $result instanceof Closure ) {
		return $result( $to, $subject, $message, $headers );
	}
	return $result;
}

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message, $data ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function remove_action( $hook, $callback, $priority = 10 ) {
	$GLOBALS['omppm_test_actions'] = array_values( array_filter( $GLOBALS['omppm_test_actions'], static function ( $entry ) use ( $hook, $callback, $priority ) {
		return $entry[0] !== $hook || $entry[1] !== $callback || $entry[2] !== $priority;
	} ) );
	return true;
}
function do_action( $hook, ...$args ) {
	foreach ( $GLOBALS['omppm_test_actions'] as $entry ) {
		if ( $entry[0] === $hook ) { ($entry[1])( ...array_slice( $args, 0, $entry[3] ) ); }
	}
}

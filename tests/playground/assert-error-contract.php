<?php
/**
 * Real MailPoet error contract regression; transport is always intercepted.
 * This is not an SMTP or newsletter-queue E2E test.
 *
 * @package OMPPM
 */

$method = new \OMPPM\MyPHPMailOverride(
	array( 'from_email' => 'sender@example.test', 'from_name' => 'Contract test' ),
	array( 'reply_to_email' => 'reply@example.test', 'reply_to_name' => 'Contract test' ),
	'bounce@example.test',
	new \MailPoet\Mailer\Methods\ErrorMappers\PHPMailMapper(),
	new \MailPoet\Util\Url( new \MailPoet\WP\Functions() )
);
$newsletter = array( 'subject' => 'Contract test', 'body' => array( 'html' => '<p>Test</p>', 'text' => 'Test' ) );
$mode = 'false';
$recipients = array();
$intercept = static function ( $pre, $atts ) use ( &$mode, &$recipients ) {
	$recipients[] = $atts['to'];
	if ( 'exception' === $mode ) {
		throw new \Exception( 'Synthetic transport exception' );
	}
	return 'success' === $mode;
};
add_filter( 'pre_wp_mail', $intercept, PHP_INT_MAX, 2 );
try {
	foreach ( array( 'false', 'exception', 'named-false', 'named-exception' ) as $failure_case ) {
		$failure_mode = str_replace( 'named-', '', $failure_case );
		$mode = $failure_mode;
		$email = $failure_case . '@example.test';
		$recipient = 0 === strpos( $failure_case, 'named-' ) ? 'Example Name <' . $email . '>' : $email;
		$result = $method->send( $newsletter, $recipient );
		if ( false !== $result['response'] || ! $result['error'] instanceof \MailPoet\Mailer\MailerError ) {
			throw new \RuntimeException( 'Failure must return the real MailPoet MailerError.' );
		}
		if ( $email !== end( $recipients ) ) {
			throw new \RuntimeException( 'Transport must receive only the normalized email address.' );
		}
		$error = $result['error'];
		$subscriber_errors = $error->getSubscriberErrors();
		if ( 1 !== count( $subscriber_errors ) || $recipient !== $subscriber_errors[0]->getEmail() ) {
			throw new \RuntimeException( 'Error mapper lost the original recipient string: ' . $failure_mode );
		}
		// This is the formatter invoked by SendingErrorHandler::processHardError().
		// Before the fix SubscriberError::__toString() returned an array (TypeError).
		$message = $error->getMessageWithFailedSubscribers();
		if ( false === strpos( $message, $recipient ) || \MailPoet\Mailer\MailerError::LEVEL_HARD !== $error->getLevel() ) {
			throw new \RuntimeException( 'Error formatting or native hard-error classification changed.' );
		}
		$mode = 'success';
		$next = $method->send( $newsletter, 'next@example.test' );
		if ( true !== $next['response'] || 'next@example.test' !== end( $recipients ) ) {
			throw new \RuntimeException( 'Subsequent send did not return to the intercepted transport.' );
		}
	}
	if ( 8 !== count( $recipients ) ) {
		throw new \RuntimeException( 'Unexpected transport calls or recursion-guard fallback.' );
	}
} finally {
	remove_filter( 'pre_wp_mail', $intercept, PHP_INT_MAX );
}
echo "PASS: Real MailPoet false/exception errors preserve recipient strings and format safely; following sends reset the guard. No SMTP delivery attempted.\n";

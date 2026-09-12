<?php
/**
 * Playground assertion: the class alias must be active inside a real
 * WordPress instance with MailPoet activated.
 *
 * Executed via: wp eval-file tests/playground/assert-alias.php
 *
 * @package OMPPM
 */

$failures = array();

if ( ! class_exists( 'MailPoet\\Mailer\\Mailer' ) ) {
	$failures[] = 'MailPoet is not active.';
}

if ( ! class_exists( '\\MailPoet\\Mailer\\Methods\\PHPMail' ) ) {
	$failures[] = 'No PHPMail class is loaded at all.';
} else {
	$reflection = new ReflectionClass( '\\MailPoet\\Mailer\\Methods\\PHPMail' );
	if ( 'OMPPM\\MyPHPMailOverride' !== $reflection->getName() ) {
		$failures[] = 'PHPMail is not aliased to OMPPM\\MyPHPMailOverride (got ' . $reflection->getName() . ').';
	}
	if ( ! is_subclass_of( 'OMPPM\\MyPHPMailOverride', 'MailPoet\\Mailer\\Methods\\PHPMailerMethod' ) ) {
		$failures[] = 'Override no longer extends MailPoet PHPMailerMethod.';
	}
}

if ( ! function_exists( 'OMPPM\\omppm_setup_alias' ) ) {
	$failures[] = 'Plugin bootstrap did not load.';
}

if ( $failures ) {
	foreach ( $failures as $failure ) {
		fwrite( STDERR, 'FAIL: ' . $failure . "\n" );
	}
	exit( 1 );
}

echo "PASS: MailPoet PHPMail resolves to OMPPM\\MyPHPMailOverride inside a live WordPress + MailPoet instance.\n";

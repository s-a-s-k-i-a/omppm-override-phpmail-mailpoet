<?php
/**
 * Playground assertion: the class alias must be active inside a real
 * WordPress instance with MailPoet activated.
 *
 * Executed by the Playground runPHP step with /omppm-assertions mounted.
 *
 * @package OMPPM
 */

$failures = array();
if (file_exists('/omppm-assertions/force-failure')) {
    file_put_contents('/omppm-assertions/negative-reached', 'OMPPM_NEGATIVE');
    $failures[] = 'Intentional negative control.';
}

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
    throw new RuntimeException(implode("\n", $failures));
}

if (false === file_put_contents('/omppm-assertions/passed', 'OMPPM_ALIAS_PASS')) {
    throw new RuntimeException('Cannot write assertion receipt.');
}
echo "PASS: MailPoet PHPMail resolves to OMPPM\\MyPHPMailOverride inside WordPress.\n";

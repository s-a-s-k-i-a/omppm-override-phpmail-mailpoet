<?php
/**
 * Verify release version parity.
 *
 * Usage: php scripts/check-version.php [expected-version]
 *
 * @package OMPPM
 */

$repository_root = dirname( __DIR__ );
$plugin_file     = file_get_contents( $repository_root . '/omppm-override-phpmail-mailpoet.php' );
$admin_class     = file_get_contents( $repository_root . '/includes/class-omppm-admin.php' );
$readme_txt      = file_get_contents( $repository_root . '/readme.txt' );
$readme_md       = file_get_contents( $repository_root . '/README.md' );
$expected        = isset( $argv[1] ) ? ltrim( $argv[1], 'v' ) : null;

$patterns = array(
	'plugin header'        => '/^ \* Version:\s+([^\s]+)$/m',
	'Admin PLUGIN_VERSION' => "/PLUGIN_VERSION\s*=\s*'([^']+)'/",
	'readme.txt stable'    => '/^Stable tag:\s+([^\s]+)\s*$/m',
	'README.md stable'     => '/^\*\*Stable tag:\*\*\s+([^\s]+)\s*$/m',
);

$sources = array(
	'plugin header'        => $plugin_file,
	'Admin PLUGIN_VERSION' => $admin_class,
	'readme.txt stable'    => $readme_txt,
	'README.md stable'     => $readme_md,
);

$versions = array();

foreach ( $patterns as $label => $pattern ) {
	if ( ! preg_match( $pattern, $sources[ $label ], $matches ) ) {
		fwrite( STDERR, sprintf( "Could not find %s.\n", $label ) );
		exit( 1 );
	}

	$versions[ $label ] = $matches[1];
}

$unique_versions = array_unique( array_values( $versions ) );

if ( 1 !== count( $unique_versions ) ) {
	fwrite( STDERR, 'Version mismatch: ' . json_encode( $versions ) . "\n" );
	exit( 1 );
}

$version = reset( $unique_versions );

if ( null !== $expected && $version !== $expected ) {
	fwrite( STDERR, sprintf( "Expected %s, found %s.\n", $expected, $version ) );
	exit( 1 );
}

fwrite( STDOUT, $version . "\n" );

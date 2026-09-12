<?php
/**
 * Version parity contract tests.
 *
 * @package OMPPM
 */

use PHPUnit\Framework\TestCase;

/**
 * The plugin header, the admin PLUGIN_VERSION constant, readme.txt and
 * README.md must always declare the same version. scripts/check-version.php
 * enforces the same contract in CI and releases.
 */
class VersionContractTest extends TestCase {

	/**
	 * Extract a version with a pattern or fail the test.
	 *
	 * @param string $pattern Regex with one capture group.
	 * @param string $content Haystack.
	 * @param string $label   Source label.
	 * @return string
	 */
	private function extract( $pattern, $content, $label ) {
		$this->assertSame( 1, preg_match( $pattern, $content, $matches ), "Could not find {$label}." );
		return $matches[1];
	}

	public function test_all_version_sources_match(): void {
		$root = dirname( __DIR__, 2 );

		require_once $root . '/includes/class-omppm-admin.php';
		$constant = ( new ReflectionClassConstant( 'OMPPM\\Admin\\OMPPM_Admin', 'PLUGIN_VERSION' ) )->getValue();

		$header = $this->extract(
			'/^ \* Version:\s+([^\s]+)$/m',
			file_get_contents( $root . '/omppm-override-phpmail-mailpoet.php' ),
			'plugin header version'
		);
		$readme_txt = $this->extract(
			'/^Stable tag:\s+([^\s]+)\s*$/m',
			file_get_contents( $root . '/readme.txt' ),
			'readme.txt stable tag'
		);
		$readme_md = $this->extract(
			'/^\*\*Stable tag:\*\*\s+([^\s]+)\s*$/m',
			file_get_contents( $root . '/README.md' ),
			'README.md stable tag'
		);

		$this->assertSame( $header, $constant, 'Admin PLUGIN_VERSION must match the plugin header.' );
		$this->assertSame( $header, $readme_txt, 'readme.txt stable tag must match the plugin header.' );
		$this->assertSame( $header, $readme_md, 'README.md stable tag must match the plugin header.' );
	}

	public function test_check_version_script_agrees(): void {
		$root = dirname( __DIR__, 2 );

		exec( 'php ' . escapeshellarg( $root . '/scripts/check-version.php' ) . ' 2>&1', $output, $exit_code );

		$this->assertSame( 0, $exit_code, implode( "\n", $output ) );

		$header = $this->extract(
			'/^ \* Version:\s+([^\s]+)$/m',
			file_get_contents( $root . '/omppm-override-phpmail-mailpoet.php' ),
			'plugin header version'
		);
		$this->assertSame( $header, trim( end( $output ) ) );
	}
}

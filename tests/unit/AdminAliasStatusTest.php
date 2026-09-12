<?php
/** @package OMPPM */
use PHPUnit\Framework\TestCase;

final class AdminAliasStatusTest extends TestCase {
    public function test_alias_status_checks_the_override_identity(): void {
        require_once dirname(__DIR__, 2) . '/includes/class-omppm-admin.php';
        $admin = (new ReflectionClass(\OMPPM\Admin\OMPPM_Admin::class))->newInstanceWithoutConstructor();
        self::assertTrue($admin->is_alias_active());
    }

    /** @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_status_contract_does_not_use_an_autoloading_existence_check(): void {
        // The isolated subprocess loads only the admin class, with a native class present.
        $file = dirname(__DIR__, 2) . '/includes/class-omppm-admin.php';
        $code = 'namespace { define("ABSPATH", "/"); } namespace MailPoet\\Mailer\\Methods { class PHPMail {} } namespace { require ' . var_export($file, true) . '; $a=(new ReflectionClass("OMPPM\\\\Admin\\\\OMPPM_Admin"))->newInstanceWithoutConstructor(); exit($a->is_alias_active() ? 1 : 0); }';
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code), $output, $status);
        self::assertSame(0, $status);
    }
}

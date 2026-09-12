<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads WordPress and MailPoet contract stubs, then the real plugin file,
 * and activates the class alias exactly as plugins_loaded would.
 *
 * @package OMPPM
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );

require_once __DIR__ . '/stubs/wp-functions.php';
require_once __DIR__ . '/stubs/mailpoet.php';

require_once dirname( __DIR__, 2 ) . '/omppm-override-phpmail-mailpoet.php';

\OMPPM\omppm_setup_alias();

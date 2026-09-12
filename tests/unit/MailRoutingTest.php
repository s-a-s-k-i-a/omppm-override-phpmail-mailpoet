<?php
/**
 * Routing contract tests for the MailPoet PHPMail override.
 *
 * @package OMPPM
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests the class alias, email-type routing, header construction,
 * recursion guard, and error mapping of MyPHPMailOverride.
 */
class MailRoutingTest extends TestCase {

	/** @var \OMPPM\MyPHPMailOverride */
	private $method;

	protected function setUp(): void {
		$GLOBALS['omppm_test_mail_log']    = array();
		$GLOBALS['omppm_test_mail_result'] = true;

		$this->method = new \OMPPM\MyPHPMailOverride(
			array(
				'from_name'  => 'Isla Studio',
				'from_email' => 'news@example.com',
			),
			array(
				'reply_to_name'  => 'Support',
				'reply_to_email' => 'support@example.com',
			),
			'bounce@example.com',
			new \MailPoet\Mailer\Methods\TestErrorMapper(),
			null
		);
	}

	protected function tearDown(): void {
		$flag = new ReflectionProperty( \OMPPM\MyPHPMailOverride::class, 'is_sending' );
		$flag->setValue( null, false );
	}

	/**
	 * Send through the override with a given email type.
	 *
	 * @param string|null $email_type Email type meta, null for none.
	 * @param array       $newsletter Newsletter payload.
	 * @param string      $subscriber Subscriber email.
	 * @return array
	 */
	private function send_with_type( $email_type, array $newsletter = array( 'subject' => 'Hello' ), $subscriber = 'subscriber@example.com' ) {
		$extra = null === $email_type ? array() : array( 'meta' => array( 'email_type' => $email_type ) );
		return $this->method->send( $newsletter, $subscriber, $extra );
	}

	public function test_alias_replaces_mailpoet_phpmail_class(): void {
		$this->assertTrue( class_exists( '\\MailPoet\\Mailer\\Methods\\PHPMail' ) );
		$reflection = new ReflectionClass( '\\MailPoet\\Mailer\\Methods\\PHPMail' );
		$this->assertSame( 'OMPPM\\MyPHPMailOverride', $reflection->getName() );
		$this->assertTrue( is_subclass_of( 'OMPPM\\MyPHPMailOverride', 'MailPoet\\Mailer\\Methods\\PHPMailerMethod' ) );
	}

	public function test_supported_static_type_routes_through_wp_mail(): void {
		$result = $this->send_with_type( 'newsletter' );

		$this->assertSame( array( 'response' => true ), $result );
		$this->assertCount( 1, $GLOBALS['omppm_test_mail_log'] );
		$this->assertCount( 0, $this->method->parentSendCalls );
		$this->assertSame( 'subscriber@example.com', $GLOBALS['omppm_test_mail_log'][0]['to'] );
		$this->assertSame( 'Hello', $GLOBALS['omppm_test_mail_log'][0]['subject'] );
	}

	public function test_mailpoet_entity_type_discovered_via_reflection_routes_through_wp_mail(): void {
		$result = $this->send_with_type( \MailPoet\Entities\NewsletterEntity::TYPE_WC_TRANSACTIONAL );

		$this->assertSame( array( 'response' => true ), $result );
		$this->assertCount( 1, $GLOBALS['omppm_test_mail_log'] );
	}

	public function test_automatic_pattern_routes_through_wp_mail(): void {
		$result = $this->send_with_type( 'automatic_woocommerce_order_completed' );

		$this->assertSame( array( 'response' => true ), $result );
		$this->assertCount( 1, $GLOBALS['omppm_test_mail_log'] );
	}

	public function test_unknown_type_falls_back_to_original_mailpoet_path(): void {
		$result = $this->send_with_type( 'totally_unknown_type' );

		$this->assertSame( 'parent', $result['via'] );
		$this->assertCount( 0, $GLOBALS['omppm_test_mail_log'] );
		$this->assertCount( 1, $this->method->parentSendCalls );
	}

	public function test_missing_email_type_defaults_to_wp_mail(): void {
		$result = $this->send_with_type( null );

		$this->assertSame( array( 'response' => true ), $result );
		$this->assertCount( 1, $GLOBALS['omppm_test_mail_log'] );
	}

	public function test_blacklisted_subscriber_is_rejected_before_sending(): void {
		$this->method->blacklist->blacklisted[] = 'subscriber@example.com';

		$result = $this->send_with_type( 'newsletter' );

		$this->assertFalse( $result['response'] );
		$this->assertSame( 'blacklisted:subscriber@example.com', $result['error'] );
		$this->assertCount( 0, $GLOBALS['omppm_test_mail_log'] );
		$this->assertCount( 0, $this->method->parentSendCalls );
	}

	public function test_headers_carry_sender_reply_to_and_html_content_type(): void {
		$this->send_with_type( 'newsletter' );

		$headers = $GLOBALS['omppm_test_mail_log'][0]['headers'];
		$this->assertContains( 'From: Isla Studio <news@example.com>', $headers );
		$this->assertContains( 'Reply-To: Support <support@example.com>', $headers );
		$this->assertContains( 'Content-Type: text/html; charset=UTF-8', $headers );
	}

	public function test_plain_text_content_type_is_preserved(): void {
		$this->method->send(
			array( 'subject' => 'Plain' ),
			'subscriber@example.com',
			array(
				'meta'         => array( 'email_type' => 'newsletter' ),
				'content_type' => 'text/plain',
			)
		);

		$headers = $GLOBALS['omppm_test_mail_log'][0]['headers'];
		$this->assertContains( 'Content-Type: text/plain; charset=UTF-8', $headers );
	}

	public function test_string_sender_and_reply_to_are_normalized(): void {
		$method = new \OMPPM\MyPHPMailOverride(
			'plain-sender@example.com',
			'plain-reply@example.com',
			'bounce@example.com',
			new \MailPoet\Mailer\Methods\TestErrorMapper(),
			null
		);

		$method->send( array( 'subject' => 'Hello' ), 'subscriber@example.com', array() );

		$headers = $GLOBALS['omppm_test_mail_log'][0]['headers'];
		$this->assertContains( 'From:  <plain-sender@example.com>', $headers );
		$this->assertContains( 'Reply-To:  <plain-reply@example.com>', $headers );
	}

	public function test_wp_mail_failure_maps_to_subscriber_error(): void {
		$GLOBALS['omppm_test_mail_result'] = false;

		$result = $this->send_with_type( 'newsletter' );

		$this->assertFalse( $result['response'] );
		$this->assertSame( 'send-failed', $result['error'] );
	}

	public function test_configuration_exception_maps_to_error_and_resets_guard(): void {
		$result = $this->send_with_type( 'newsletter', array( 'subject' => 'THROW' ) );

		$this->assertFalse( $result['response'] );
		$this->assertSame( 'exception:configure failed', $result['error'] );

		$follow_up = $this->send_with_type( 'newsletter' );
		$this->assertSame( array( 'response' => true ), $follow_up );
		$this->assertCount( 1, $GLOBALS['omppm_test_mail_log'] );
	}

	public function test_recursion_guard_delegates_to_original_path(): void {
		$flag = new ReflectionProperty( \OMPPM\MyPHPMailOverride::class, 'is_sending' );
		$flag->setValue( null, true );

		$result = $this->send_with_type( 'newsletter' );

		$this->assertSame( 'parent', $result['via'] );
		$this->assertCount( 0, $GLOBALS['omppm_test_mail_log'] );
		$this->assertCount( 1, $this->method->parentSendCalls );
	}
}

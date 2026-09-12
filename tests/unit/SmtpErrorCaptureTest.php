<?php
/** @package OMPPM */
use PHPUnit\Framework\TestCase;

class SmtpErrorCaptureTest extends TestCase {
	private function mailer() {
		$mailer = new \PHPMailer\PHPMailer\PHPMailer();
		$mailer->Mailer = 'smtp';
		$mailer->Subject = 'Synthetic';
		$mailer->recipients = array( 'reject@example.test' => true );
		return $mailer;
	}
	private function failure( $capture, $mailer, $code = 550, $enhanced = '5.1.1', $command = 'RCPT TO' ) {
		$smtp = $mailer->getSMTPInstance();
		$smtp->error = array( 'error' => $command . ' command failed', 'smtp_code' => $code, 'smtp_code_ex' => $enhanced );
		($mailer->Debugoutput)( 'SMTP ERROR: synthetic', 1 );
		$capture->mailFailed( new WP_Error( 'wp_mail_failed', 'Synthetic SMTP failure', array( 'to' => array( 'reject@example.test' ), 'subject' => 'Synthetic' ) ) );
	}
	public function test_permanent_rcpt_failure_is_subscriber_error_and_preserves_details(): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$mailer = $this->mailer();
		$capture->mailerInit( $mailer );
		$this->failure( $capture, $mailer );
		$error = $capture->getError( 'Example Name <reject@example.test>' );
		$this->assertSame( 'soft', $error->getLevel() );
		$this->assertSame( 'Synthetic SMTP failure', $error->getMessage() );
		$this->assertSame( 'Example Name <reject@example.test>', $error->getSubscriberErrors()[0]->getEmail() );
		$capture->stop();
		$this->assertSame( 0, $mailer->SMTPDebug );
		$this->assertSame( 'echo', $mailer->Debugoutput );
	}
	/** @dataProvider hardCases */
	public function test_other_failures_remain_hard( $code, $enhanced, $command ): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$mailer = $this->mailer();
		$capture->mailerInit( $mailer );
		$this->failure( $capture, $mailer, $code, $enhanced, $command );
		$this->assertSame( 'hard', $capture->getError( 'reject@example.test' )->getLevel() );
		$capture->stop();
	}
	public function hardCases(): array {
		return array( array( 450, '4.2.0', 'RCPT TO' ), array( 550, '5.7.1', 'RCPT TO' ), array( 535, '5.7.8', 'AUTH' ), array( 550, '5.1.1', 'DATA' ), array( 550, '', 'RCPT TO' ), array( 0, '', 'CONNECT' ) );
	}
	public function test_nested_mailer_init_prevents_soft_classification(): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$mailer = $this->mailer();
		$capture->mailerInit( $mailer );
		$this->failure( $capture, $mailer );
		$capture->mailerInit( $mailer );
		$this->assertSame( 'hard', $capture->getError( 'reject@example.test' )->getLevel() );
		$capture->stop();
	}
	public function test_extra_recipient_prevents_soft_classification(): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$mailer = $this->mailer();
		$capture->mailerInit( $mailer );
		$mailer->recipients['other@example.test'] = true;
		$this->failure( $capture, $mailer );
		$this->assertSame( 'hard', $capture->getError( 'reject@example.test' )->getLevel() );
		$capture->stop();
	}
	public function test_quiet_debug_is_not_forwarded_and_existing_callback_level_is_preserved(): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$mailer = $this->mailer();
		$seen = array();
		$callback = static function ( $line, $level ) use ( &$seen ) { $seen[] = $level; };
		$mailer->Debugoutput = $callback;
		$capture->mailerInit( $mailer );
		($mailer->Debugoutput)( 'potential secret', 1 );
		$this->assertSame( array(), $seen );
		$capture->stop();
		$this->assertSame( $callback, $mailer->Debugoutput );
		$mailer->SMTPDebug = 1;
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$capture->mailerInit( $mailer );
		($mailer->Debugoutput)( 'level one', 1 );
		($mailer->Debugoutput)( 'level two', 2 );
		$this->assertSame( array( 1 ), $seen );
		$capture->stop();
		$this->assertSame( 1, $mailer->SMTPDebug );
	}
	public function test_stale_smtp_error_without_current_error_event_is_not_classified(): void {
		$mailer = $this->mailer();
		$first = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$first->mailerInit( $mailer );
		$this->failure( $first, $mailer );
		$first->stop();
		$next = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$next->mailerInit( $mailer );
		$next->mailFailed( new WP_Error( 'wp_mail_failed', 'Unknown new failure', array( 'to' => array( 'reject@example.test' ), 'subject' => 'Synthetic' ) ) );
		$this->assertSame( 'hard', $next->getError( 'reject@example.test' )->getLevel() );
		$next->stop();
	}
	public function test_unrelated_failure_does_not_supply_details_and_cleanup_removes_hooks(): void {
		$before = $GLOBALS['omppm_test_actions'];
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$capture->start();
		$mailer = $this->mailer();
		do_action( 'phpmailer_init', $mailer );
		do_action( 'wp_mail_failed', new WP_Error( 'wp_mail_failed', 'Other mail failure', array( 'to' => array( 'other@example.test' ), 'subject' => 'Synthetic' ) ) );
		$this->assertNull( $capture->getError( 'reject@example.test' ) );
		$capture->stop();
		$this->assertSame( $before, $GLOBALS['omppm_test_actions'] );
	}
	public function test_no_smtp_evidence_retains_error_details_but_blocks(): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$capture->mailFailed( new WP_Error( 'wp_mail_failed', 'API transport denied request', array( 'to' => array( 'reject@example.test' ), 'subject' => 'Synthetic' ) ) );
		$error = $capture->getError( 'reject@example.test' );
		$this->assertSame( 'hard', $error->getLevel() );
		$this->assertSame( 'API transport denied request', $error->getMessage() );
	}

	public function test_envelope_changed_after_capture_cannot_use_old_recipient_evidence(): void {
		$capture = new \OMPPM\SmtpErrorCapture( 'reject@example.test', 'Synthetic' );
		$mailer = $this->mailer();
		$capture->mailerInit( $mailer );
		$this->failure( $capture, $mailer );
		$mailer->recipients = array( 'another@example.test' => true );
		$this->assertSame( 'hard', $capture->getError( 'reject@example.test' )->getLevel() );
		$capture->stop();
	}

}

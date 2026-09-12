<?php
/**
 * Per-send observation of WordPress/PHPMailer errors without replacing transport.
 *
 * @package OMPPM
 */
namespace OMPPM;

use MailPoet\Mailer\MailerError;
use MailPoet\Mailer\SubscriberError;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmtpErrorCapture {
	private string $recipient;
	private string $subject;
	private ?string $message = null;
	private bool $permanentRecipientFailure = false;
	private bool $ambiguous = false;
	private int $initCount = 0;
	private int $failureCount = 0;
	private ?PHPMailer $mailer = null;
	private ?SMTP $smtp = null;
	private $debugOutput;
	private int $debugLevel = 0;
	private $smtpDebugOutput;
	private int $smtpDebugLevel = 0;
	private $observer;

	public function __construct( string $recipient, string $subject ) {
		$this->recipient = $recipient;
		$this->subject = $subject;
	}

	public function start(): void {
		add_action( 'phpmailer_init', array( $this, 'mailerInit' ), PHP_INT_MAX );
		add_action( 'wp_mail_failed', array( $this, 'mailFailed' ), PHP_INT_MAX );
	}

	private function matchesMailer( PHPMailer $mailer ): bool {
		return 'smtp' === $mailer->Mailer
			&& $this->subject === $mailer->Subject
			&& array( strtolower( $this->recipient ) ) === array_keys( $mailer->getAllRecipientAddresses() );
	}

	public function mailerInit( $mailer ): void {
		++$this->initCount;
		// A second init may be a nested WordPress send using the shared mailer.
		// Its results cannot safely be attributed to this MailPoet subscriber.
		if ( 1 !== $this->initCount ) {
			$this->ambiguous = true;
			return;
		}
		if ( ! $mailer instanceof PHPMailer || ! $this->matchesMailer( $mailer ) ) {
			$this->ambiguous = true;
			return;
		}
		$this->mailer = $mailer;
		$this->smtp = $mailer->getSMTPInstance();
		$this->debugOutput = $mailer->Debugoutput;
		$this->debugLevel = $mailer->SMTPDebug;
		$this->smtpDebugOutput = $this->smtp->Debugoutput;
		$this->smtpDebugLevel = $this->smtp->do_debug;

		// Delegate any previously enabled diagnostics to PHPMailer's own output
		// implementation, at exactly the original level. The relay has no socket.
		$relay = new class() extends SMTP {
			public function forward( string $line, int $level ): void {
				$this->edebug( $line, $level );
			}
		};
		$relay->Debugoutput = $this->debugOutput;
		$relay->do_debug = $this->debugLevel;
		$this->observer = function ( $line, $level ) use ( $relay ): void {
			// SMTP populates structured error data immediately before this event.
			// QUIT can clear it later, so reading getError() after wp_mail is too late.
			if ( 0 === strpos( $line, 'SMTP ERROR:' ) && ! $this->ambiguous && $this->matchesMailer( $this->mailer ) ) {
				$error = $this->smtp->getError();
				$code = (int) ( $error['smtp_code'] ?? 0 );
				$this->permanentRecipientFailure = 'RCPT TO command failed' === ( $error['error'] ?? '' )
					&& $code >= 500 && $code <= 599
					// Address/mailbox-specific permanent failures only. Missing status,
					// policy, quota, authentication and DATA failures stay blocking.
					&& in_array( $error['smtp_code_ex'] ?? '', array( '5.1.1', '5.1.2', '5.1.3', '5.1.6', '5.2.1' ), true );
			}
			$relay->forward( $line, $level );
		};
		$mailer->Debugoutput = $this->observer;
		$mailer->SMTPDebug = max( 2, $this->debugLevel );
	}

	public function mailFailed( $error ): void {
		if ( ! $error instanceof \WP_Error || 'wp_mail_failed' !== $error->get_error_code() ) {
			return;
		}
		$data = $error->get_error_data();
		if ( ! is_array( $data ) || $this->subject !== ( $data['subject'] ?? null ) ) {
			$this->ambiguous = true;
			return;
		}
		$to = $data['to'] ?? array();
		$to = is_array( $to ) ? array_values( $to ) : array( $to );
		if ( array( $this->recipient ) !== $to ) {
			$this->ambiguous = true;
			return;
		}
		if ( ++$this->failureCount > 1 ) {
			$this->ambiguous = true;
		}
		// Keep details only in the returned native error, never in debug events.
		$this->message = $error->get_error_message();
	}

	public function getError( string $subscriber ): ?MailerError {
		if ( null === $this->message ) {
			return null;
		}
		$soft = $this->permanentRecipientFailure && ! $this->ambiguous
			&& $this->mailer && $this->matchesMailer( $this->mailer )
			&& $this->smtp === $this->mailer->getSMTPInstance();
		return new MailerError(
			MailerError::OPERATION_SEND,
			$soft ? MailerError::LEVEL_SOFT : MailerError::LEVEL_HARD,
			$this->message,
			null,
			array( new SubscriberError( $subscriber ) )
		);
	}

	public function stop(): void {
		remove_action( 'phpmailer_init', array( $this, 'mailerInit' ), PHP_INT_MAX );
		remove_action( 'wp_mail_failed', array( $this, 'mailFailed' ), PHP_INT_MAX );
		if ( $this->mailer && $this->mailer->Debugoutput === $this->observer ) {
			$this->mailer->Debugoutput = $this->debugOutput;
			$this->mailer->SMTPDebug = $this->debugLevel;
		}
		if ( $this->smtp && $this->smtp->Debugoutput === $this->observer ) {
			$this->smtp->Debugoutput = $this->smtpDebugOutput;
			$this->smtp->do_debug = $this->smtpDebugLevel;
		}
	}
}

<?php
/**
 * MailPoet contract stubs.
 *
 * These mirror exactly the surface the override touches: the base method
 * class it extends, the Mailer result formatting, the NewsletterEntity
 * TYPE_* constants read via reflection, and the PHPMailer message object.
 *
 * @package OMPPM
 */

namespace PHPMailer\PHPMailer {

	/**
	 * Message container stub.
	 */
	class PHPMailer {

		/** @var string */
		public $Subject = '';

		/** @var string */
		public $Body = '';

		/** @var string */
		public $ContentType = 'text/html';

		/**
		 * @param bool $exceptions Unused.
		 */
		public function __construct( $exceptions = false ) {}
	}
}

namespace MailPoet\Mailer {

	/**
	 * Result formatting stub.
	 */
	class Mailer {

		/**
		 * @param mixed $error Error payload.
		 * @return array
		 */
		public static function formatMailerErrorResult( $error ) {
			return array(
				'response' => false,
				'error'    => $error,
			);
		}

		/**
		 * @return array
		 */
		public static function formatMailerSendSuccessResult() {
			return array( 'response' => true );
		}
	}
}

namespace MailPoet\Entities {

	/**
	 * Newsletter entity stub carrying the TYPE_* constants the override
	 * discovers via reflection.
	 */
	class NewsletterEntity {
		const TYPE_AUTOMATION       = 'automation';
		const TYPE_STANDARD         = 'standard';
		const TYPE_NOTIFICATION     = 'notification';
		const TYPE_WC_TRANSACTIONAL = 'wc_transactional';
	}
}

namespace MailPoet\Mailer\Methods {

	/**
	 * Blacklist stub with a controllable list.
	 */
	class TestBlacklist {

		/** @var array */
		public $blacklisted = array();

		/**
		 * @param mixed $subscriber Subscriber.
		 * @return bool
		 */
		public function isBlacklisted( $subscriber ) {
			return in_array( $subscriber, $this->blacklisted, true );
		}
	}

	/**
	 * Error mapper stub producing distinguishable error payloads.
	 */
	class TestErrorMapper {

		/** @var array Original subscriber values received by the mapper. */
		public $subscribers = array();

		/**
		 * @param mixed $subscriber Subscriber.
		 * @return string
		 */
		public function getBlacklistError( $subscriber ) {
			$email = is_array( $subscriber ) ? ( $subscriber['email'] ?? '' ) : $subscriber;
			return 'blacklisted:' . $email;
		}

		/**
		 * @param \Exception $exception  Exception.
		 * @param mixed      $subscriber Subscriber.
		 * @return string
		 */
		public function getErrorFromException( $exception, $subscriber ) {
			$this->subscribers[] = $subscriber;
			return 'exception:' . $exception->getMessage();
		}

		/**
		 * @param mixed $subscriber Subscriber.
		 * @return string
		 */
		public function getErrorForSubscriber( $subscriber ) {
			$this->subscribers[] = $subscriber;
			return 'send-failed';
		}
	}

	/**
	 * Base method stub matching the constructor and helper surface of
	 * MailPoet's PHPMailerMethod that the override relies on.
	 */
	class PHPMailerMethod {

		/** @var mixed */
		public $sender;

		/** @var mixed */
		public $replyTo;

		/** @var mixed */
		public $returnPath;

		/** @var TestErrorMapper */
		public $errorMapper;

		/** @var TestBlacklist */
		public $blacklist;

		/** @var array */
		public $parentSendCalls = array();

		/**
		 * @param mixed $sender      Sender.
		 * @param mixed $replyTo     Reply-to.
		 * @param mixed $returnPath  Return path.
		 * @param mixed $errorMapper Error mapper.
		 * @param mixed $urlUtils    Unused.
		 */
		public function __construct( $sender, $replyTo, $returnPath, $errorMapper, $urlUtils ) {
			$this->sender      = $sender;
			$this->replyTo     = $replyTo;
			$this->returnPath  = $returnPath;
			$this->errorMapper = $errorMapper;
			$this->blacklist   = new TestBlacklist();
		}

		/**
		 * Records delegation to the original MailPoet path.
		 *
		 * @param mixed $newsletter  Newsletter.
		 * @param mixed $subscriber  Subscriber.
		 * @param array $extraParams Extra params.
		 * @return array
		 */
		public function send( $newsletter, $subscriber, $extraParams = array() ): array {
			$this->parentSendCalls[] = array( $newsletter, $subscriber, $extraParams );
			return array(
				'response' => true,
				'via'      => 'parent',
			);
		}

		/**
		 * @param mixed $newsletter  Newsletter.
		 * @param mixed $subscriber  Subscriber.
		 * @param array $extraParams Extra params.
		 * @return \PHPMailer\PHPMailer\PHPMailer
		 * @throws \Exception When the newsletter subject is THROW.
		 */
		protected function configureMailerWithMessage( $newsletter, $subscriber, $extraParams = array() ) {
			if ( isset( $newsletter['subject'] ) && 'THROW' === $newsletter['subject'] ) {
				throw new \Exception( 'configure failed' );
			}

			$mailer              = new \PHPMailer\PHPMailer\PHPMailer( true );
			$mailer->Subject     = $newsletter['subject'] ?? 'Test subject';
			$mailer->Body        = $newsletter['body'] ?? 'Test body';
			$mailer->ContentType = $extraParams['content_type'] ?? 'text/html';
			return $mailer;
		}

		/**
		 * @param mixed $subscriber Subscriber.
		 * @return array
		 */
		protected function processSubscriber( $subscriber ) {
			if ( is_string( $subscriber ) ) {
				return array( 'email' => $subscriber );
			}
			return $subscriber;
		}
	}
}

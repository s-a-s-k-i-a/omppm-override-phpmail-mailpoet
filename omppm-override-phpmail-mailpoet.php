<?php
namespace OMPPM;

/**
 * Plugin Name:       SMTP Mail Control for MailPoet
 * Plugin URI:        https://saskialund.de/
 * Description:       The missing link between MailPoet and your SMTP plugin – for reliable email delivery!
 * Version:           1.2.5
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Saskia Teichmann
 * Author URI:        https://saskialund.de
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       omppm-override-phpmail-mailpoet
 * Requires Plugins:  mailpoet
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define OMPPM debug constant
if (!defined('OMPPM_DEBUG')) {
    $debug_enabled = get_option('omppm_debug_enabled', false);
    define('OMPPM_DEBUG', $debug_enabled);
}

// Only load if MailPoet is active
if (!class_exists('MailPoet\Mailer\Mailer')) {
    if (OMPPM_DEBUG) {
        error_log('OMPPM: MailPoet is not active, plugin disabled');
    }
    return;
}

// Use statements for MailPoet classes
use MailPoet\Mailer\Methods\PHPMailerMethod as BasePHPMailerMethod;
use MailPoet\Mailer\Mailer as MailPoetMailer;
use PHPMailer\PHPMailer\PHPMailer;
use ReflectionClass;
use ReflectionException;

/**
 * Email types enum for better type safety
 */
enum EmailType: string {
    case NEWSLETTER = 'newsletter';
    case POST_NOTIFICATION = 'post_notification';
    case WELCOME_EMAIL = 'welcome_email';
    case AUTOMATIC = 'automatic';
    case SENDING_TEST = 'sending_test';
    case CONFIRMATION = 'confirmation';
    case UNSUBSCRIBE = 'unsubscribe';
    case RE_ENGAGEMENT = 're_engagement';
    case TRANSACTIONAL = 'transactional';
    case NOTIFICATION = 'notification';
    case PREVIEW = 'preview';
    case EMAIL_STATS_NOTIFICATION = 'email_stats_notification';
    case NEW_SUBSCRIBER_NOTIFICATION = 'new_subscriber_notification';
}

// Load admin interface
if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-omppm-admin.php';
    new \OMPPM\Admin\OMPPM_Admin();
}

/**
 * Wir müssen früh genug laden, damit die Originalklasse
 * \MailPoet\Mailer\Methods\PHPMail noch nicht bekannt ist.
 * This is the working approach from version 1.0.4
 */
add_action('plugins_loaded', __NAMESPACE__ . '\\omppm_setup_alias', 1);

/**
 * Debug: Log plugin loading
 */
if (OMPPM_DEBUG) {
    error_log('OMPPM: Plugin file loaded');
}

/**
 * Ersetzt die MailPoet-Klasse \MailPoet\Mailer\Methods\PHPMail
 * durch unsere Override-Klasse, bevor sie geladen wird.
 * This is the simple, working approach from version 1.0.4
 */
function omppm_setup_alias() {
    if (OMPPM_DEBUG) {
        error_log('OMPPM: omppm_setup_alias() called');
    }
    
    // Check if PHPMail class already exists (meaning our alias worked)
    if (class_exists('\\MailPoet\\Mailer\\Methods\\PHPMail', false)) {
        if (OMPPM_DEBUG) {
            error_log('OMPPM: PHPMail class already exists (our alias is active)');
        }
        return;
    }
    
    // Create the alias - simple and direct like version 1.0.4
    if (!class_exists('\\MailPoet\\Mailer\\Methods\\PHPMail', false)) {
        if (OMPPM_DEBUG) {
            error_log('OMPPM: Creating class alias for PHPMail');
        }
        
        class_alias(
            __NAMESPACE__ . '\\MyPHPMailOverride',
            '\\MailPoet\\Mailer\\Methods\\PHPMail'
        );
        
        if (OMPPM_DEBUG) {
            error_log('OMPPM: Class alias created successfully');
        }
    }
}



/**
 * Override-Klasse für MailPoet PHPMail
 * This is the simple, working approach from version 1.0.4
 */
class MyPHPMailOverride extends BasePHPMailerMethod {
    private array $supported_email_types;
    
    /**
     * Recursion protection flag
     * Prevents infinite loops when wp_mail() triggers MailPoet again
     */
    private static bool $is_sending = false;
    
    public function __construct(
        $sender,
        $replyTo,
        $returnPath,
        $errorMapper,
        $urlUtils
    ) {
        if (OMPPM_DEBUG) {
            error_log('OMPPM: MyPHPMailOverride constructor called');
        }
        
        // Initialize supported email types
        $this->supported_email_types = array_map(
            static fn(EmailType $email_type): string => $email_type->value,
            EmailType::cases()
        );
        
        // Call parent constructor first to set up properties
        parent::__construct($sender, $replyTo, $returnPath, $errorMapper, $urlUtils);
    }
    
    public function buildMailer(): PHPMailer {
        $mailer = new PHPMailer(true);
        return $mailer;
    }
    
    /**
     * Enhanced email type validation with dynamic MailPoet type detection
     * Supports all official MailPoet email types and pattern matching
     */
    private function isSupportedEmailType(string $email_type): bool {
        // Get dynamic MailPoet email types using reflection
        $mailpoetTypes = $this->getMailPoetEmailTypes();
        
        // Direct match with MailPoet email types
        if (in_array($email_type, $mailpoetTypes, true)) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: MailPoet email type matched: " . $email_type);
            }
            return true;
        }
        
        // Direct match with our supported email types
        if (in_array($email_type, $this->supported_email_types, true)) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Supported email type matched: " . $email_type);
            }
            return true;
        }
        
        // Pattern matching for automatic emails (automatic_{group}_{event})
        if (strpos($email_type, 'automatic_') === 0) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Automatic email pattern matched: " . $email_type);
            }
            return true;
        }
        
        // Pattern matching for WooCommerce automatic emails
        if (strpos($email_type, 'automatic_woocommerce_') === 0) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: WooCommerce automatic email pattern matched: " . $email_type);
            }
            return true;
        }
        
        // Pattern matching for other automatic email patterns
        if (preg_match('/^automatic_[a-zA-Z0-9_]+_[a-zA-Z0-9_]+$/', $email_type)) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Generic automatic email pattern matched: " . $email_type);
            }
            return true;
        }
        
        if (OMPPM_DEBUG) {
            error_log("OMPPM: Email type not supported: " . $email_type);
        }
        
        return false;
    }
    
    /**
     * Dynamically get all MailPoet email types using reflection
     * This ensures we always support the latest MailPoet email types
     */
    private function getMailPoetEmailTypes(): array {
        static $mailpoetTypes = null;
        
        // Cache the result to avoid repeated reflection calls
        if ($mailpoetTypes !== null) {
            return $mailpoetTypes;
        }
        
        $mailpoetTypes = [];
        
        // Check if NewsletterEntity class exists
        if (!class_exists('MailPoet\Entities\NewsletterEntity')) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: NewsletterEntity class not found");
            }
            return $mailpoetTypes;
        }
        
        try {
            $reflection = new ReflectionClass('MailPoet\Entities\NewsletterEntity');
            $constants = $reflection->getConstants();
            
            foreach ($constants as $name => $value) {
                if (strpos($name, 'TYPE_') === 0) {
                    $mailpoetTypes[] = $value;
                }
            }
            
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Found " . count($mailpoetTypes) . " MailPoet email types: " . implode(', ', $mailpoetTypes));
            }
            
        } catch (ReflectionException $e) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Reflection error: " . $e->getMessage());
            }
        }
        
        return $mailpoetTypes;
    }
    
    public function send($newsletter, $subscriber, $extraParams = []): array {
        if (OMPPM_DEBUG) {
            $email_type = $extraParams["meta"]["email_type"] ?? "unknown";
            error_log("OMPPM: send() called - Email type: " . $email_type);
        }
        
        // RECURSION PROTECTION: Prevent infinite loops
        if (self::$is_sending) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: RECURSION DETECTED! Using parent method to break the loop.");
            }
            return parent::send($newsletter, $subscriber, $extraParams);
        }
        
        if ($this->blacklist->isBlacklisted($subscriber)) {
            $error = $this->errorMapper->getBlacklistError($subscriber);
            return MailPoetMailer::formatMailerErrorResult($error);
        }
        
        try {
            $is_mailpoet_mail = true;
            
            $email_type = $extraParams["meta"]["email_type"] ?? null;
            if ($email_type) {
                $email_type = sanitize_text_field($email_type);
                
                // Enhanced email type validation with pattern matching
                $is_mailpoet_mail = $this->isSupportedEmailType($email_type);
            }
            
            if (OMPPM_DEBUG) {
                error_log("OMPPM: is_mailpoet_mail: " . ($is_mailpoet_mail ? "TRUE" : "FALSE"));
            }
            
            if (!$is_mailpoet_mail) {
                if (OMPPM_DEBUG) {
                    error_log("OMPPM: Using original MailPoet method");
                }
                return parent::send($newsletter, $subscriber, $extraParams);
            }
            
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Processing via wp_mail()");
            }
            
            // Set recursion flag before calling wp_mail()
            self::$is_sending = true;
            
            $mailer = $this->configureMailerWithMessage($newsletter, $subscriber, $extraParams);
            // Keep the original string for MailPoet error mapping and queue consumers.
            $processedSubscriber = $this->processSubscriber($subscriber);
            
            ['email' => $to] = $processedSubscriber + ['email' => ''];
            $subject = $mailer->Subject;
            $body = $mailer->Body;
            
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Sending via wp_mail() - To: " . $to . ", Subject: " . $subject);
            }
            
            $headers = [];
            
            if (!is_array($this->sender)) {
                $this->sender = [
                    "from_name" => "",
                    "from_email" => (string)$this->sender,
                ];
            }
            $sender_name = $this->sender["from_name"] ?? "";
            $sender_email = $this->sender["from_email"] ?? "";
            if ($sender_email) {
                $headers[] = "From: {$sender_name} <{$sender_email}>";
            }
            
            if (!is_array($this->replyTo)) {
                $this->replyTo = [
                    "reply_to_name" => "",
                    "reply_to_email" => (string)$this->replyTo,
                ];
            }
            $reply_to_name = $this->replyTo["reply_to_name"] ?? "";
            $reply_to_email = $this->replyTo["reply_to_email"] ?? "";
            if ($reply_to_email) {
                $headers[] = "Reply-To: {$reply_to_name} <{$reply_to_email}>";
            }
            
            $headers[] = match($mailer->ContentType) {
                "text/plain" => "Content-Type: text/plain; charset=UTF-8",
                default => "Content-Type: text/html; charset=UTF-8"
            };
            
            $result = wp_mail($to, $subject, $body, $headers);
            
            if (OMPPM_DEBUG) {
                error_log("OMPPM: wp_mail() result: " . ($result ? "SUCCESS" : "FAILED"));
            }
            
        } catch (\Exception $e) {
            if (OMPPM_DEBUG) {
                error_log("OMPPM: Exception occurred: " . $e->getMessage());
            }
            // Reset recursion flag on exception
            self::$is_sending = false;
            
            return MailPoetMailer::formatMailerErrorResult(
                $this->errorMapper->getErrorFromException($e, $subscriber)
            );
        } finally {
            // Always reset recursion flag when done
            self::$is_sending = false;
        }
        
        if ($result === true) {
            return MailPoetMailer::formatMailerSendSuccessResult();
        } else {
            $error = $this->errorMapper->getErrorForSubscriber($subscriber);
            return MailPoetMailer::formatMailerErrorResult($error);
        }
    }
}


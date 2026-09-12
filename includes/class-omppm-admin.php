<?php
/**
 * OMPPM Admin Interface
 * 
 * @package OMPPM
 * @since 1.0.11
 */

namespace OMPPM\Admin;

use ReflectionClass;
use ReflectionException;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Admin Class for OMPPM Plugin
 * 
 * Provides a modern, extensible admin interface for the OMPPM plugin
 * with debugging controls and future expansion capabilities.
 */
class OMPPM_Admin {
    
    /**
     * Plugin slug
     */
    private string $plugin_slug = 'omppm-admin';
    
    /**
     * Option name for debug setting
     */
    private string $debug_option = 'omppm_debug_enabled';
    
    /**
     * Plugin version constant
     */
    private const PLUGIN_VERSION = '1.2.5';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', $this->add_admin_menu(...));
        add_action('admin_init', $this->init_settings(...));
        add_action('admin_enqueue_scripts', $this->enqueue_admin_scripts(...));
        add_action('wp_ajax_omppm_toggle_debug', $this->ajax_toggle_debug(...));
        add_action('wp_ajax_omppm_clear_logs', $this->ajax_clear_logs(...));
        add_action('wp_ajax_omppm_send_test_email', $this->ajax_send_test_email(...));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('SMTP Mail Control for MailPoet - Debug Tools', 'omppm-override-phpmail-mailpoet'),
            __('SMTP Mail Control', 'omppm-override-phpmail-mailpoet'),
            'manage_options',
            $this->plugin_slug,
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'omppm_settings',
            $this->debug_option,
            [
                'type' => 'boolean',
                'default' => false,
                'sanitize_callback' => 'rest_sanitize_boolean'
            ]
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tools_page_' . $this->plugin_slug) {
            return;
        }
        
        wp_enqueue_script(
            'omppm-admin',
            plugin_dir_url(__FILE__) . 'js/omppm-admin.js',
            ['jquery'],
            self::PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'omppm-admin',
            plugin_dir_url(__FILE__) . 'css/omppm-admin.css',
            [],
            self::PLUGIN_VERSION
        );
        
        wp_localize_script('omppm-admin', 'omppm_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('omppm_admin_nonce'),
            'strings' => [
                'debug_enabled' => __('Debug enabled', 'omppm-override-phpmail-mailpoet'),
                'debug_disabled' => __('Debug disabled', 'omppm-override-phpmail-mailpoet'),
                'logs_cleared' => __('Logs cleared', 'omppm-override-phpmail-mailpoet'),
                'error' => __('Error occurred', 'omppm-override-phpmail-mailpoet'),
                'confirm_clear_logs' => __('Are you sure you want to delete all debug logs?', 'omppm-override-phpmail-mailpoet'),
                'log_status_updated' => __('Log status updated', 'omppm-override-phpmail-mailpoet'),
                'test_email_error' => __('Error sending test email', 'omppm-override-phpmail-mailpoet'),
                'test_email_error_details' => __('Error sending test email:', 'omppm-override-phpmail-mailpoet'),
                'mailpoet_test_page_opened' => __('MailPoet test email page opened. Send a test email via MailPoet.', 'omppm-override-phpmail-mailpoet'),
                'mailpoet_test_url' => admin_url('admin.php?page=mailpoet-settings#mta')
            ]
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $debug_enabled = get_option($this->debug_option, false);
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $log_exists = file_exists($log_file);
        $log_size = $log_exists ? size_format(filesize($log_file)) : '0 B';
        
        ?>
        <div class="wrap omppm-admin">
            <h1><?php _e('SMTP Mail Control for MailPoet - Debug Tools', 'omppm-override-phpmail-mailpoet'); ?></h1>
            
            <div class="omppm-admin-grid">
                <!-- Debug Control Panel -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('Debug Settings', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-debug-controls">
                            <div class="omppm-switch-container">
                                <label class="omppm-switch">
                                    <input type="checkbox" id="omppm-debug-toggle" <?php checked($debug_enabled); ?>>
                                    <span class="omppm-slider"></span>
                                </label>
                                <span class="omppm-switch-label">
                                    <?php _e('Enable SMTP Mail Control Debug', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                            
                            <div class="omppm-debug-status">
                                <span class="omppm-status-indicator <?php echo $debug_enabled ? 'active' : 'inactive'; ?>"></span>
                                <span class="omppm-status-text">
                                    <?php echo $debug_enabled ? 
                                        __('Debug is active', 'omppm-override-phpmail-mailpoet') : 
                                        __('Debug is inactive', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="omppm-debug-info">
                            <p><strong><?php _e('What happens during debug?', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                            <ul>
                                <li><?php _e('Detailed logs are written to debug.log', 'omppm-override-phpmail-mailpoet'); ?></li>
                                <li><?php _e('Email delivery is logged step by step', 'omppm-override-phpmail-mailpoet'); ?></li>
                                <li><?php _e('Plugin initialization is monitored', 'omppm-override-phpmail-mailpoet'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Log Management -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('Log Management', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-log-info">
                            <p><strong><?php _e('Debug Log Status:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                            <ul>
                                <li><?php _e('File:', 'omppm-override-phpmail-mailpoet'); ?> <code><?php echo $log_file; ?></code></li>
                                <li><?php _e('Exists:', 'omppm-override-phpmail-mailpoet'); ?> 
                                    <span class="<?php echo $log_exists ? 'omppm-success' : 'omppm-warning'; ?>">
                                        <?php echo $log_exists ? __('Yes', 'omppm-override-phpmail-mailpoet') : __('No', 'omppm-override-phpmail-mailpoet'); ?>
                                    </span>
                                </li>
                                <li><?php _e('Size:', 'omppm-override-phpmail-mailpoet'); ?> <code><?php echo $log_size; ?></code></li>
                            </ul>
                        </div>
                        
                        <div class="omppm-log-actions">
                            <button type="button" id="omppm-clear-logs" class="button button-secondary">
                                <?php _e('Clear Logs', 'omppm-override-phpmail-mailpoet'); ?>
                            </button>
                            <button type="button" id="omppm-refresh-logs" class="button button-primary">
                                <?php _e('Refresh Status', 'omppm-override-phpmail-mailpoet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Plugin Status -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('Plugin Status', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-status-grid">
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php _e('Plugin Version:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value"><?php echo $this->get_plugin_version(); ?></span>
                            </div>
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php _e('MailPoet Active:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value <?php echo class_exists('MailPoet\Mailer\Mailer') ? 'omppm-success' : 'omppm-error'; ?>">
                                                                            <?php echo class_exists('MailPoet\Mailer\Mailer') ? __('Yes', 'omppm-override-phpmail-mailpoet') : __('No', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php _e('Class Alias Active:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value <?php echo class_exists('\\MailPoet\\Mailer\\Methods\\PHPMail') ? 'omppm-success' : 'omppm-warning'; ?>">
                                    <?php echo class_exists('\\MailPoet\\Mailer\\Methods\\PHPMail') ? __('Yes', 'omppm-override-phpmail-mailpoet') : __('No', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php _e('Supported Email Types:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value omppm-success">
                                    <?php echo count($this->get_supported_email_types()); ?> <?php _e('types', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('Quick Actions', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=mailpoet-settings#mta'); ?>" class="button button-primary">
                                <?php _e('MailPoet Settings', 'omppm-override-phpmail-mailpoet'); ?>
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=wp-mail-smtp'); ?>" class="button button-secondary">
                                <?php _e('WP Mail SMTP', 'omppm-override-phpmail-mailpoet'); ?>
                            </a>
                            <button type="button" id="omppm-test-email" class="button button-secondary">
                                <?php _e('Send Test Email', 'omppm-override-phpmail-mailpoet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Setup Instructions -->
                <div class="omppm-card omppm-setup-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('📋 Setup Guide', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-setup-steps">
                            <div class="omppm-step">
                                <div class="omppm-step-number">1</div>
                                <div class="omppm-step-content">
                                    <h3><?php _e('Configure MailPoet Settings', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                    <p><?php _e('Go to <strong>MailPoet > Settings > Sending</strong> and select:', 'omppm-override-phpmail-mailpoet'); ?></p>
                                    <div class="omppm-step-highlight">
                                        <span class="omppm-badge omppm-badge-success">✅ Recommended</span>
                                        <strong><?php _e('Host / Web Server (Standard)', 'omppm-override-phpmail-mailpoet'); ?></strong>
                                    </div>
                                    <div class="omppm-step-note">
                                        <span class="omppm-icon">💡</span>
                                        <?php _e('This ensures that MailPoet uses the PHPMail method that our plugin overrides.', 'omppm-override-phpmail-mailpoet'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="omppm-step">
                                <div class="omppm-step-number">2</div>
                                <div class="omppm-step-content">
                                    <h3><?php _e('Configure SMTP Plugin', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                    <p><?php _e('Configure your preferred SMTP plugin (e.g., WP Mail SMTP) with your email provider settings:', 'omppm-override-phpmail-mailpoet'); ?></p>
                                    <div class="omppm-step-options">
                                        <div class="omppm-option">
                                            <span class="omppm-icon">📧</span>
                                            <strong>Gmail</strong>
                                            <span class="omppm-option-desc"><?php _e('SMTP: smtp.gmail.com, Port: 587', 'omppm-override-phpmail-mailpoet'); ?></span>
                                        </div>
                                        <div class="omppm-option">
                                            <span class="omppm-icon">📧</span>
                                            <strong>Outlook/Hotmail</strong>
                                            <span class="omppm-option-desc"><?php _e('SMTP: smtp-mail.outlook.com, Port: 587', 'omppm-override-phpmail-mailpoet'); ?></span>
                                        </div>
                                        <div class="omppm-option">
                                            <span class="omppm-icon">📧</span>
                                            <strong>Custom SMTP Server</strong>
                                            <span class="omppm-option-desc"><?php _e('Use your SMTP settings', 'omppm-override-phpmail-mailpoet'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="omppm-step">
                                <div class="omppm-step-number">3</div>
                                <div class="omppm-step-content">
                                    <h3><?php _e('Test the Configuration', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                    <p><?php _e('Send a test email via MailPoet to ensure everything works:', 'omppm-override-phpmail-mailpoet'); ?></p>
                                    <div class="omppm-step-actions">
                                        <button type="button" id="omppm-test-mailpoet" class="button button-primary">
                                            <span class="omppm-icon">📤</span>
                                            <?php _e('Send MailPoet Test Email', 'omppm-override-phpmail-mailpoet'); ?>
                                        </button>
                                        <div class="omppm-step-note">
                                            <span class="omppm-icon">🔍</span>
                                            <?php _e('Check the logs in your SMTP plugin to confirm that the email was sent via your SMTP settings.', 'omppm-override-phpmail-mailpoet'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- How It Works -->
                <div class="omppm-card omppm-info-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('🔧 How Does the Plugin Work?', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-info-grid">
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">📨</div>
                                <h4><?php _e('MailPoet Sends Email', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php _e('MailPoet prepares newsletters or test emails and attempts to send them via the PHPMail method.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                            
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">🔄</div>
                                <h4><?php _e('Plugin Overrides PHPMail', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php _e('Our plugin intercepts the PHPMail method and redirects it to the WordPress wp_mail() function.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                            
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">📤</div>
                                <h4><?php _e('SMTP Plugin Takes Over', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php _e('Your SMTP plugin (e.g., WP Mail SMTP) processes the email and sends it via your configured SMTP settings.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                            
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">✅</div>
                                <h4><?php _e('Email Successfully Sent', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php _e('The email is sent via your SMTP provider and logged in the SMTP plugin logs.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Troubleshooting -->
                <div class="omppm-card omppm-troubleshooting-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('🔍 Common Issues & Solutions', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-troubleshooting">
                            <div class="omppm-issue">
                                <div class="omppm-issue-header">
                                    <span class="omppm-icon">❌</span>
                                    <h4><?php _e('Emails are not sent via SMTP', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                </div>
                                <div class="omppm-issue-solutions">
                                    <p><strong><?php _e('Possible causes:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                                    <ul>
                                        <li><?php _e('MailPoet is set to "MailPoet Sending Service"', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php _e('SMTP plugin is not configured or disabled', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php _e('Incorrect SMTP settings', 'omppm-override-phpmail-mailpoet'); ?></li>
                                    </ul>
                                    <div class="omppm-issue-action">
                                        <a href="<?php echo admin_url('admin.php?page=mailpoet-settings#mta'); ?>" class="button button-secondary">
                                            <?php _e('Check MailPoet Settings', 'omppm-override-phpmail-mailpoet'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="omppm-issue">
                                <div class="omppm-issue-header">
                                    <span class="omppm-icon">❌</span>
                                    <h4><?php _e('Emails do not appear in SMTP logs', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                </div>
                                                                  <div class="omppm-issue-solutions">
                                    <p><strong><?php _e('Solution:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                                    <ul>
                                        <li><?php _e('Enable debug logging above', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php _e('Send a test email via MailPoet', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php _e('Check the debug.log file for SMTP Mail Control entries', 'omppm-override-phpmail-mailpoet'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="omppm-issue">
                                <div class="omppm-issue-header">
                                    <span class="omppm-icon">❌</span>
                                    <h4><?php _e('Plugin does not work after MailPoet update', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                </div>
                                <div class="omppm-issue-solutions">
                                    <p><strong><?php _e('Solution:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                                    <ul>
                                        <li><?php _e('Deactivate and reactivate the SMTP Mail Control plugin', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php _e('Check the plugin status above', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php _e('Contact support for persistent issues', 'omppm-override-phpmail-mailpoet'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Developer Info -->
                <div class="omppm-card omppm-developer-card">
                    <div class="omppm-card-header">
                        <h2><?php _e('👩‍💻 Developer', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-developer-info">
                            <div class="omppm-developer-avatar">
                                <div class="omppm-avatar-placeholder">
                                    <img src="<?php echo plugin_dir_url(__FILE__) . 'img/isla-studio-favicon.png'; ?>" alt="Saskia Teichmann | isla WordPress Studio" width="230" height="230">
                                </div>
                            </div>
                            <div class="omppm-developer-details">
                                <h3><?php _e('Saskia Teichmann', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                <p class="omppm-developer-title"><?php _e('Full Stack Web Developer for WordPress', 'omppm-override-phpmail-mailpoet'); ?></p>
                                
                                <div class="omppm-developer-company">
                                    <h4><?php _e('WordPress Studio Teichmann', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                    <p><?php _e('WordPress & WooCommerce Specialist for SMEs & Industry', 'omppm-override-phpmail-mailpoet'); ?></p>
                                </div>
                                
                                <div class="omppm-developer-contact">
                                    <div class="omppm-contact-item">
                                        <span class="omppm-icon">📧</span>
                                        <a href="mailto:hello@wp-studio.dev" target="_blank">hello@wp-studio.dev</a>
                                    </div>
                                    <div class="omppm-contact-item">
                                        <span class="omppm-icon">🐙</span>
                                        <a href="https://github.com/s-a-s-k-i-a" target="_blank">github.com/s-a-s-k-i-a</a>
                                    </div>
                                </div>
                                
                                <div class="omppm-developer-actions">
                                    <a href="https://github.com/s-a-s-k-i-a/omppm-override-phpmail-mailpoet" target="_blank" class="button button-primary">
                                        <span class="omppm-icon">📋</span>
                                        <?php _e('Issues & Contributions', 'omppm-override-phpmail-mailpoet'); ?>
                                    </a>
                                    <a href="mailto:hello@wp-studio.dev?subject=SMTP Mail Control for MailPoet - Support" target="_blank" class="button button-secondary">
                                        <span class="omppm-icon">💬</span>
                                        <?php _e('Request Support', 'omppm-override-phpmail-mailpoet'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications -->
            <div id="omppm-notifications"></div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for debug toggle
     */
    public function ajax_toggle_debug() {
        check_ajax_referer('omppm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission', 'omppm-override-phpmail-mailpoet'));
        }
        
        $debug_enabled = isset($_POST['debug_enabled']) ? rest_sanitize_boolean($_POST['debug_enabled']) : false;
        update_option($this->debug_option, $debug_enabled);
        
        wp_send_json_success([
            'debug_enabled' => $debug_enabled,
            'message' => $debug_enabled ? 
                __('Debug enabled', 'omppm-override-phpmail-mailpoet') : 
                __('Debug disabled', 'omppm-override-phpmail-mailpoet')
        ]);
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('omppm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission', 'omppm-override-phpmail-mailpoet'));
        }
        
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $success = false;
        
        if (file_exists($log_file) && is_writable($log_file)) {
            $success = file_put_contents($log_file, '') !== false;
        }
        
        wp_send_json_success([
            'success' => $success,
            'message' => $success ? 
                __('Logs successfully cleared', 'omppm-override-phpmail-mailpoet') : 
                __('Error clearing logs', 'omppm-override-phpmail-mailpoet')
        ]);
    }
    
    /**
     * AJAX handler for sending test email
     */
    public function ajax_send_test_email() {
        check_ajax_referer('omppm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('No permission', 'omppm-override-phpmail-mailpoet'));
        }
        
        // Get admin email
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        // Prepare email
        $to = $admin_email;
        $subject = sprintf(__('[%s] SMTP Mail Control for MailPoet - Test Email', 'omppm-override-phpmail-mailpoet'), $site_name);
        $message = sprintf(
            __("This is a test email from the SMTP Mail Control Plugin.\n\n" .
               "Plugin: SMTP Mail Control for MailPoet\n" .
               "Version: %s\n" .
               "Timestamp: %s\n\n" .
               "If you receive this email, the plugin is working correctly and forwarding emails via wp_mail().\n\n" .
               "Check the logs in your SMTP plugin to confirm that the email was sent via your SMTP settings.", 'omppm-override-phpmail-mailpoet'),
            $this->get_plugin_version(),
            current_time('Y-m-d H:i:s')
        );
        
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . $admin_email . '>'
        ];
        
        // Send email
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            wp_send_json_success([
                'success' => true,
                'message' => sprintf(
                    __('Test email successfully sent to %s! Check your email inbox and SMTP logs.', 'omppm-override-phpmail-mailpoet'),
                    $admin_email
                ),
                'email' => $admin_email
            ]);
        } else {
            wp_send_json_error([
                'success' => false,
                'message' => __('Error sending test email. Check your SMTP settings.', 'omppm-override-phpmail-mailpoet')
            ]);
        }
    }
    
    /**
     * Get plugin version
     */
    private function get_plugin_version(): string {
        $plugin_data = get_plugin_data(plugin_dir_path(dirname(__FILE__)) . 'omppm-override-phpmail-mailpoet.php');
        return $plugin_data['Version'] ?? '1.0.11';
    }
    
    /**
     * Get supported email types for display
     */
    private function get_supported_email_types(): array {
        $emailTypes = [
            'newsletter',
            'post_notification',
            'welcome_email',
            'automatic',
            'sending_test',
            'confirmation',
            'unsubscribe',
            're_engagement',
            'transactional',
            'notification',
            'preview',
            'email_stats_notification',
            'new_subscriber_notification',
            'automatic_woocommerce_*',
            'automatic_*_*'
        ];
        
        // Add dynamic MailPoet email types
        $mailpoetTypes = $this->get_mailpoet_email_types();
        $emailTypes = array_merge($emailTypes, $mailpoetTypes);
        
        return array_unique($emailTypes);
    }
    
    /**
     * Get MailPoet email types dynamically
     */
    private function get_mailpoet_email_types(): array {
        if (!class_exists('MailPoet\Entities\NewsletterEntity')) {
            return [];
        }
        
        try {
            $reflection = new ReflectionClass('MailPoet\Entities\NewsletterEntity');
            $constants = $reflection->getConstants();
            
            $mailpoetTypes = [];
            foreach ($constants as $name => $value) {
                if (strpos($name, 'TYPE_') === 0) {
                    $mailpoetTypes[] = $value;
                }
            }
            
            return $mailpoetTypes;
            
        } catch (ReflectionException $e) {
            return [];
        }
    }
} 
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
            esc_html__('SMTP Mail Control for MailPoet - Debug Tools', 'omppm-override-phpmail-mailpoet'),
            esc_html__('SMTP Mail Control', 'omppm-override-phpmail-mailpoet'),
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
                'debug_enabled' => esc_html__('Debug enabled', 'omppm-override-phpmail-mailpoet'),
                'debug_disabled' => esc_html__('Debug disabled', 'omppm-override-phpmail-mailpoet'),
                'logs_cleared' => esc_html__('Logs cleared', 'omppm-override-phpmail-mailpoet'),
                'error' => esc_html__('Error occurred', 'omppm-override-phpmail-mailpoet'),
                'confirm_clear_logs' => esc_html__('Clear the stored SMTP Mail Control diagnostic events?', 'omppm-override-phpmail-mailpoet'),
                'log_status_updated' => esc_html__('Log status updated', 'omppm-override-phpmail-mailpoet'),
                'test_email_error' => esc_html__('Error sending test email', 'omppm-override-phpmail-mailpoet'),
                'test_email_error_details' => esc_html__('Error sending test email:', 'omppm-override-phpmail-mailpoet'),
                'mailpoet_test_page_opened' => esc_html__('MailPoet test email page opened. Send a test email via MailPoet.', 'omppm-override-phpmail-mailpoet'),
                'mailpoet_test_url' => admin_url('admin.php?page=mailpoet-settings#mta')
            ]
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $debug_enabled = get_option($this->debug_option, false);
        $events = get_option('omppm_debug_events', []);
        $events = is_array($events) ? $events : [];
        
        ?>
        <div class="wrap omppm-admin">
            <h1><?php esc_html_e('SMTP Mail Control for MailPoet - Debug Tools', 'omppm-override-phpmail-mailpoet'); ?></h1>
            
            <div class="omppm-admin-grid">
                <!-- Debug Control Panel -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('Debug Settings', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-debug-controls">
                            <div class="omppm-switch-container">
                                <label class="omppm-switch">
                                    <input type="checkbox" id="omppm-debug-toggle" <?php checked($debug_enabled); ?>>
                                    <span class="omppm-slider"></span>
                                </label>
                                <span class="omppm-switch-label">
                                    <?php esc_html_e('Enable SMTP Mail Control Debug', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                            
                            <div class="omppm-debug-status">
                                <span class="omppm-status-indicator <?php echo $debug_enabled ? 'active' : 'inactive'; ?>"></span>
                                <span class="omppm-status-text">
                                    <?php echo $debug_enabled ? 
                                        esc_html__('Debug is active', 'omppm-override-phpmail-mailpoet') : 
                                        esc_html__('Debug is inactive', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="omppm-debug-info">
                            <p><strong><?php esc_html_e('What happens during debug?', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e('Diagnostic event codes are stored privately in the WordPress database', 'omppm-override-phpmail-mailpoet'); ?></li>
                                <li><?php esc_html_e('No recipient, message or SMTP error details are stored', 'omppm-override-phpmail-mailpoet'); ?></li>
                                <li><?php esc_html_e('Plugin initialization is monitored', 'omppm-override-phpmail-mailpoet'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Log Management -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('Log Management', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-log-info">
                            <p><strong><?php esc_html_e('Debug Log Status:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                            <p><?php esc_html_e('The latest 100 diagnostic events are stored privately in the WordPress database. No email addresses, subjects, message bodies or SMTP error text are stored.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            <p><?php esc_html_e('Stored events:', 'omppm-override-phpmail-mailpoet'); ?> <?php echo count($events); ?></p>
                            <pre><?php foreach ($events as $event) {
                                if (is_array($event)) {
                                    echo esc_html(($event['time'] ?? '') . ' UTC ' . ($event['event'] ?? '') . "\n");
                                }
                            } ?></pre>
                        </div>
                        
                        <div class="omppm-log-actions">
                            <button type="button" id="omppm-clear-logs" class="button button-secondary">
                                <?php esc_html_e('Clear Logs', 'omppm-override-phpmail-mailpoet'); ?>
                            </button>
                            <button type="button" id="omppm-refresh-logs" class="button button-primary">
                                <?php esc_html_e('Refresh Status', 'omppm-override-phpmail-mailpoet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Plugin Status -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('Plugin Status', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-status-grid">
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php esc_html_e('Plugin Version:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value"><?php echo esc_html($this->get_plugin_version()); ?></span>
                            </div>
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php esc_html_e('MailPoet Active:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value <?php echo class_exists('MailPoet\Mailer\Mailer') ? 'omppm-success' : 'omppm-error'; ?>">
                                                                            <?php echo class_exists('MailPoet\Mailer\Mailer') ? esc_html__('Yes', 'omppm-override-phpmail-mailpoet') : esc_html__('No', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php esc_html_e('Class Alias Active:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value <?php echo $this->is_alias_active() ? 'omppm-success' : 'omppm-warning'; ?>">
                                    <?php echo $this->is_alias_active() ? esc_html__('Yes', 'omppm-override-phpmail-mailpoet') : esc_html__('No', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                            <div class="omppm-status-item">
                                <span class="omppm-status-label"><?php esc_html_e('Supported Email Types:', 'omppm-override-phpmail-mailpoet'); ?></span>
                                <span class="omppm-status-value omppm-success">
                                    <?php echo count($this->get_supported_email_types()); ?> <?php esc_html_e('types', 'omppm-override-phpmail-mailpoet'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="omppm-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('Quick Actions', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-quick-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=mailpoet-settings#mta')); ?>" class="button button-primary">
                                <?php esc_html_e('MailPoet Settings', 'omppm-override-phpmail-mailpoet'); ?>
                            </a>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-smtp')); ?>" class="button button-secondary">
                                <?php esc_html_e('WP Mail SMTP', 'omppm-override-phpmail-mailpoet'); ?>
                            </a>
                            <button type="button" id="omppm-test-email" class="button button-secondary">
                                <?php esc_html_e('Send Test Email', 'omppm-override-phpmail-mailpoet'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Setup Instructions -->
                <div class="omppm-card omppm-setup-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('📋 Setup Guide', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-setup-steps">
                            <div class="omppm-step">
                                <div class="omppm-step-number">1</div>
                                <div class="omppm-step-content">
                                    <h3><?php esc_html_e('Configure MailPoet Settings', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                    <p><?php echo wp_kses_post(__('Go to <strong>MailPoet > Settings > Sending</strong> and select:', 'omppm-override-phpmail-mailpoet')); ?></p>
                                    <div class="omppm-step-highlight">
                                        <span class="omppm-badge omppm-badge-success">✅ Recommended</span>
                                        <strong><?php esc_html_e('Host / Web Server (Standard)', 'omppm-override-phpmail-mailpoet'); ?></strong>
                                    </div>
                                    <div class="omppm-step-note">
                                        <span class="omppm-icon">💡</span>
                                        <?php esc_html_e('This ensures that MailPoet uses the PHPMail method that our plugin overrides.', 'omppm-override-phpmail-mailpoet'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="omppm-step">
                                <div class="omppm-step-number">2</div>
                                <div class="omppm-step-content">
                                    <h3><?php esc_html_e('Configure SMTP Plugin', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                    <p><?php esc_html_e('Configure your preferred SMTP plugin (e.g., WP Mail SMTP) with your email provider settings:', 'omppm-override-phpmail-mailpoet'); ?></p>
                                    <div class="omppm-step-options">
                                        <div class="omppm-option">
                                            <span class="omppm-icon">📧</span>
                                            <strong>Gmail</strong>
                                            <span class="omppm-option-desc"><?php esc_html_e('SMTP: smtp.gmail.com, Port: 587', 'omppm-override-phpmail-mailpoet'); ?></span>
                                        </div>
                                        <div class="omppm-option">
                                            <span class="omppm-icon">📧</span>
                                            <strong>Outlook/Hotmail</strong>
                                            <span class="omppm-option-desc"><?php esc_html_e('SMTP: smtp-mail.outlook.com, Port: 587', 'omppm-override-phpmail-mailpoet'); ?></span>
                                        </div>
                                        <div class="omppm-option">
                                            <span class="omppm-icon">📧</span>
                                            <strong>Custom SMTP Server</strong>
                                            <span class="omppm-option-desc"><?php esc_html_e('Use your SMTP settings', 'omppm-override-phpmail-mailpoet'); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="omppm-step">
                                <div class="omppm-step-number">3</div>
                                <div class="omppm-step-content">
                                    <h3><?php esc_html_e('Test the Configuration', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                    <p><?php esc_html_e('Send a test email via MailPoet to ensure everything works:', 'omppm-override-phpmail-mailpoet'); ?></p>
                                    <div class="omppm-step-actions">
                                        <button type="button" id="omppm-test-mailpoet" class="button button-primary">
                                            <span class="omppm-icon">📤</span>
                                            <?php esc_html_e('Send MailPoet Test Email', 'omppm-override-phpmail-mailpoet'); ?>
                                        </button>
                                        <div class="omppm-step-note">
                                            <span class="omppm-icon">🔍</span>
                                            <?php esc_html_e('Check the logs in your SMTP plugin to confirm that the email was sent via your SMTP settings.', 'omppm-override-phpmail-mailpoet'); ?>
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
                        <h2><?php esc_html_e('🔧 How Does the Plugin Work?', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-info-grid">
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">📨</div>
                                <h4><?php esc_html_e('MailPoet Sends Email', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php esc_html_e('MailPoet prepares newsletters or test emails and attempts to send them via the PHPMail method.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                            
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">🔄</div>
                                <h4><?php esc_html_e('Plugin Overrides PHPMail', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php esc_html_e('Our plugin intercepts the PHPMail method and redirects it to the WordPress wp_mail() function.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                            
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">📤</div>
                                <h4><?php esc_html_e('SMTP Plugin Takes Over', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php esc_html_e('Your SMTP plugin (e.g., WP Mail SMTP) processes the email and sends it via your configured SMTP settings.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                            
                            <div class="omppm-info-item">
                                <div class="omppm-info-icon">✅</div>
                                <h4><?php esc_html_e('Email Successfully Sent', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                <p><?php esc_html_e('The email is sent via your SMTP provider and logged in the SMTP plugin logs.', 'omppm-override-phpmail-mailpoet'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Troubleshooting -->
                <div class="omppm-card omppm-troubleshooting-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('🔍 Common Issues & Solutions', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-troubleshooting">
                            <div class="omppm-issue">
                                <div class="omppm-issue-header">
                                    <span class="omppm-icon">❌</span>
                                    <h4><?php esc_html_e('Emails are not sent via SMTP', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                </div>
                                <div class="omppm-issue-solutions">
                                    <p><strong><?php esc_html_e('Possible causes:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                                    <ul>
                                        <li><?php esc_html_e('MailPoet is set to "MailPoet Sending Service"', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php esc_html_e('SMTP plugin is not configured or disabled', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php esc_html_e('Incorrect SMTP settings', 'omppm-override-phpmail-mailpoet'); ?></li>
                                    </ul>
                                    <div class="omppm-issue-action">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=mailpoet-settings#mta')); ?>" class="button button-secondary">
                                            <?php esc_html_e('Check MailPoet Settings', 'omppm-override-phpmail-mailpoet'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="omppm-issue">
                                <div class="omppm-issue-header">
                                    <span class="omppm-icon">❌</span>
                                    <h4><?php esc_html_e('Emails do not appear in SMTP logs', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                </div>
                                                                  <div class="omppm-issue-solutions">
                                    <p><strong><?php esc_html_e('Solution:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                                    <ul>
                                        <li><?php esc_html_e('Enable debug logging above', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php esc_html_e('Send a test email via MailPoet', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php esc_html_e('Review the diagnostic events in Log Management above', 'omppm-override-phpmail-mailpoet'); ?></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="omppm-issue">
                                <div class="omppm-issue-header">
                                    <span class="omppm-icon">❌</span>
                                    <h4><?php esc_html_e('Plugin does not work after MailPoet update', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                </div>
                                <div class="omppm-issue-solutions">
                                    <p><strong><?php esc_html_e('Solution:', 'omppm-override-phpmail-mailpoet'); ?></strong></p>
                                    <ul>
                                        <li><?php esc_html_e('Deactivate and reactivate the SMTP Mail Control plugin', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php esc_html_e('Check the plugin status above', 'omppm-override-phpmail-mailpoet'); ?></li>
                                        <li><?php esc_html_e('Contact support for persistent issues', 'omppm-override-phpmail-mailpoet'); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Developer Info -->
                <div class="omppm-card omppm-developer-card">
                    <div class="omppm-card-header">
                        <h2><?php esc_html_e('👩‍💻 Developer', 'omppm-override-phpmail-mailpoet'); ?></h2>
                    </div>
                    <div class="omppm-card-body">
                        <div class="omppm-developer-info">
                            <div class="omppm-developer-avatar">
                                <div class="omppm-avatar-placeholder">
                                    <img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'img/isla-studio-favicon.png'); ?>" alt="Saskia Teichmann | isla WordPress Studio" width="230" height="230">
                                </div>
                            </div>
                            <div class="omppm-developer-details">
                                <h3><?php esc_html_e('Saskia Teichmann', 'omppm-override-phpmail-mailpoet'); ?></h3>
                                <p class="omppm-developer-title"><?php esc_html_e('Full Stack Web Developer for WordPress', 'omppm-override-phpmail-mailpoet'); ?></p>
                                
                                <div class="omppm-developer-company">
                                    <h4><?php esc_html_e('WordPress Studio Teichmann', 'omppm-override-phpmail-mailpoet'); ?></h4>
                                    <p><?php esc_html_e('WordPress & WooCommerce Specialist for SMEs & Industry', 'omppm-override-phpmail-mailpoet'); ?></p>
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
                                        <?php esc_html_e('Issues & Contributions', 'omppm-override-phpmail-mailpoet'); ?>
                                    </a>
                                    <a href="mailto:hello@wp-studio.dev?subject=SMTP Mail Control for MailPoet - Support" target="_blank" class="button button-secondary">
                                        <span class="omppm-icon">💬</span>
                                        <?php esc_html_e('Request Support', 'omppm-override-phpmail-mailpoet'); ?>
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
            wp_die(esc_html__('No permission', 'omppm-override-phpmail-mailpoet'));
        }
        
        $debug_enabled = isset($_POST['debug_enabled']) ? rest_sanitize_boolean(wp_unslash($_POST['debug_enabled'])) : false;
        update_option($this->debug_option, $debug_enabled);
        
        wp_send_json_success([
            'debug_enabled' => $debug_enabled,
            'message' => $debug_enabled ? 
                esc_html__('Debug enabled', 'omppm-override-phpmail-mailpoet') : 
                esc_html__('Debug disabled', 'omppm-override-phpmail-mailpoet')
        ]);
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('omppm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permission', 'omppm-override-phpmail-mailpoet'));
        }
        
        delete_option('omppm_debug_events');
        $success = get_option('omppm_debug_events', false) === false;

        wp_send_json_success([
            'success' => $success,
            'message' => $success ? 
                esc_html__('Logs successfully cleared', 'omppm-override-phpmail-mailpoet') : 
                esc_html__('Error clearing logs', 'omppm-override-phpmail-mailpoet')
        ]);
    }
    
    /**
     * AJAX handler for sending test email
     */
    public function ajax_send_test_email() {
        check_ajax_referer('omppm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No permission', 'omppm-override-phpmail-mailpoet'));
        }
        
        // Get admin email
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        // Prepare email
        $to = $admin_email;
        // translators: %s: Site name.
        $subject = sprintf(__('[%s] SMTP Mail Control for MailPoet - Test Email', 'omppm-override-phpmail-mailpoet'), $site_name);
        $message = sprintf(
            // translators: 1: Plugin version, 2: Site-local timestamp.
            __("This is a WordPress system-mail test from SMTP Mail Control for MailPoet.\n\nPlugin version: %1\$s\nTimestamp: %2\$s\n\nReceiving this message verifies the WordPress mail path. Test MailPoet separately using its Sending settings and verify SMTP acceptance in your SMTP provider or test catcher.", 'omppm-override-phpmail-mailpoet'),
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
                    // translators: %s: Administrator email address.
                    __('Test email successfully sent to %s! Check your email inbox and SMTP logs.', 'omppm-override-phpmail-mailpoet'),
                    $admin_email
                ),
                'email' => $admin_email
            ]);
        } else {
            wp_send_json_error([
                'success' => false,
                'message' => esc_html__('Error sending test email. Check your SMTP settings.', 'omppm-override-phpmail-mailpoet')
            ]);
        }
    }
    
    /** Verify identity without triggering the native class autoloader. */
    public function is_alias_active(): bool {
        $class = 'MailPoet\\Mailer\\Methods\\PHPMail';
        return class_exists($class, false)
            && (new ReflectionClass($class))->getName() === 'OMPPM\\MyPHPMailOverride';
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
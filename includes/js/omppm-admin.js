/**
 * OMPPM Admin JavaScript
 * Interactive functionality for the OMPPM admin interface
 */

(function($) {
    'use strict';

    // Main OMPPM Admin object
    const OMPPMAdmin = {
        
        /**
         * Initialize the admin interface
         */
        init: function() {
            this.bindEvents();
            this.initNotifications();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Debug toggle
            $('#omppm-debug-toggle').on('change', this.handleDebugToggle.bind(this));
            
            // Clear logs
            $('#omppm-clear-logs').on('click', this.handleClearLogs.bind(this));
            
            // Refresh logs
            $('#omppm-refresh-logs').on('click', this.handleRefreshLogs.bind(this));
            
            // Test email
            $('#omppm-test-email').on('click', this.handleTestEmail.bind(this));
            
            // MailPoet test email
            $('#omppm-test-mailpoet').on('click', this.handleMailPoetTestEmail.bind(this));
        },

        /**
         * Handle debug toggle
         */
        handleDebugToggle: function(e) {
            const isEnabled = e.target.checked;
            const $button = $(e.target);
            const $container = $button.closest('.omppm-switch-container');
            
            // Add loading state
            $container.addClass('omppm-loading');
            
            $.ajax({
                url: omppm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'omppm_toggle_debug',
                    debug_enabled: isEnabled,
                    nonce: omppm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        OMPPMAdmin.showNotification(
                            response.data.message,
                            'success'
                        );
                        
                        // Update status indicator
                        const $statusIndicator = $('.omppm-status-indicator');
                        const $statusText = $('.omppm-status-text');
                        
                        if (isEnabled) {
                            $statusIndicator.removeClass('inactive').addClass('active');
                            $statusText.text(omppm_ajax.strings.debug_enabled);
                        } else {
                            $statusIndicator.removeClass('active').addClass('inactive');
                            $statusText.text(omppm_ajax.strings.debug_disabled);
                        }
                    } else {
                        OMPPMAdmin.showNotification(
                            omppm_ajax.strings.error,
                            'error'
                        );
                        // Revert toggle
                        e.target.checked = !isEnabled;
                    }
                },
                error: function() {
                    OMPPMAdmin.showNotification(
                        omppm_ajax.strings.error,
                        'error'
                    );
                    // Revert toggle
                    e.target.checked = !isEnabled;
                },
                complete: function() {
                    $container.removeClass('omppm-loading');
                }
            });
        },

        /**
         * Handle clear logs
         */
        handleClearLogs: function(e) {
            const $button = $(e.target);
            
            if (!confirm(omppm_ajax.strings.confirm_clear_logs)) {
                return;
            }
            
            // Add loading state
            $button.addClass('omppm-loading');
            
            $.ajax({
                url: omppm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'omppm_clear_logs',
                    nonce: omppm_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.success) {
                        OMPPMAdmin.showNotification(
                            response.data.message,
                            'success'
                        );
                        
                        // Update log info
                        OMPPMAdmin.updateLogInfo();
                    } else {
                        OMPPMAdmin.showNotification(
                            response.data.message || omppm_ajax.strings.error,
                            'error'
                        );
                    }
                },
                error: function() {
                    OMPPMAdmin.showNotification(
                        omppm_ajax.strings.error,
                        'error'
                    );
                },
                complete: function() {
                    $button.removeClass('omppm-loading');
                }
            });
        },

        /**
         * Handle refresh logs
         */
        handleRefreshLogs: function(e) {
            const $button = $(e.target);
            
            // Add loading state
            $button.addClass('omppm-loading');
            
            // Update log info
            OMPPMAdmin.updateLogInfo();
            
            setTimeout(function() {
                $button.removeClass('omppm-loading');
                OMPPMAdmin.showNotification(
                    omppm_ajax.strings.log_status_updated,
                    'success'
                );
            }, 500);
        },

        /**
         * Handle test email
         */
        handleTestEmail: function(e) {
            const $button = $(e.target);
            
            // Add loading state
            $button.addClass('omppm-loading');
            
            // Send test email via AJAX
            $.ajax({
                url: omppm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'omppm_send_test_email',
                    nonce: omppm_ajax.nonce
                },
                success: function(response) {
                    if (response.success && response.data.success) {
                        OMPPMAdmin.showNotification(
                            response.data.message,
                            'success'
                        );
                    } else {
                        OMPPMAdmin.showNotification(
                            response.data.message || omppm_ajax.strings.test_email_error,
                            'error'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    OMPPMAdmin.showNotification(
                        omppm_ajax.strings.test_email_error_details + ' ' + error,
                        'error'
                    );
                },
                complete: function() {
                    $button.removeClass('omppm-loading');
                }
            });
        },

        /**
         * Handle MailPoet test email
         */
        handleMailPoetTestEmail: function(e) {
            const $button = $(e.target);
            
            // Add loading state
            $button.addClass('omppm-loading');
            
            // Redirect to MailPoet test email page
            setTimeout(function() {
                window.open(omppm_ajax.strings.mailpoet_test_url || '/wp-admin/admin.php?page=mailpoet-settings#mta', '_blank');
                $button.removeClass('omppm-loading');
                OMPPMAdmin.showNotification(
                    omppm_ajax.strings.mailpoet_test_page_opened,
                    'success'
                );
            }, 500);
        },

        /**
         * Update log information
         */
        updateLogInfo: function() {
            // This would typically fetch updated log info via AJAX
            // For now, we'll just refresh the page to show updated info
            location.reload();
        },

        /**
         * Initialize notification system
         */
        initNotifications: function() {
            // Create notification container if it doesn't exist
            if ($('#omppm-notifications').length === 0) {
                $('body').append('<div id="omppm-notifications"></div>');
            }
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'success') {
            const $container = $('#omppm-notifications');
            const notificationId = 'omppm-notification-' + Date.now();
            
            const $notification = $(`
                <div class="omppm-notification ${type}" id="${notificationId}">
                    <button class="omppm-notification-close">&times;</button>
                    <div class="omppm-notification-content">${message}</div>
                </div>
            `);
            
            $container.append($notification);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                OMPPMAdmin.removeNotification(notificationId);
            }, 5000);
            
            // Manual close
            $notification.find('.omppm-notification-close').on('click', function() {
                OMPPMAdmin.removeNotification(notificationId);
            });
        },

        /**
         * Remove notification
         */
        removeNotification: function(notificationId) {
            const $notification = $('#' + notificationId);
            if ($notification.length) {
                $notification.addClass('removing');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }
        },

        /**
         * Utility function to format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Utility function to debounce AJAX calls
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        OMPPMAdmin.init();
    });

    // Make OMPPMAdmin available globally for debugging
    window.OMPPMAdmin = OMPPMAdmin;

})(jQuery); 
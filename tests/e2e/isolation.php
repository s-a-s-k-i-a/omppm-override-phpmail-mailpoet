<?php

// Install as an MU plugin before the first boot of this disposable test site.
add_filter('pre_http_request', static function () {
    return new WP_Error('isolated_e2e', 'External HTTP blocked');
}, PHP_INT_MAX);
add_action('phpmailer_init', static function ($mailer) {
    if ($mailer->Mailer !== 'smtp' || $mailer->Host !== '127.0.0.1' || !in_array((int)$mailer->Port, [25281,25282,25283,25284], true)) {
        throw new Exception('E2E refuses non-loopback SMTP');
    }
    foreach ($mailer->getAllRecipientAddresses() as $email => $unused) {
        if (!str_ends_with($email, '.test')) {
            throw new Exception('E2E refuses non-synthetic recipients');
        }
    }
    $mailer->Timeout = 1;
    $mailer->Timelimit = 1;
}, PHP_INT_MAX);

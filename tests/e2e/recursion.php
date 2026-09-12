<?php

$c = MailPoet\DI\ContainerWrapper::getInstance();
$s = $c->get(MailPoet\Settings\SettingsController::class);
$s->set('send_transactional_emails', true);
(new MailPoet\Mailer\WordPress\WordpressMailerReplacer($c->get(MailPoet\Mailer\MailerFactory::class), new MailPoet\Mailer\MetaInfo(), $s, $c->get(MailPoet\Subscribers\SubscribersRepository::class)))->replaceWordPressMailer();
global $phpmailer;
if (!$phpmailer instanceof MailPoet\Mailer\WordPress\WordPressMailer) {
    throw new RuntimeException('Real MailPoet transactional mailer absent');
}
$calls = 0;
add_action('phpmailer_init', function () use (&$calls) {
    $calls++;
    if ($calls > 4) {
        throw new RuntimeException('Unbounded transactional recursion');
    }
}, PHP_INT_MAX);
function captureCount(): int
{
    $count = 0;
    foreach (file(getenv('OMPPM_E2E_SMTP_LOG')) as $line) {
        $event = json_decode($line, true);
        $count += count($event['accepted'] ?? []);
    }return $count;
}
$before = captureCount();
$result = retrieve_password('smoke');
if (captureCount() !== $before + 1) {
    throw new RuntimeException('Transactional reset lost or duplicated SMTP acceptance');
}
$s->set('send_transactional_emails', false);
if ($result !== true || $calls < 2) {
    throw new RuntimeException('Transactional password reset did not re-enter wp_mail');
}
echo "PASS real transactional password reset re-entry: $calls wp_mail calls, bounded native fallback\n";

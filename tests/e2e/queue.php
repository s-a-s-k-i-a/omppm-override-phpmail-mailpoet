<?php

/** Real persisted queue processing; invoke once per scenario and once per retry in separate WP-CLI processes. */

use MailPoet\DI\ContainerWrapper;
use MailPoet\Entities\{NewsletterEntity,ScheduledTaskEntity,SendingQueueEntity,SubscriberEntity,ScheduledTaskSubscriberEntity,SegmentEntity,SubscriberSegmentEntity,NewsletterSegmentEntity};
use MailPoet\Mailer\MailerLog;
use MailPoet\Cron\Workers\SendingQueue\SendingQueue;
use MailPoetVendor\Doctrine\ORM\EntityManager;

function check($ok, $message)
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}
$scenario = getenv('OMPPM_E2E_SCENARIO') ?: 'permanent';
$phase = getenv('OMPPM_E2E_PHASE') ?: 'initial';
$stateFile = getenv('OMPPM_E2E_STATE');
check($stateFile && defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'Disposable state/cron isolation required');
$c = ContainerWrapper::getInstance();
$em = $c->get(EntityManager::class);
$s = $c->get(MailPoet\Settings\SettingsController::class);
$s->set('mta', ['method' => 'PHPMail','frequency' => ['emails' => 1000,'interval' => 60]]);
$s->set('sender', ['address' => 'sender@example.test','name' => 'Synthetic E2E']);
$s->set('reply_to', ['address' => 'sender@example.test','name' => 'Synthetic E2E']);
$port = $phase === 'initial' ? (['auth' => 25282,'timeout' => 25283,'connect' => 25284][$scenario] ?? 25281) : 25281;
add_action('phpmailer_init', static function ($m) use ($port) {
    $m->isSMTP();
    $m->Host = '127.0.0.1';
    $m->Port = $port;
    $m->SMTPAuth = $port === 25282;
    $m->Username = 'synthetic';
    $m->Password = 'synthetic';
    $m->SMTPAutoTLS = false;
    $m->SMTPSecure = '';
}, 999);
MailerLog::resetMailerLog();
if ($phase === 'initial') {
    $segment = new SegmentEntity('Synthetic E2E ' . uniqid(), 'default', 'Disposable synthetic list');
    $em->persist($segment);
    $n = new NewsletterEntity();
    $n->setType('standard');
    $n->setSubject('Synthetic ' . $scenario);
    $n->setSenderAddress('sender@example.test');
    $n->setSenderName('Synthetic');
    $n->setStatus('sending');
    $em->persist($n);
    $link = new NewsletterSegmentEntity($n, $segment);
    $em->persist($link);
    $n->getNewsletterSegments()->add($link);
    $t = new ScheduledTaskEntity();
    $t->setType('sending');
    $t->setStatus(in_array($scenario, ['full','cold'], true) ? null : 'cli');
    $em->persist($t);
    $q = new SendingQueueEntity();
    $q->setTask($t);
    $t->setSendingQueue($q);
    $q->setNewsletter($n);
    $q->setNewsletterRenderedSubject('Synthetic ' . $scenario);
    $q->setNewsletterRenderedBody(['html' => '<p>Synthetic queue [subscriber:email]</p>','text' => 'Synthetic queue [subscriber:email]']);
    if ($scenario === 'cold') {
        $template = (new MailPoet\Config\PopulatorData\Templates\SimpleText(plugins_url('mailpoet/assets')))->get();
        $n->setBody(json_decode($template['body'], true));
        $q->setNewsletterRenderedBody(null);
    }
    $q->setCountTotal(3);
    $q->setCountToProcess(3);
    $q->setCountProcessed(0);
    $em->persist($q);
    $prefix = ['full' => 'reject','permanent' => 'reject','temporary' => 'temporary','policy' => 'policy'][$scenario] ?? 'healthy-middle';
    $subs = [];
    foreach (['healthy-before',$prefix,'healthy-after'] as $local) {
        $sub = new SubscriberEntity();
        $sub->setEmail($local . '-' . uniqid() . '@example.test');
        $sub->setFirstName('Synthetic');
        $sub->setLastName($local);
        $sub->setStatus('subscribed');
        $em->persist($sub);
        $em->persist(new SubscriberSegmentEntity($segment, $sub, 'subscribed'));
        $em->persist(new ScheduledTaskSubscriberEntity($t, $sub));
        $subs[] = $sub;
    }
    $em->flush();
    file_put_contents($stateFile, json_encode(['task' => $t->getId(),'newsletter' => $n->getId(),'emails' => array_map(fn($sub)=>$sub->getEmail(), $subs)]));
} else {
    $state = json_decode(file_get_contents($stateFile), true);
    $t = $em->find(ScheduledTaskEntity::class, $state['task']);
    $n = $em->find(NewsletterEntity::class, $state['newsletter']);
    $q = $t->getSendingQueue();
    $subs = [];
 // New PHP process, fresh actual persisted task rows: processed recipients cannot reenter the batch.
    foreach ($em->getRepository(ScheduledTaskSubscriberEntity::class)->findBy(['task' => $t,'processed' => 0]) as $row) {
        $subs[] = $row->getSubscriber();
    }
}
$exception = null;
try {
    if (in_array($scenario, ['full','cold'], true)) {
        $c->get(SendingQueue::class)->process(time());
    } else {
        $c->get(SendingQueue::class)->processQueue($t, $n, $subs, time());
    }
} catch (Exception $e) {
    $exception = $e->getMessage();
}
$rows = [];
foreach ($em->getRepository(ScheduledTaskSubscriberEntity::class)->findBy(['task' => $t]) as $row) {
    $em->refresh($row);
    $sub = $row->getSubscriber();
    $rows[] = ['email' => $sub->getEmail(),'processed' => $row->getProcessed(),'failed' => $row->getFailed(),'error' => $row->getError(),'status' => $sub->getStatus()];
}
$processed = array_sum(array_column($rows, 'processed'));
$failed = array_sum(array_column($rows, 'failed'));
$log = MailerLog::getMailerLog();
$done = $phase === 'retry' || in_array($scenario, ['permanent','full','cold','dsn'], true);
check($processed === ($done ? 3 : (in_array($scenario, ['temporary','policy'], true) ? 1 : 0)), 'Wrong processed count: ' . json_encode($rows));
check($failed === (in_array($scenario, ['permanent','full'], true) ? 1 : 0), 'Wrong failed count');
check($done ? $exception === null : ($exception !== null && !empty($log['retry_at'])), 'Wrong retry/continuation behavior: ' . json_encode($exception));
foreach ($rows as $row) {
    check($row['status'] === 'subscribed', 'Unexpected global subscriber bounce');
}
if ($scenario === 'dsn') {
    check($c->get(MailPoet\Cron\Workers\Bounce::class)->checkProcessingRequirements() === false, 'Host transport must not run MailPoet Sending Service bounce reports');
}
if (in_array($scenario, ['full','cold'], true)) {
    $em->refresh($t);
    check($t->getStatus() === 'completed', 'Public worker did not complete task');
}
if ($scenario === 'cold') {
    check(strlen($q->getNewsletterRenderedBody()['html']) > 100, 'Cold renderer produced no real HTML');
}
echo json_encode(['scenario' => $scenario,'phase' => $phase,'processed' => $processed,'failed' => $failed,'retry' => $exception,'rows' => $rows]) . "\n";

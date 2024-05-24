<?php

require_once('core.php');
require_once('config/config.php');
require_once('cache/mails.php');

$mails = [];
foreach($mailsHeader as $headers) {
    $mail = new Mail($headers);
    $mails[$mail->getId()] = new Mail($headers);
}

$clients = ['all' => []];
$domains = $config['clients'];
foreach($domains as $client) {
    if(is_null($client)) {
	continue;
    }
    $clients[$client] = [];
}

$currentClient = isset($_GET['client']) ? $_GET['client'] : 'all';
$currentDuration = isset($_GET['duration']) ? $_GET['duration'] : null;

$subjects = [];
foreach($mails as $mail) {
    $subjects[strtolower($mail->getSubject())] = $mail->getId();
}

foreach($mails as $id => $mail) {
    if($mail->isBounce()) {
	$mail->setReplyToId(null);
    }
    if($mail->getReplyToId()) {
        continue;
    }
    if(!preg_match('/^re ?: /', strtolower($mail->getSubject()))) {
        continue;
    }
    $keySubject = preg_replace('/^re ?: /', '', strtolower($mail->getSubject()));
    if(isset($subjects[$keySubject])) {
        $mails[$id]->setReplyToId($subjects[$keySubject]);
        unset($subjects[$keySubject]);
    }
}

foreach($mails as $mail) {
    if($mail->getReplyToId() && isset($mails[$mail->getReplyToId()])) {
        $mails[$mail->getReplyToId()]->addResponses($mail);
    }
}

foreach($mails as $id => $mail) {
    if($mail->getReplyToId()) {
        unset($mails[$id]);
        continue;
    }
    if(count($mail->getResponses()) > 0) {
        unset($mails[$id]);
        continue;
    }
}

$counter = [];
$counterConf = ['-24 hours' => "24 dernières heures", '-7 days' => "7 derniers jours", '-30 days' => "30 derniers jours", '-3 months' => "3 derniers mois glissant"];
foreach($mails as $mail):
    $client = $mail->getClient();
    if(!$client) {
        continue;
    }
    $dateObject = $mail->getDateObject();
    if(!$dateObject) {
        continue;
    }
    if($dateObject->format('Y-m-d') < Config::getInstance()->config['from_date']) {
        continue;
    }
    if($client == $currentClient || $currentClient == 'all') {
        foreach($counterConf as $duration => $counterLibelle) {
            if(!isset($counter[$duration])) {
                $counter[$duration] = 0;
            }
            $counter[$duration];
            if($dateObject->format('Y-m-d') >= (new DateTime())->modify($duration)->format('Y-m-d')) {
                $counter[$duration]++;
            }
        }
    }
    if($currentDuration && $dateObject->format('Y-m-d') < (new DateTime())->modify($currentDuration)->format('Y-m-d')) {
        continue;
    }
    $clients['all'][$dateObject->format('Y-m-d H:i:s').$mail->getId()] = $mail;
    $clients[$client][$dateObject->format('Y-m-d H:i:s').$mail->getId()] = $mail;
endforeach;

foreach($clients as $client => $mails) {
    ksort($clients[$client]);
};

uasort($clients, function($a, $b) { return count($a) < count($b); });

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title></title>
    <link id="favicon-link" rel="icon" type="image/x-icon" href="">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
    <div class="container">
        <div class="row mt-4">
            <div class="col-2">
                <div class="card">
                    <div class="list-group list-group-flush">
                        <?php foreach($clients as $client => $mails): ?>
                            <a href="?<?php if($client != $currentClient): ?>client=<?php echo $client ?><?php endif; ?>" class="list-group-item d-flex justify-content-between align-items-center <?php if($client == 'all'): ?>fs-5 bg-light<?php endif; ?> <?php if($currentClient == $client && $client != 'all'): ?>active<?php endif; ?> <?php if($currentClient == $client && $client == 'all'): ?>text-primary<?php endif; ?> <?php if(!count($mails)): ?>opacity-50<?php endif; ?>">
                                <?php if($client == 'all'): ?>ZeroInbox<?php else: ?><?php echo $client ?><?php endif; ?>
                                <span class="badge <?php if($currentClient != $client && $client == 'all'): ?>bg-dark<?php elseif($currentClient != $client): ?>bg-secondary bg-opacity-75<?php endif; ?><?php if($currentClient == $client && $client != 'all'): ?>bg-white text-primary<?php endif; ?> <?php if($currentClient == $client && $client == 'all'): ?>bg-primary<?php endif; ?> rounded-pill"><?php echo count($mails) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row mb-4">
                    <?php foreach($counter as $duration => $number):  ?>
                    <div class="col">
                        <div class="card <?php if($currentDuration == $duration): ?>border-primary<?php endif; ?>">
                            <div class="card-header <?php if($currentDuration == $duration): ?>text-bg-primary<?php endif; ?> text-center"><?php echo $counterConf[$duration] ?></div>
                            <div class="card-body text-center p-1">
                                <a class="btn stretched-link <?php if($currentDuration == $duration): ?>text-primary<?php endif; ?>" href="?client=<?php echo $currentClient ?><?php if(!$currentDuration || $currentDuration != $duration): ?>&duration=<?php echo $duration ?><?php endif ?>"><?php echo $number ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <table class="table table-bordered table-striped table-sm">
                    <thead>
                        <tr>
                            <th class="col-1">Date</th>
                            <th class="col-3">De</th>
                            <th>Sujet</th>
                            <th class="col-1">Client</th>
                        </thead>
                        <tbody>
                            <?php foreach($clients[$currentClient] as $mail): ?>
                                <tr>
                                    <td class="text-nowrap" title="<?php echo $mail->getId() ?>"><?php echo $mail->getDateObject()->format('d/m/Y H:i'); ?></td>
                                    <td class="text-truncate col-3" style="max-width: 250px;" title="<?php echo $mail->getFromEmail(); ?>"><?php echo $mail->getFromEmail(); ?></td>
                                    <td><?php echo $mail->getSubject(); ?></td>
                                    <td class="text-nowrap" class="text-center"><?php echo $mail->getClient(); ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                    <textarea class="form-control mb-4 opacity-25" style="height: 400px;" readonly="readonly"><?php foreach($clients[$currentClient] as $mail): ?>-------------------
Date: <?php echo $mail->getHeader('Date') ?>

From: <?php echo $mail->getHeader('From') ?>

Subject: <?php echo $mail->getSubject(); ?>

<?php endforeach; ?></textarea>
                </div>
            </div>
        </div>
	<script>
        function makeFavicon(letters, color, backgroundColor, fontSizeInPixels, x, y) {
            let canvas = document.createElement('canvas');
            canvas.width = 16;
            canvas.height = 16;

	    let ctx = canvas.getContext('2d');
	    ctx.fillStyle = backgroundColor;
	    ctx.beginPath();
	    ctx.roundRect(0, 0, 16, 16, [8]);
	    ctx.fill();
            let ctx2 = canvas.getContext("2d");
            ctx2.fillStyle = color;
            ctx2.font = "bold "+fontSizeInPixels.toString()+"px monospace";
            ctx2.fillText(letters, x, y);

            let link = document.getElementById("favicon-link");
            link.href = canvas.toDataURL("image/x-icon");
        }
	makeFavicon("<?php echo count($clients['all']) ?>", "white", "black", 10, 2, 12);
	setInterval(function() { window.location.reload(); }, 600000);
    </script>
    </body>
    </html>

<?php

require_once('config/config.php');
include_once('cache/mails.php');

function findClient($mail, $domains) {

    if(isset($domains[strtolower($mail['From'])])) {
        return $domains[strtolower($mail['From'])];
    }

    $domain = explode('@', $mail['From'])[1];

    if(isset($domains[strtolower($domain)])) {
        return $domains[strtolower($domain)];
    }

    return null;
}

$clients = ['all' => []];
$domains = $config['clients'];
foreach($domains as $client) {
    $clients[$client] = [];
}

$current = isset($_GET['client']) ? $_GET['client'] : 'all';
$subjects = [];
foreach($mails as $id => $mail) {
    $subjects[strtolower($mail['Subject'])] = $mail['Message-Id'];
}
foreach($mails as $id => $mail) {
    if(isset($mail['In-Reply-To'])) {
        continue;
    }
    if(!preg_match('/^re ?: /', strtolower($mail['Subject']))) {
        continue;
    }
    $keySubject = preg_replace('/^re ?: /', '', strtolower($mail['Subject']));
    if(isset($subjects[$keySubject])) {
        $mails[$id]['In-Reply-To'] = $subjects[$keySubject];
        unset($subjects[$keySubject]);
    }
}
foreach($mails as $mail) {
    if(isset($mail['In-Reply-To'])) {
        $mails[$mail['In-Reply-To']]['Responses'][] = $mail['Message-Id'];
    }
}

foreach($mails as $id => $mail) {
    if(isset($mail['In-Reply-To']) && $mail['In-Reply-To']) {
        unset($mails[$id]);
        continue;
    }
    if(isset($mail['Responses']) && count($mail['Responses']) > 0) {
        unset($mails[$id]);
        continue;
    }
}

$counter = [];
$counterConf = ['-24 hours' => "24 dernières heures", '-7 days' => "7 derniers jours", '-30 days' => "30 derniers jours", '-3 months' => "3 derniers mois glissant"];
foreach($mails as $mail):
    $client = findClient($mail, $domains);
    if(!$client) {
        continue;
    }
    $mail['DateOrigin'] = $mail['Date'];
    try {
        $mail['Date'] = new DateTime($mail['Date']);
        $mail['Date']->modify("+1 hour");
    } catch(Exception $e) {
        continue;
    }
    if($mail['Date']->format('Y') != date('Y')) {
        continue;
    }
    foreach($counterConf as $duration => $counterLibelle) {
        if(!isset($counter[$counterLibelle])) {
            $counter[$counterLibelle] = 0;
        }
        $counter[$counterLibelle];
        if($mail['Date']->format('Y-m-d') >= (new DateTime())->modify($duration)->format('Y-m-d')) {
            $counter[$counterLibelle]++;
        }
    }
    $mail['Client'] = $client;
    $clients['all'][$mail['Date']->format('Y-m-d H:i:s').$mail['Message-Id']] = $mail;
    $clients[$client][$mail['Date']->format('Y-m-d H:i:s').$mail['Message-Id']] = $mail;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
</head>
<body>
    <div class="container">
        <div class="row mt-4">
            <div class="col-2">
                <div class="card">
                    <div class="list-group list-group-flush">
                        <?php foreach($clients as $client => $mails): ?>
                            <a href="?<?php if($client != $current): ?>client=<?php echo $client ?><?php endif; ?>" class="list-group-item d-flex justify-content-between align-items-center <?php if($client == 'all'): ?>fs-5 bg-light<?php endif; ?> <?php if($current == $client && $client != 'all'): ?>active<?php endif; ?> <?php if($current == $client && $client == 'all'): ?>text-primary<?php endif; ?>">
                                <?php if($client == 'all'): ?>ZeroInbox<?php else: ?><?php echo $client ?><?php endif; ?>
                                <span class="badge <?php if($current != $client && $client == 'all'): ?>bg-dark<?php elseif($current != $client): ?>bg-secondary bg-opacity-75<?php endif; ?><?php if($current == $client && $client != 'all'): ?>bg-white text-primary<?php endif; ?> <?php if($current == $client && $client == 'all'): ?>bg-primary<?php endif; ?> rounded-pill"><?php echo count($mails) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="row mb-4">
                    <?php foreach($counter as $libelle => $number):  ?>
                    <div class="col">
                        <div class="card">
                            <div class="card-header text-center"><?php echo $libelle ?></div>
                            <div class="card-body text-center p-1">
                                <a class="btn" href=""><?php echo $number ?></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <table class="table table-bordered table-striped table-sm">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>De</th>
                            <th>Sujet</th>
                            <th>Client</th>
                        </thead>
                        <tbody>
                            <?php foreach($clients[$current] as $mail): ?>
                                <tr>
                                    <td title="<?php echo $mail['Message-Id'] ?>"><?php echo str_replace(" ", "&nbsp;", $mail['Date']->format('d/m/Y H:i')); ?></td>
                                    <td><?php echo $mail['From']; ?></td>
                                    <td><?php echo $mail['Subject']; ?></td>
                                    <td class="text-center"><?php echo $mail['Client']; ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                    <textarea class="form-control mb-4 opacity-25" style="height: 400px;" readonly="readonly"><?php foreach($clients[$current] as $mail): ?>-------------------
Date: <?php echo $mail['DateOrigin'] ?>

From: <?php echo $mail['FromOrigin'] ?>

Subject: <?php echo $mail['Subject']; ?>

<?php endforeach; ?></textarea>
                </div>
            </div>
        </div>
    </body>
    </html>

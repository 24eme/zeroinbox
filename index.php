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

$counter = ['today' => 0, 'yesterday' => 0, 'week' => 0, 'lastweek' => 0, 'month' => 0, 'lastmonth' => 0];
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
    if($mail['Date']->format('Y-m-d') == date('Y-m-d')) {
        $counter['today']++;
    }
    if($mail['Date']->format('Y-m-d') == (new DateTime())->modify('-1 day')->format('Y-m-d')) {
        $counter['yesterday']++;
    }
    if($mail['Date']->format('Y-m-d') >= (new DateTime())->modify('monday this week')->format('Y-m-d') && $mail['Date']->format('Y-m-d') <= (new DateTime())->modify('sunday this week')->format('Y-m-d')) {
        $counter['week']++;
    }
    if($mail['Date']->format('Y-m-d') >= (new DateTime())->modify('monday last week')->format('Y-m-d') && $mail['Date']->format('Y-m-d') <= (new DateTime())->modify('sunday last week')->format('Y-m-d')) {
        $counter['lastweek']++;
    }
    if($mail['Date']->format('Y-m-d') >= (new DateTime())->modify('first day of this month')->format('Y-m-d') && $mail['Date']->format('Y-m-d') <= (new DateTime())->modify('last day of this month')->format('Y-m-d')) {
        $counter['month']++;
    }
    if($mail['Date']->format('Y-m-d') >= (new DateTime())->modify('first day of last month')->format('Y-m-d') && $mail['Date']->format('Y-m-d') <= (new DateTime())->modify('last day of last month')->format('Y-m-d')) {
        $counter['lastmonth']++;
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
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">Aujourd'hui</div>
                    <div class="card-body">
                        <h5 class="card-title text-center"><?php echo $counter['today'] ?></h5>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">Hier</div>
                    <div class="card-body">
                        <h5 class="card-title text-center"><?php echo $counter['yesterday'] ?></h5>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">Cette semaine</div>
                    <div class="card-body">
                        <h5 class="card-title text-center"><?php echo $counter['week'] ?></h5>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">La semaine dernière</div>
                    <div class="card-body">
                        <h5 class="card-title text-center"><?php echo $counter['lastweek'] ?></h5>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">Ce mois</div>
                    <div class="card-body">
                        <h5 class="card-title text-center"><?php echo $counter['month'] ?></h5>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">Le mois dernier</div>
                    <div class="card-body">
                        <h5 class="card-title text-center"><?php echo $counter['lastmonth'] ?></h5>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-2">
                <div class="list-group">
                    <?php foreach($clients as $client => $mails): ?>
                        <a href="?client=<?php echo $client ?>" class="list-group-item d-flex justify-content-between align-items-center <?php if($current == $client): ?>active<?php endif; ?>">
                            <?php echo $client ?>
                            <span class="badge bg-primary rounded-pill"><?php echo count($mails) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col">
                <table class="table table-bordered table-sm table-striped">
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
                                    <td><?php echo $mail['Client']; ?></td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                    <textarea class="form-control mb-4" style="height: 400px;" readonly="readonly"><?php foreach($clients[$current] as $mail): ?>-------------------
Date: <?php echo $mail['DateOrigin'] ?>

From: <?php echo $mail['FromOrigin'] ?>

Subject: <?php echo $mail['Subject']; ?>

<?php endforeach; ?></textarea>
                </div>
            </div>
        </div>
    </body>
    </html>

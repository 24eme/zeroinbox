<?php

// Usage : export.php ['@client.fr']

require_once('core.php');
require_once('config/config.php');

// @var $mailsHeader
require_once('cache/mails.php');

if (is_iterable($mailsHeader) === false) {
    fwrite(STDERR, "Le tableau de mail n'existe pas, ou est malformé".PHP_EOL);
    exit;
}

$csv = fopen('php://output', 'a');

if ($csv === false) {
    fwrite(STDERR, "Impossible d'ouvrir le flux de sortie".PHP_EOL);
    exit;
}

$client = $argc === 2 ? $argv[1] : null;

foreach ($mailsHeader as $headers) {
    $mail = new Mail($headers);

    if ($client && strstr($mail->getFromEmail(), '@') !== $client) {
        continue;
    }

    fputcsv($csv, [
        $mail->getDateObject() ? $mail->getDateObject()->format('Y-m-d H:i:s') : null,
        $mail->getSubject(),
        $mail->getFromEmail(),
        $mail->getReplyToId(),
        $mail->getClient(),
        $mail->getId(),
    ], ';');
}

fclose ($csv);

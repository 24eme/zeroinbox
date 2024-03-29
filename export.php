<?php

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

foreach ($mailsHeader as $headers) {
    $mail = new Mail($headers);

    fputcsv($csv, [
        $mail->getClient(),
        $mail->getSubject(),
        $mail->getDateObject()?->format('Y-m-d H:i:s'),
        $mail->getId(),
        $mail->getReplyToId(),
    ], ';');
}

fclose ($csv);

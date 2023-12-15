<?php

require_once('config/config.php');

function extractMail($text) {
    if(strpos($text, '<') === false) {
        return null;
    }

    return preg_replace('/>[^>]*$/', "", preg_replace('/^[^<]*</', "", $text));
}

function storeMail($entry, &$entries, &$subjects) {
    if(!isset($entry['Message-Id'])) {
        return;
    }
    if(!isset($entry['From'])) {
        return;
    }
    $subjectDecode = @iconv_mime_decode($entry['Subject']);
    if($subjectDecode) {
        $entry['Subject'] = $subjectDecode;
    }
    $entries[$entry['Message-Id']] = $entry;
}

$handle = fopen($config['mail_file'], "r");

$discussions = [];
$subjects = [];

$mail = ["From" => null, "Subject" => null, "Date" => null, "Message-Id" => null, "In-Reply-To" => null];
$header = true;
$currentPrefix = null;
while (($line = fgets($handle)) !== false) {
    if(preg_match('/^(From .?$|From - |From [^@ ]+@[^@ ]+ )/', $line)) {
        storeMail($mail, $discussions, $subjects);
        $mail = ["From" => null, "Subject" => null, "Date" => null, "Message-Id" => null, "In-Reply-To" => null];
        $currentPrefix = null;
        $header = true;
        continue;
    }

    if(!$header) {
        continue;
    }

    if($line === "\n") {
        $header = false;
        continue;
    }

    $line = str_replace("\n", "", $line);
    if(preg_match("/^[ \t]/", $line)) {
        $line = $currentPrefix.$line;
    }
    $currentPrefix = null;

    if(strpos($line, 'From:') === 0) {
        $currentPrefix = 'From:';
        $mail['From'] .= trim(extractMail($line));
        $mail['FromOrigin'] .= str_replace("From: ", "", $line);
    }
    if(strpos($line, 'Subject:') === 0) {
        $currentPrefix = 'Subject:';
        $mail['Subject'] .= preg_replace('/^Subject: /', '', $line);
    }
    if(strpos($line, 'Date:') === 0) {
        $currentPrefix = 'Date:';
        $mail['Date'] .= preg_replace('/^Date: /', '', $line);
    }
    if(strpos(strtoupper($line), 'MESSAGE-ID:') === 0) {
        $currentPrefix = 'Message-Id:';
        $mail['Message-Id'] .= extractMail($line);
    }
    if(strpos(strtoupper($line), 'IN-REPLY-TO:') === 0) {
        $currentPrefix = 'In-Reply-To:';
        $mail['In-Reply-To'] .= extractMail($line);
    }
}
storeMail($mail, $discussions, $subjects);
fclose($handle);

file_put_contents("cache/mails.php", "<?php \$mails = ".var_export($discussions, true).";");

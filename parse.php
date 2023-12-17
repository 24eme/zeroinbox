<?php

require_once('core.php');
require_once('config/config.php');

$handle = fopen($config['mail_file'], "r");

$mailsHeader = [];

$headers = Mail::getEmptyHeaders();
$headerEnded = false;
$currentHeaderKey = null;
while (($line = fgets($handle)) !== false) {
    if(preg_match('/^(From .?$|From - |From [^@ ]+@[^@ ]+ )/', $line)) {
        $mailsHeader[] = $headers;
        $headers = Mail::getEmptyHeaders();
        $currentHeaderKey = null;
        $headerEnded = false;
        continue;
    }

    if($headerEnded) {
        continue;
    }

    if($line === "\n") {
        $headerEnded = true;
        continue;
    }

    $line = str_replace("\n", "", $line);
    if(preg_match("/^[ \t]/", $line)) {
        $line = $currentHeaderKey.': '.$line;
    }
    $currentHeaderKey = null;

    foreach($headers as $headerKey => $headerValue) {
        if(strpos(strtolower($line), strtolower($headerKey).':') === 0) {
            $currentHeaderKey = $headerKey;
            $headers[$headerKey] .= preg_replace('/^'.$headerKey.': /i', '', $line);
        }
    }
}
$mailsHeader[] = $headers;
fclose($handle);

file_put_contents("cache/mails.php", "<?php \$mailsHeader = ".var_export($mailsHeader, true).";");

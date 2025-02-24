<?php

class Mail {

    protected $headers = null;
    protected $responses = [];

    public function __construct($headers) {
        $this->headers = $headers;
    }

    public static function __set_state($properties)
    {
        return new Mail($properties['headers']);
    }

    public static function getEmptyHeaders() {

        return ["From" => null, "Subject" => null, "Date" => null, "Message-Id" => null, "In-Reply-To" => null, "Resent-To" => null];
    }

    public static function extractInChevron($value) {
        if(strpos($value, '<') === false) {
            return null;
        }

        return preg_replace('/>[^>]*$/', "", preg_replace('/^[^<]*</', "", $value));
    }

    public function getHeader($key) {

        return $this->headers[$key];
    }

    public function getId() {

        return self::extractInChevron($this->headers['Message-Id']);
    }

    public function isBounce() {

	    return isset(Config::getInstance()->config['bounce_mail']) && isset($this->headers['Resent-To']) && $this->headers['Resent-To'] == Config::getInstance()->config['bounce_mail'];
    }

    public function setReplyToId($value) {
        $this->headers['In-Reply-To'] = "<".$value.">";
    }

    public function getReplyToId() {

        return self::extractInChevron($this->headers['In-Reply-To']);
    }

    public function addResponses(Mail $mail) {
        $this->responses[] = $mail;
    }

    public function getResponses() {
        $responses = $this->responses;
        foreach($this->responses as $mail) {
            $responses = array_merge($responses, $mail->getResponses());
        }

        $responsesEquipe = [];
        foreach($responses as $mail) {
            if(strpos($mail->getFromEmail(), Config::getInstance()->config['team_email']) !== false) {
                $responsesEquipe[] = $mail;
            }
        }

        return $responsesEquipe;
    }

    public function getClient() {
        $config = Config::getInstance()->config;

        if(array_key_exists(strtolower($this->getFromEmail()), $config['clients'])) {
            return $config['clients'][strtolower($this->getFromEmail())];
        }
	if (strpos($this->getFromEmail(), '@') === false) {
		return null;
	}
	$domain = explode('@', $this->getFromEmail())[1];

        if(isset($config['clients'][strtolower($domain)])) {
            return $config['clients'][strtolower($domain)];
        }

        return null;
    }

    public function getDateObject() {
        try {
            $date = new DateTime($this->headers['Date']);
            $date->modify("+1 hour");
        } catch(Exception $e) {

            return null;
        }

        return $date;
    }

    public function getFromEmail() {

        return self::extractInChevron($this->headers['From']);
    }

    public function getSubject() {
        $subjectDecode = @iconv_mime_decode($this->headers['Subject']);

        if(!$subjectDecode) {

            return $this->headers['Subject'];
        }

        return $subjectDecode;
    }
}

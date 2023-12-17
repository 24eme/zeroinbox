<?php

require_once('config/config.php');

class Config {
    private static $_instance;
    public $config = [];

    public static function getInstance($config = null)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new Config($config);
        }
        return self::$_instance;
    }

    private function __construct($config)
    {
        $this->config = $config;
    }
}

Config::getInstance($config);

require_once('mail.php');

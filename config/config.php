<?php
define('PROJECT_ROOT', dirname(__DIR__).'/');

set_time_limit(0);

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'affiliate');
define('DB_PASS', 'T&3123Mch');
define('DB_NAME', 'ptt_crawler');

function __autoload($class)
{
    $class_file = PROJECT_ROOT."library/class.{$class}.php";
    if(file_exists($class_file)) { include_once($class_file); }
}
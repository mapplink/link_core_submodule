<?php
if (file_exists('local.php')) {
    require_once('local.php');
}

chdir(dirname(str_replace('magelink/', '', __DIR__)));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return FALSE;
}

// Setup autoloading
require 'init_autoloader.php';

require_once('magelink/Application/src/Application/Helper/ErrorHandler.php');
$errorHandler = new Application\Helper\ErrorHandler(FALSE);

// Run the application!
Zend\Mvc\Application::init(require 'config/application.config.php')->run();

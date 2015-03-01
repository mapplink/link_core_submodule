<?php

namespace Log\Logger;

use Log\Service\LogService;

class StdoutLogger extends AbstractLogger {

    protected $_cliMode = false;

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     *
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config=array()) {
        if(php_sapi_name() == 'cli'){
            $this->_cliMode = true;
        }else{
            return false;
        }

        return true;
    }

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     *
     * @param $level
     * @param $code
     * @param $message
     * @param $data
     * @param $extraData
     * @param $lastStackFrame
     */
    function printLog($level, $code, $message, $data, $extraData, $lastStackFrame) {

        $s1 = '['.strtoupper($level).':' . $code . ']';
        if(isset($lastStackFrame['class'])){
            $s2 = $lastStackFrame['class'] . $lastStackFrame['type'] . $lastStackFrame['function'] . ':'.$lastStackFrame['line'];
        }else{
            // Exception-recovered format
            $s2 = $lastStackFrame['file'] . ':' . $lastStackFrame['line'];
        }
        $s3 = $message;

        $p1 = 25 - strlen($s1);
        $p2 = 50 - strlen($s2);
        if($p2 < 0){
            $p2 = 4;
        }
        if($p1 <= 0){
            $p1 = 1;
        }
        if($p2 <= 0){
            $p2 = 1;
        }

        if($level == LogService::LEVEL_ERROR && $this->_cliMode){
            echo "\033[0;31m\033[40m";
        }
        if($level == LogService::LEVEL_WARN && $this->_cliMode){
            echo "\033[1;31m\033[40m";
        }

        if(!$this->_cliMode){
            echo '<pre>';
        }
        echo $s1 . str_repeat(' ', $p1) . $s2 . str_repeat(' ', $p2) . $s3 . PHP_EOL;
        if(!$this->_cliMode){
            echo '</pre><br/>';
        }

        if(($level == LogService::LEVEL_ERROR || $level == LogService::LEVEL_WARN) && $this->_cliMode){
            echo "\033[0m";
        }

    }

    /**
     * Output any queued messages (if relevant).
     */
    function flushLog() {
        // Unused
    }

}
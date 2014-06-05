<?php

namespace Log\Logger;

use \Log\Service\LogService;

class EmailLogger extends AbstractLogger {

    protected $logsTarget = 'forms@lero9.co.nz';
    protected $lastCache = array();
    protected $cacheSize = 20;

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     *
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config=array()) {
        $this->lastCache = array();
        // TODO make cacheSize configurable?

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

        $s4 = '';

        if(count($data)){
            $s4 .= " \tdata{";
            $entries = array();
            foreach($data as $key=>$ed){
                $entries[] = $key . ': ' . $this->convertDataHuman($ed);
            }
            $s4 .= implode(', ', $entries);
            $s4 .= '}';
        }

        if(count($extraData)){
            $s4 .= " \textraData{";
            $entries = array();
            foreach($extraData as $key=>$ed){
                $entries[] = $key . ': ' . $this->convertDataHuman($ed);
            }
            $s4 .= implode(', ', $entries);
            $s4 .= '}';
        }

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

        $output = $s1 . str_repeat(' ', $p1) . $s2 . str_repeat(' ', $p2) . $s3 . $s4 . PHP_EOL;

        if(count($this->lastCache) >= $this->cacheSize){
            array_pop($this->lastCache);
        }
        array_unshift($this->lastCache, $output);

        if($level == LogService::LEVEL_ERROR){
            $this->sendAlert($level, $code, $message, $data, $extraData, $lastStackFrame);
        }

    }

    protected function sendAlert($level, $code, $message, $data, $extraData, $lastStackFrame){

        $subject = 'MageLink ERROR: [' . $code . '] ' . $message;
        $contents = 'MageLink error thrown! Details:'.PHP_EOL.PHP_EOL;

        $contents .= implode(PHP_EOL.PHP_EOL.'----------'.PHP_EOL.PHP_EOL, $this->lastCache);

        mail($this->logsTarget, $subject, $contents, 'Content-Type: text/plain');

    }

    /**
     * Output any queued messages (if relevant).
     */
    function flushLog() {
        $this->lastCache = array();
    }

}
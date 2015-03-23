<?php
/**
 * Log\Logger\EmailLogger
 *
 * @category Log
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Log\Logger;

use Log\Service\LogService;
use Application\Helper\ErrorHandler;


class EmailLogger extends AbstractLogger
{

    const ERROR_TO_CLIENT = 'alerts@healthpost.co.nz';
    const ERROR_TO_CLIENT_CODE = 'cno_';
    const ERROR_TO_CLIENT_STARTHOUR = 7;
    const ERROR_TO_CLIENT_ENDHOUR = 20;

    protected $lastCache = array();
    protected $cacheSize = 20;

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     *
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config=array())
    {
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
    function printLog($level, $code, $message, $data, $extraData, $lastStackFrame)
    {
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

    protected function sendAlert($errorLevel, $errorCode, $errorMessage, $data, $extraData, $lastStackFrame)
    {
        $subject = 'MageLink ERROR: ['.$errorCode.'] '.$errorMessage;
        $content = 'MageLink error thrown! Details:'.PHP_EOL.PHP_EOL;
        $content .= implode(PHP_EOL.PHP_EOL.'----------'.PHP_EOL.PHP_EOL, $this->lastCache);

        mail(ErrorHandler::ERROR_TO, $subject, $content, 'Content-Type: text/plain');

        $clientCodeMatching = strpos($errorCode, self::ERROR_TO_CLIENT_CODE) !== FALSE;
        $daytime = (date('H') > self::ERROR_TO_CLIENT_STARTHOUR) && (date('H') < self::ERROR_TO_CLIENT_ENDHOUR);
        $devOrStaging = (strpos(__DIR__, 'dev.') + strpos(__DIR__, 'staging.') > 0);

        if (self::ERROR_TO_CLIENT && $clientCodeMatching && $daytime && !$devOrStaging) {
            $additionalHeader = 'Content-Type: text/plain'."\r\n".'From: '.ErrorHandler::ERROR_FROM;
            mail(self::ERROR_TO_CLIENT, $subject, $content, $additionalHeader);
        }
    }

    /**
     * Output any queued messages (if relevant).
     */
    function flushLog()
    {
        $this->lastCache = array();
    }

}
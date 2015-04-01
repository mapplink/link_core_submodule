<?php
/**
 * @category Log
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Log\Logger;

use Log\Service\LogService;


class StdoutLogger extends AbstractLogger
{

    /** @var bool $_cliMode */
    protected $_cliMode = FALSE;


    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config = array())
    {
        if (php_sapi_name() == 'cli') {
            $success = $this->_cliMode = TRUE;
        }else{
            $success = FALSE;
        }

        return $success;
    }

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     * @param string $level
     * @param string $code
     * @param string$message
     * @param array $data
     * @param array $extraData
     * @param array $lastStackFrame
     */
    function printLog($level, $code, $message, array $data, array $extraData, array $lastStackFrame)
    {
        $specifier = '['.strtoupper($level).':'.$code.']';
        if(isset($lastStackFrame['class'])){
            $basicInformation = $lastStackFrame['class'].$lastStackFrame['type'].$lastStackFrame['function'].':'.$lastStackFrame['line'];
        }else{
            $basicInformation = $lastStackFrame['file'].':'.$lastStackFrame['line'];
        }

        $specifierGap = 25 - strlen($specifier);
        if($specifierGap <= 0){
            $specifierGap = 1;
        }

        $basicGap = 50 - strlen($basicInformation);
        if ($basicGap < 0) {
            $basicGap = 4;
        }elseif ($basicGap == 0) {
            $basicGap = 1;
        }

        if ($this->_cliMode) {
            switch ($level) {
                case LogService::LEVEL_ERROR:
                    $prefix = "\033[0;31m\033[40m";
                    $suffix = "\033[0m";
                    break;
                case LogService::LEVEL_WARN:
                    $prefix = "\033[1;31m\033[40m";
                    $suffix = "\033[0m";
                    break;
                default:
                    $prefix = $suffix = '';
            }
        }else{
            $prefix = '<pre>';
            $suffix = '</pre><br/>';
        }

        print $prefix.$specifier.str_repeat(' ', $specifierGap).$basicInformation.str_repeat(' ', $basicGap)
            .$message.PHP_EOL.$suffix;
    }

    /** Output any queued messages (if relevant). */
    function flushLog() {}

}
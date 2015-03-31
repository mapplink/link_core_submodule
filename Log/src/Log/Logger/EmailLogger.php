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
use Application\Helper\ErrorHandler;


class EmailLogger extends AbstractLogger
{
    const ERROR_TO_CLIENT = 'alerts@healthpost.co.nz';
    const ERROR_TO_CLIENT_CODE = 'cno_';
    const ERROR_TO_CLIENT_STARTHOUR = 7;
    const ERROR_TO_CLIENT_ENDHOUR = 20;

    protected $lastCache = array();
    protected $cacheSize = 20;

    protected $_allowedLevels = array(
        LogService::LEVEL_ERROR
    );



    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config = array())
    {
        $this->lastCache = array();
        // TODO (maybe) : make cacheSize configurable

        return TRUE;
    }

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     *
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array $data
     * @param array $extraData
     * @param array $lastStackFrame
     */
    function printLog($level, $code, $message, array $data, array $extraData, array $lastStackFrame)
    {
        $specifier = '['.strtoupper($level).':'.$code.']';

        if (isset($lastStackFrame['class'])) {
            $basicInformation = $lastStackFrame['class'].$lastStackFrame['type'].$lastStackFrame['function'].':'
                .$lastStackFrame['line'];
        }else{
            // Exception-recovered format
            $basicInformation = $lastStackFrame['file'].':'.$lastStackFrame['line'];
        }

        $additionalInformation = '';

        if (count($data)) {
            $additionalInformation .= " \t".'data{';
            $entries = array();
            foreach ($data as $key=>$dataRow) {
                $entries[] = $key.': '.$this->convertDataHuman($dataRow, 7);
            }
            $additionalInformation .= implode(', ', $entries);
            $additionalInformation .= '}';
        }

        if (count($extraData)) {
            $additionalInformation .= " \t".'extraData{';
            $entries = array();
            foreach ($extraData as $key=>$extraDataRow) {
                $entries[] = $key.': '.$this->convertDataHuman($extraDataRow, 7);
            }
            $additionalInformation .= implode(', ', $entries);
            $additionalInformation .= '}';
        }

        $specifierGap = 25 - strlen($specifier);
        if ($specifierGap <= 0) {
            $specifierGap = 1;
        }

        $basicGap = 50 - strlen($basicInformation);
        if ($basicGap < 0) {
            $basicGap = 4;
        }elseif ($basicGap == 0) {
            $basicGap = 1;
        }

        $output = $specifier.str_repeat(' ', $specifierGap).$basicInformation.str_repeat(' ', $basicGap)
            .$message.$additionalInformation.PHP_EOL;

        if (count($this->lastCache) >= $this->cacheSize) {
            array_pop($this->lastCache);
        }
        array_unshift($this->lastCache, $output);

        $this->sendAlert($code, $message);
    }

    /**
     * @param string $errorCode
     * @param string $errorMessage
     */
    protected function sendAlert($errorCode, $errorMessage)
    {
        $subject = 'MageLink ERROR: ['.$errorCode.'] '.$errorMessage;
        $content = 'MageLink error thrown! Details:'.PHP_EOL.PHP_EOL
            .implode(PHP_EOL.PHP_EOL.'----------'.PHP_EOL.PHP_EOL, $this->lastCache);

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
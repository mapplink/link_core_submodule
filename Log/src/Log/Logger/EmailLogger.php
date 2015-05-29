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

use Application\Helper\ErrorHandler;
use Application\Service\ApplicationConfigService;
use Log\Service\LogService;


class EmailLogger extends AbstractLogger
{

    protected $lastCache = array();
    protected $cacheSize = 20;

    protected $clientEmail;
    protected $clientEmailStarthour;
    protected $clientEmailEndhour;

    protected $_allowedLevels = array(
        LogService::LEVEL_ERROR
    );


    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    public function init(array $config = array())
    {
        /** @var ApplicationConfigService $applicationConfigService */
        $applicationConfigService = $this->getServiceLocator()->get('applicationConfigService');

        // TODO (maybe) : make cacheSize configurable
        $this->lastCache = array();
        $this->cacheSize = 20;

        $this->clientEmail = $applicationConfigService->getClientEmail();
        $this->clientEmailStarthour = $applicationConfigService->getClientStarthour();
        $this->clientEmailEndhour = $applicationConfigService->getClientEndhour();

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
            $additionalInformation .= implode(', ', $entries).', __DIR__: '.__DIR__.'}';
        }

        if (count($extraData)) {
            $additionalInformation .= " \t".'extraData{';
            $entries = array();
            foreach ($extraData as $key=>$extraDataRow) {
                $entries[] = $key.': '.$this->convertDataHuman($extraDataRow, 7);
            }
            $additionalInformation .= implode(', ', $entries).'}';
        }

        $specifierLength = max(27, strlen($specifier) + 2);
        $basicLength = max(50, strlen($basicInformation) + 3);
        $output = str_pad($specifier, $specifierLength).str_pad($basicInformation, $basicLength)
            .$message.$additionalInformation.PHP_EOL;

        if (count($this->lastCache) >= $this->cacheSize) {
            array_pop($this->lastCache);
        }
        array_unshift($this->lastCache, $output);

        $this->sendAlert($code, $message);
    }

    /**
     * @param string $errorCode
     * @param string $subjectMessage
     * @param bool $clientNotification
     */
    protected function sendAlert($errorCode, $subjectMessage)
    {
        $subject = 'MageLink ERROR'.($this->notifyClient() ? ' - Client notified' : '')
            .': ['.$errorCode.'] '.$subjectMessage;
        $content = 'MageLink error thrown! Details:'.PHP_EOL.PHP_EOL
            .implode(PHP_EOL.PHP_EOL.'----------'.PHP_EOL.PHP_EOL, $this->lastCache);

        mail(ErrorHandler::ERROR_TO, $subject, $content, 'Content-Type: text/plain');

        $daytime = (date('H') > $this->clientEmailStarthour) && (date('H') < $this->clientEmailEndhour);
        if ($this->clientEmail && $daytime && $this->notifyClient()) {
            $additionalHeader = 'Content-Type: text/plain'."\r\n".'From: '.ErrorHandler::ERROR_FROM;
            mail($this->clientEmail, $subject, $content, $additionalHeader);
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
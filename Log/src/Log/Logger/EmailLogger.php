<?php
/**
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Log\Logger;

use Application\Helper\ErrorHandler;
use Application\Service\ApplicationConfigService;
use Log\Service\LogService;


class EmailLogger extends AbstractLogger
{

    const EMAIL_MAX_LENGTH = 50000;

    protected $lastCache = array();
    protected $cacheSize = 20;

    protected $sender;
    protected $adminEmail;
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

        // @todo (maybe) : make cacheSize configurable
        $this->lastCache = array();
        $this->cacheSize = 20;

        $this->sender = $applicationConfigService->getSender();
        $this->adminEmail = $applicationConfigService->getAdminEmail();
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
        $subjectAddition = ' (Client notified)';
        $subject = 'MageLink ERROR'.($this->notifyClient() ? $subjectAddition : '')
            .' : ['.$errorCode.'] '.$subjectMessage;
        $clientSubject = str_replace($subjectAddition, '', $subject);
        $content = 'MageLink error thrown! Details:'.PHP_EOL.PHP_EOL
            .implode(PHP_EOL.PHP_EOL.'----------'.PHP_EOL.PHP_EOL, $this->lastCache);

        if (mb_strlen($content) > self::EMAIL_MAX_LENGTH) {
            $content = mb_substr($content, 0, self::EMAIL_MAX_LENGTH * 0.9)
                ."\r\n...\r\n".mb_substr($content, self::EMAIL_MAX_LENGTH * -0.1);
        }

        $additionalHeader = 'Content-Type: text/plain'."\r\n".'From: '.$this->sender;
        mail($this->adminEmail, $subject, $content, $additionalHeader);

        $daytime = (date('H') > $this->clientEmailStarthour) && (date('H') < $this->clientEmailEndhour);
        if ($this->clientEmail && $daytime && $this->notifyClient()) {
            mail($this->clientEmail, $clientSubject, $content, $additionalHeader);
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

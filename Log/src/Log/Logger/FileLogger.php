<?php
/*
 * Class AbstractLogger represents a log output method.
 * @category Log
 * @package Log\Logger
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 *
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Log\Logger;

use Log\Service\LogService;


class FileLogger extends AbstractLogger
{
    const LOGFILE = 'data/link.log';

    /** @var resource|NULL $_fileHandler */
    protected $_fileHandler = NULL;


    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return bool Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    function init($config = array())
    {
        $this->_fileHandler = fopen(self::LOGFILE, 'a');
        return (bool) $this->_fileHandler;
    }

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     * @param string $level
     * @param string $code
     * @param string $message
     * @param array $data
     * @param array $extraData
     * @param array $lastStackFrame
     */
    function printLog($level, $code, $message, array $data, array $extraData, array $lastStackFrame)
    {
        $specifier = date('y-m-d H:i:s').' ['.strtoupper($level).':'.$code.']';

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
                $entries[] = $key.': '.$this->convertDataHuman($dataRow);
            }
            $additionalInformation .= implode(', ', $entries);
            $additionalInformation .= '}';
        }

        if (count($extraData)) {
            $additionalInformation .= " \t".'extraData{';
            $entries = array();
            foreach ($extraData as $key=>$extraDataRow) {
                $entries[] = $key.': '.$this->convertDataHuman($extraDataRow);
            }
            $additionalInformation .= implode(', ', $entries).'}';
        }

        $specifierGap = max(3, 45 - strlen($specifier));
        $output = str_pad($specifier, $specifierGap).$basicInformation.PHP_EOL.
            str_pad($message.$additionalInformation, 19, STR_PAD_LEFT).PHP_EOL;
        fwrite($this->_fileHandler, $output);
    }

    /** Output any queued messages */
    function flushLog() {}

}
<?php
/*
 * Class AbstractLogger represents a log output method.
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
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
    public function init(array $config = array())
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
        $specifier = '['.strtoupper($level).':'.$code.' '.date('d/m H:i:s').']';

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

        $specifierLength = max(42, strlen($specifier) + 3);
        $output = str_pad($specifier, $specifierLength).$basicInformation.PHP_EOL
            .str_repeat(' ', 19).$message.$additionalInformation.PHP_EOL;
        fwrite($this->_fileHandler, $output);
    }

    /** Output any queued messages */
    function flushLog() {}

}

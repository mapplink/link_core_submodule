<?php
/**
 * @category Log
 * @package Log\Logger
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Log\Logger;

use Log\Service\LogService;


class StdoutLogger extends AbstractLogger
{

    /** @var bool $_cliMode */
    protected $_cliMode = FALSE;

    /** @var array $_allowedLevels */
    protected $_allowedLevels = array(
        LogService::LEVEL_INFO,
        LogService::LEVEL_WARN,
        LogService::LEVEL_ERROR,
        LogService::LEVEL_DEBUGINTERNAL
    );


    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    public function init(array $config = array())
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
        if (isset($lastStackFrame['class'])) {
            $basicInformation = $lastStackFrame['class'].$lastStackFrame['type'].$lastStackFrame['function'].':'.$lastStackFrame['line'];
        }else{
            $basicInformation = $lastStackFrame['file'].':'.$lastStackFrame['line'];
        }

        if ($this->_cliMode) {
            switch ($level) {
                case LogService::LEVEL_ERROR:
                    $prefix = "\033[1;31m\033[40m"; // red
                    $suffix = "\033[0m";
                    break;
                case LogService::LEVEL_WARN:
                    $prefix = "\033[1;33m\033[40m"; // yellow
                    $suffix = "\033[0m";
                    break;
                case LogService::LEVEL_DEBUGINTERNAL:
                    $prefix = "\033[1;34m\033[40m"; // light blue
                    $suffix = "\033[0m";
                    break;
                default:
                    $prefix = $suffix = '';
            }
        }else{
            $prefix = '<pre>';
            $suffix = '</pre><br/>';
        }

        $specifierLength = max(27, strlen($specifier) + 2); // Leaves for the log code 16 characters
        $basicLength = max(52, strlen($basicInformation) + 2);

        print $prefix.str_pad($specifier, $specifierLength).str_pad($basicInformation, $basicLength).$message
            .PHP_EOL.$suffix;
    }

    /** Output any queued messages (if relevant). */
    function flushLog() {}

}

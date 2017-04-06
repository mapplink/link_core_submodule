<?php
/**
 * @category Application
 * @package Application\Helper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Application\Helper;

use Log\Logger\EmailLogger;


class ErrorHandler
{

    const ERROR_TO = 'magelink_log@lero9.com';
    const ERROR_FROM = 'noreply@lero9.co.nz';

    /** @var bool|NULL $allowException */
    protected static $allowException = NULL;
    /** @var string|FALSE $_lastError */
    protected $_lastError = FALSE;


    /**
     * ErrorHandler constructor.
     * @param bool|NULL $allowException
     */
    public function __construct($allowException = NULL)
    {
        if (self::$allowException === NULL) {
            register_shutdown_function(array($this, 'shutdownhandler'));
        }

        if ($allowException === NULL && self::$allowException !== NULL) {
            $allowException = self::$allowException;
        }elseif ($allowException !== NULL) {
            self::$allowException = $allowException;
        }else{
            self::$allowException = FALSE;
            $allowException = FALSE;
        }

        set_error_handler(array($this, 'errorhandler'));

        if ($allowException) {
            set_exception_handler(array($this, 'exceptionhandler'));
        }
    }

    /**
     * Shutdownhandler: Throws last error is there is any
     */
    public function shutdownhandler()
    {
        $error = error_get_last();

        if (!is_null($error) && $error['type'] === E_NOTICE) {
            $this->errorhandler(
                $error['type'],
                $error['message'],
                isset($error['file']) ? $error['file'] : NULL,
                isset($error['line']) ? $error['line'] : NULL
            );
        }
    }

    /**
     * Exception handler callback
     * @param \Throwable $throwable
     */
    public function exceptionhandler($throwable)
    {
        // This code block is added for PHP5 compatibility
        $type = get_class($throwable);
        $methodDefinitionLine = __LINE__ - 4;
        $prefix = 'Uncaught '.$type.': Argument 1 passed to '.__CLASS__.'::'.__METHOD__.' must be an instance of ';
        $postfix = ', instance of '.$type.' given in '.__FILE__.':'.$methodDefinitionLine;
        if (strnatcmp(phpversion(),'7.0.0') >= 0) {
            if (!$throwable instanceof \Throwable) {
                throw new \Exception($prefix.'Throwable'.$postfix);
            }
        }else{
            if (!$throwable instanceof \Exception) {
                throw new \Exception($prefix.'Exception'.$postfix);
            }
        }

        $subject = 'MageLink Exception Handler: '.get_class($throwable);
        $content = $throwable->__toString();

        if (mb_strlen($content) > EmailLogger::EMAIL_MAX_LENGTH) {
            $content = mb_substr($content, 0, EmailLogger::EMAIL_MAX_LENGTH * 0.9)
                ."\r\n...\r\n".mb_substr($content, EmailLogger::EMAIL_MAX_LENGTH * -0.1);
        }
        @mail(self::ERROR_TO, $subject, $content, 'From: '.self::ERROR_FROM);

        $trace = $throwable->getTraceAsString();
        if (mb_strlen($trace) > EmailLogger::EMAIL_MAX_LENGTH) {
            $trace = mb_substr($trace, 0, EmailLogger::EMAIL_MAX_LENGTH * 0.9)
                ."\r\n...\r\n".mb_substr($trace, EmailLogger::EMAIL_MAX_LENGTH * -0.1);
        }
        print $trace;
    }

    /**
     * Error handler callback
     * @param int $errorNo
     * @param string $errorText
     * @param string $errorFile
     * @param int $errorLine
     * @param array $errorContext
     * @return bool|FALSE
     */
    public function errorhandler($errorNo, $errorText, $errorFile = NULL, $errorLine = NULL, array $errorContext = NULL)
    {
        if ($errorFile != NULL && stripos($errorFile, 'ErrorHandler') !== FALSE) {
            return FALSE; // Error occured here!
        }

        if ($errorContext) {
            try{
                if (is_array($errorContext)) {
                    $errorContext = PHP_EOL.'Error Context: '.serialize($errorContext);
                }elseif (is_object($errorContext)) {
                    $errorContext .= PHP_EOL.'Error Context: <'.get_class($errorContext).'>';
                }else{
                    $errorContext = PHP_EOL.'Error Context: '.$errorContext;
                }
            }catch (\Exception $exception) {
                $errorContext = PHP_EOL.'Error occurred during the error context conversion.';
            }
        }else{
            $errorContext = '';
        }

        $debugInfo = PHP_EOL.'debug_backtrace{';
        foreach (debug_backtrace() as $key=>$value) {
            if (is_scalar($value)) {
                $debugInfo .= PHP_EOL.$key.':'.$value;
            }else{
                try{
                    if (is_array($value)) {
                        $debugInfo .= PHP_EOL.$key.':'.serialize($value);
                    }elseif (is_object($value)) {
                        $debugInfo .= PHP_EOL.$key.':<'.get_class($value).'>';
                    }
                }catch (\Exception $exception) {}
            }
        }
        $debugInfo .= PHP_EOL.'}';

        switch ($errorNo) {
            case E_ERROR:
                $errorType = 'ERROR';
                break;
            case E_WARNING:
                $errorType = 'WARNING';
                break;
            case E_PARSE:
                $errorType = 'PARSE';
                break;
            case E_NOTICE:
                $errorType = 'NOTICE';
                break;
            case E_CORE_ERROR:
                $errorType = 'CORE_ERROR';
                break;
            case E_CORE_WARNING:
                $errorType = 'CORE_WARNING';
                break;
            case E_COMPILE_ERROR:
                $errorType = 'COMPILE_ERROR';
                break;
            case E_COMPILE_WARNING:
                $errorType = 'COMPILE_WARNING';
                break;
            case E_USER_ERROR:
                $errorType = 'USER_ERROR';
                break;
            case E_USER_WARNING:
                $errorType = 'USER_WARNING';
                break;
            case E_USER_NOTICE:
                $errorType = 'USER_NOTICE';
                break;
            case E_STRICT:
                $errorType = 'STRICT';
                break;
            case E_RECOVERABLE_ERROR:
                $errorType = 'RECOVERABLE_ERROR';
                break;
            case E_DEPRECATED:
                $errorType = 'DEPRECATED';
                break;
            case E_USER_DEPRECATED:
                $errorType = 'USER_DEPRECATED';
                break;
            default:
                $errorType = 'UNKNOWN_'.$errorNo;
                break;
        }

        $content = '['.$errorType.'] '.$errorText
            .PHP_EOL.'Line '.$errorLine.' in file '.$errorFile.'.'
            .PHP_EOL.PHP_EOL.$debugInfo
            .PHP_EOL.PHP_EOL.$errorContext;

        if ($this->_lastError != $content) {
            if (mb_strlen($content) > EmailLogger::EMAIL_MAX_LENGTH) {
                $content = mb_substr($content, 0, EmailLogger::EMAIL_MAX_LENGTH * 0.9)
                    ."\r\n...\r\n".mb_substr($content, EmailLogger::EMAIL_MAX_LENGTH * -0.1);
            }
            mail(self::ERROR_TO, 'MageLink Error Handler: '.$errorType, $content, 'From: '.self::ERROR_FROM);
            $this->_lastError = $content;
        }

        return FALSE;
    }

}

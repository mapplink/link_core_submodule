<?php
/**
 * Application\Helper\ErrorHandler
 *
 * @category Application
 * @package Application\Helper
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license Commercial - All Rights Reserved
 */

namespace Application\Helper;


class ErrorHandler
{

    const ERROR_TO = 'forms@lero9.co.nz';
    const ERROR_FROM = 'noreply@lero9.co.nz';

    /** @var bool|NULL $allowException */
    protected static $allowException = NULL;

    /** @var string|FALSE $_lastError */
    protected $_lastError = FALSE;


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
     * @param \Exception $ex
     */
    public function exceptionhandler (\Exception $ex)
    {
        @mail(
            self::ERROR_TO,
            'MageLink Exception Handler: '.get_class($ex),
            $ex->__toString(),
            'From: '.self::ERROR_FROM
        );
        print $ex->getTraceAsString();
    }

    /**
     * Error handler callback
     * @param int $errorNo
     * @param string $errorText
     * @param string $errorFile
     * @param int $errorLine
     * @param array $errorContext
     * @return bool
     */
    public function errorhandler($errorNo, $errorText, $errorFile = NULL, $errorLine = NULL, array $errorContext = NULL)
    {
        if ($errorFile != NULL && stripos($errorFile, 'ErrorHandler') !== FALSE) {
            return FALSE; // Error occured here!
        }

        if ($errorContext) {
            try{
                if (is_scalar($errorContext)) {
                    $errorContext = PHP_EOL.'Error Context: '.$errorContext;
                }else{
                    $errorContext = PHP_EOL.'Error Context: '.serialize($errorContext);
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
            }elseif (is_array($value)) {
                $debugInfo .= PHP_EOL.$key.':'.serialize($value);
            }elseif (is_object($value)) {
                $debugInfo .= PHP_EOL.$key.':'.get_class($value);
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
            mail(self::ERROR_TO, 'MageLink Error Handler: '.$errorType, $content, 'From: '.self::ERROR_FROM);
            $this->_lastError = $content;
        }

        return FALSE;
    }

}
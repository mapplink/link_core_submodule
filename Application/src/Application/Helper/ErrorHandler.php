<?php

namespace Application\Helper;

class ErrorHandler {

    const ERROR_TO = 'forms@lero9.co.nz';
    const ERROR_FROM = 'noreply@lero9.co.nz';

    protected static $allowEx = null;

    public function __construct($allowEx=null){
        if(self::$allowEx === null){
            register_shutdown_function(array($this, 'shutdownhandler'));
        }
        if($allowEx === null && self::$allowEx !== null){
            $allowEx = self::$allowEx;
        }else if($allowEx !== null){
            self::$allowEx = $allowEx;
        }else{
            self::$allowEx = false;
            $allowEx = false;
        }
        set_error_handler(array($this, 'errorhandler'));
        if($allowEx){
            set_exception_handler(array($this, 'exceptionhandler'));
        }
    }

    function shutdownhandler(){
        $err = error_get_last();
        if($err == null){
            return;
        }
        if($err['type'] === E_NOTICE){
            return; // Don't email shutdown notices
        }
        $this->errorhandler($err['type'], $err['message'], isset($err['file']) ? $err['file'] : null, isset($err['line']) ? $err['line'] : null);
    }

    /**
     * Exception handler callback
     * @param \Exception $ex
     */
    public function exceptionhandler ( \Exception $ex ) {
        @mail(self::ERROR_TO, 'MageLink Exception Handler: ' . get_class($ex), $ex->__toString(), 'From: ' . self::ERROR_FROM);
        echo $ex->getTraceAsString();
    }

    /**
     * Error handler callback
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @param array $errcontext
     * @return bool
     */
    public function errorhandler ( $errno, $errstr, $errfile=null, $errline=null, array $errcontext=null ) {
        if($errfile != null && stripos($errfile, 'ErrorHandler') !== false){
            return false; // Error occured here!
        }
        $typestr = 'UNKN';
        switch($errno){
            case E_ERROR:
                $typestr = 'ERROR';
                break;
            case E_WARNING:
                $typestr = 'WARNING';
                break;
            case E_PARSE:
                $typestr = 'PARSE';
                break;
            case E_NOTICE:
                $typestr = 'NOTICE';
                break;
            case E_CORE_ERROR:
                $typestr = 'CORE_ERROR';
                break;
            case E_CORE_WARNING:
                $typestr = 'CORE_WARNING';
                break;
            case E_COMPILE_ERROR:
                $typestr = 'COMPILE_ERROR';
                break;
            case E_COMPILE_WARNING:
                $typestr = 'COMPILE_WARNING';
                break;
            case E_USER_ERROR:
                $typestr = 'USER_ERROR';
                break;
            case E_USER_WARNING:
                $typestr = 'USER_WARNING';
                break;
            case E_USER_NOTICE:
                $typestr = 'USER_NOTICE';
                break;
            case E_STRICT:
                $typestr = 'STRICT';
                break;
            case E_RECOVERABLE_ERROR:
                $typestr = 'RECOVERABLE_ERROR';
                break;
            case E_DEPRECATED:
                $typestr = 'DEPRECATED';
                break;
            case E_USER_DEPRECATED:
                $typestr = 'USER_DEPRECATED';
                break;
            default:
                $typestr = 'UNKN_'.$errno;
                break;
        }

        $content = <<<EOF
[{$typestr}] {$errstr}

Line {$errline} in file {$errfile}.

EOF;
;

        mail(self::ERROR_TO, 'MageLink Error Handler: ' . $typestr, $content, 'From: ' . self::ERROR_FROM);

        return false; // Continue PHP handler
    }

}
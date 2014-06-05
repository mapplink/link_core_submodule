<?php

/* 
 * Copyright (c) 2014 Lero9 Limited
 * All Rights Reserved
 * This software is subject to our terms of trade and any applicable licensing agreements.
 */

namespace Magelink\Exception;

class MagelinkException extends \Exception {
    
    /**
     * Create the exception, logging to our LogService in the process (except where that caused problems).
     * 
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     */
    public function __construct($message = null, $code = 0, \Exception $previous = null){
        parent::__construct($message, $code, $previous);
        
        if($this->canLog()){
            // TODO: Call LogService
            
        }else if(php_sapi_name() === 'cli'){
            echo 'EXCEPTION: ' . get_class($this) . ': ' . PHP_EOL . $this->__toString() . PHP_EOL;
        }else{
            // TODO: Implement behavior in web
        }
    }
    
    /**
     * Returns whether or not we can log the exception automatically. Used to prevent infinite loops.
     * @return boolean
     */
    protected function canLog(){
        $trace = $this->getTrace();
        foreach($trace as $entry){
            if(isset($entry['file']) && stripos($entry['file'], '/log/') !== false){
                // Exception comes from log module at some point!
                return false;
            }
        }
        
        return true;
    }
    
}
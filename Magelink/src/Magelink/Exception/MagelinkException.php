<?php
/**
 * @package Magelink\Exception
 * @author Matt Johnston
 * @author Andreas Gerhards <andreas@lero9.co.nz>
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Magelink\Exception;


class MagelinkException extends \Exception
{

    /**
     * Create the exception, logging to our LogService in the process (except where that caused problems).
     * @param string $message
     * @param int $code
     * @param \Exception $previous
     */
    public function __construct($message = NULL, $code = 0, \Exception $previous = NULL)
    {
        parent::__construct($message, $code, $previous);

        if ($this->canLog()) {
            // @todo: Call LogService
        }elseif (php_sapi_name() === 'cli') {
            echo 'EXCEPTION: '.get_class($this).': '.PHP_EOL.$this->__toString().PHP_EOL;
        }else{
            // @todo: Implement behavior in web
        }
    }

    /**
     * Returns whether or not we can log the exception automatically. Used to prevent infinite loops.
     * @return boolean
     */
    protected function canLog()
    {
        $trace = $this->getTrace();
        foreach ($trace as $entry) {
            if (isset($entry['file']) && stripos($entry['file'], '/log/') !== FALSE) {
                // Exception comes from log module at some point!
                return FALSE;
            }
        }

        return TRUE;
    }

}

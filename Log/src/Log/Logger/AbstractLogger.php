<?php

namespace Log\Logger;

use \Zend\ServiceManager\ServiceLocatorAwareInterface;
use \Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class AbstractLogger represents a log output method. Child classes should implement the provided abstract methods to provide log output/persistence.
 *
 * @package Log\Logger
 */
abstract class AbstractLogger implements ServiceLocatorAwareInterface {

    /**
     * Initialize the logger instance and verify if it is able to log messages.
     * @param array $config
     * @return boolean Whether this logger is able to log messages (i.e. whether all dependencies are fulfilled)
     */
    abstract function init($config=array());

    /**
     * Provides a log message to the logger. The logger instance SHOULD output it immediately, but may queue it if necessary.
     *
     * @param $level
     * @param $code
     * @param $message
     * @param $data
     * @param $extraData
     * @param $lastStackFrame
     */
    abstract function printLog($level, $code, $message, $data, $extraData, $lastStackFrame);

    protected function convertDataHuman($ed, $hideArray=false){
        $ret = '';
        if(is_scalar($ed)){
            $ret = ''.$ed;
        }else if(is_object($ed)){
            $ret = 'Object<'.get_class($ed).'>';
        }else if(is_array($ed)){
            $count = count($ed);
            if($count > 5){
                $ed = array_slice($ed, 0, 5);
                $ed[] = '...';
            }
            $ret = 'Array['.$count.']';

            if(!$hideArray){
                $ret .= '(';
                $contentsSimple = array();
                foreach($ed as $k=>$v){
                    if(is_int($k)){
                        $contentsSimple[] = $this->convertDataHuman($v, true);
                    }else{
                        $contentsSimple[] = $k . ': ' . $this->convertDataHuman($v, true);
                    }
                }
                $ret .= implode(', ', $contentsSimple);
                $ret .= ')';
            }
        }else if(is_null($ed)){
            $ret = 'NULL';
        }else{
            $ret = 'INV<' . gettype($ed) . '>';
        }
        return $ret;
    }

    /**
     * Output any queued messages (if relevant).
     */
    abstract function flushLog();

    protected $_serviceLocator;

    /**
     * Set service locator
     *
     * @param ServiceLocatorInterface $serviceLocator
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->_serviceLocator = $serviceLocator;
    }

    /**
     * Get service locator
     *
     * @return ServiceLocatorInterface
     */
    public function getServiceLocator()
    {
        return $this->_serviceLocator;
    }

}